<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoFrontendTestCase;

uses(MahoFrontendTestCase::class)->group('browser', 'paypal');

afterAll(fn() => MahoServer::stop());

beforeEach(function () {
    if (!browserTestsReady()) {
        test()->markTestSkipped('Playwright is not installed');
    }
    // Needs the store actually configured with PayPal credentials (the test harness or CI
    // injects them at install time); without them the real checkout can't run.
    if (!Mage::getModel('paypal/config')->hasCredentials()) {
        test()->markTestSkipped('PayPal sandbox credentials not configured on the store');
    }
    // Maho is already bootstrapped by MahoFrontendTestCase::setUp(). Each test sets its
    // own display currency, then (re)starts the server so it serves the configured DB.
    ensureSaleable(aProductId());
    MahoServer::start();
});

/**
 * Type into a cross-origin hosted-field iframe, bypassing the plugin's withinFrame().
 *
 * pest-plugin-browser's withinFrame() first waits for the page to reach networkidle,
 * which PayPal's live card iframes never do. This replicates the rest of withinFrame()
 * (resolve the frame, build a Page on its guid) without that wait, then sends real
 * keystrokes (the hosted fields ignore programmatic value-setting). PayPal's iframes are
 * flaky, so read the value back and retry until it sticks.
 */
function typeInFrame(object $web, string $frameSelector, string $value): void
{
    $page = $web->page();
    $frame = (new \Pest\Browser\Support\GuessLocator($page))->for($frameSelector)->frameLocator($frameSelector);
    $frame->waitFor(['state' => 'attached']);
    $content = $frame->contentFrame();

    $innerPage = new \Pest\Browser\Playwright\Page($page->context(), $content->guid, $content->guid);
    $input = $innerPage->locator('input');
    $input->waitFor(['state' => 'visible']);

    $digits = preg_replace('/\D/', '', $value);
    for ($attempt = 0; $attempt < 4; $attempt++) {
        $input->click();
        $input->press('ControlOrMeta+a');
        $input->press('Delete');
        $input->type($value, ['delay' => 60]);

        if (preg_replace('/\D/', '', $input->inputValue()) === $digits) {
            return;
        }
    }
    throw new \RuntimeException("Could not enter '{$value}' into {$frameSelector} (hosted field rejected keystrokes)");
}

/**
 * Configure the test store currency: base is always USD (the install default); the display
 * currency and base->display rate vary so we can exercise both single- and multi-currency
 * checkouts. Cache is flushed so the running server picks up the change.
 */
function configureStoreCurrency(string $display, float $rate): void
{
    $base = 'USD';
    $config = Mage::getModel('core/config');
    $config->saveConfig('dev/log/active', '1');
    $config->saveConfig('currency/options/base', $base);
    $config->saveConfig('currency/options/allow', implode(',', array_unique([$base, $display])));
    $config->saveConfig('currency/options/default', $display);
    Mage::getModel('directory/currency')->saveRates([
        $base => [$base => 1.0, $display => $rate],
    ]);
    Mage::app()->getStore()->resetConfig();
    Mage::app()->cleanCache();
}

/**
 * Give the order entity a unique increment id for this run. The reserved order id becomes
 * the PayPal invoice_id, and the sandbox account blocks duplicate invoice ids across
 * transactions; reused or concurrent runs would otherwise collide. A time base plus a
 * random suffix keeps it unique even across overlapping CI runs on the same sandbox.
 */
function bumpOrderIncrementId(): void
{
    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $orderType = Mage::getModel('eav/entity_type')->loadByCode('order');
    // Millisecond base + random suffix: unique across concurrent CI jobs hitting the same
    // sandbox (which would otherwise collide and trip PayPal's duplicate-invoice block).
    $unique = (int) round(microtime(true) * 1000) * 100_000 + random_int(0, 99_999);
    $write->update(
        $resource->getTableName('eav/entity_store'),
        ['increment_last_id' => $unique],
        ['entity_type_id = ?' => (int) $orderType->getId()],
    );
}

/** Pick a salable, priced, visible simple product id from the test DB. */
function aProductId(): int
{
    $p = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', 1)
        ->addAttributeToFilter('visibility', ['in' => [3, 4]])
        ->addAttributeToFilter('price', ['gt' => 0])
        ->setPageSize(1)
        ->getFirstItem();
    return (int) $p->getId();
}

/**
 * Make a product permanently saleable for the run: each placed order decrements stock, so
 * after enough runs the product goes out of stock and loses its "Add to Cart" button. Set
 * the stock item to unmanaged + in stock so inventory can't deplete it.
 */
function ensureSaleable(int $productId): void
{
    /** @var Mage_CatalogInventory_Model_Stock_Item $stockItem */
    $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
    $stockItem->setData('use_config_manage_stock', 0)
        ->setData('manage_stock', 0)
        ->setData('is_in_stock', 1)
        ->setData('qty', 1000)
        ->save();
}

/**
 * Verify the full money breakdown stored on the order is internally consistent: every
 * display amount is its base amount converted at the order's rate (1-cent tolerance for
 * per-line rounding), and the components sum to the grand total in both currencies.
 */
function assertOrderAmountsReconcile(Mage_Sales_Model_Order $order, float $rate): void
{
    $pairs = [
        'subtotal' => [(float) $order->getBaseSubtotal(), (float) $order->getSubtotal()],
        'tax' => [(float) $order->getBaseTaxAmount(), (float) $order->getTaxAmount()],
        'shipping' => [(float) $order->getBaseShippingAmount(), (float) $order->getShippingAmount()],
        'discount' => [(float) $order->getBaseDiscountAmount(), (float) $order->getDiscountAmount()],
    ];
    foreach ($pairs as [$base, $display]) {
        expect(abs($base * $rate - $display))->toBeLessThanOrEqual(0.01);
    }
    $sumDisplay = array_sum(array_map(fn($p) => $p[1], $pairs));
    $sumBase = array_sum(array_map(fn($p) => $p[0], $pairs));
    expect(abs($sumDisplay - (float) $order->getGrandTotal()))->toBeLessThanOrEqual(0.01);
    expect(abs($sumBase - (float) $order->getBaseGrandTotal()))->toBeLessThanOrEqual(0.01);
}

/** The most recently placed order (created by the server during the browser run). */
function latestOrder(): Mage_Sales_Model_Order
{
    $collection = Mage::getResourceModel('sales/order_collection');
    $collection->getSelect()->order('entity_id DESC')->limit(1);
    /** @var Mage_Sales_Model_Order $order */
    $order = $collection->getFirstItem();
    return $order;
}

/**
 * Drive the storefront from product page to the rendered Advanced-Checkout card fields,
 * staying in one page so the cart session cookie persists. Returns the page.
 */
function gotoCardFields(): object
{
    $id = aProductId();
    $page = visit(MahoServer::baseUrl() . "/catalog/product/view/id/{$id}/")
        ->click('Add to Cart')
        ->assertSee('Cart Subtotal')
        ->click('Checkout')
        ->assertSee('Billing Address');

    // Filling the billing fields fires the debounced auto-save: it loads the shipping
    // methods, auto-selects the single one, then loads the payment methods.
    $page->fill('#billing\\:firstname', 'Test')
        ->fill('#billing\\:lastname', 'Buyer')
        ->fill('#billing\\:email', 'buyer-test@example.com')
        ->fill('#billing\\:street1', '1 Infinite Loop')
        ->fill('#billing\\:city', 'Cupertino')
        ->select('#billing\\:region_id', 'California')
        ->fill('#billing\\:postcode', '95014')
        ->fill('#billing\\:telephone', '5551234567')
        ->assertDontSee('Waiting for address')
        ->assertDontSee('Waiting for shipping method')
        ->assertSee('Credit or Debit Card');

    return $page->click('Credit or Debit Card')
        ->assertPresent('#paypal-card-fields-number iframe');
}

/**
 * Poll the page URL until it contains $needle or the timeout elapses. A real card
 * authorization can lag several seconds under load before redirecting to the success page;
 * a declined one never redirects. Polling (instead of a fixed wait) returns as soon as the
 * redirect lands and lets the caller tell "slow success" from "declined, retry".
 */
function waitForUrlContains(object $page, string $needle, int $timeoutSeconds): bool
{
    $deadline = time() + $timeoutSeconds;
    do {
        if (str_contains($page->url(), $needle)) {
            return true;
        }
        $page->wait(1);
    } while (time() < $deadline);

    return str_contains($page->url(), $needle);
}

/**
 * Ask PayPal what it actually has on record for a placed order, via the stored
 * paypal_order_id. This is the source-of-truth cross-check: PayPal's own record (status,
 * invoice_id, order amount, and the authorization/capture it will settle) must match what
 * Maho stored on the order. Returns the relevant fields from PayPal's response.
 */
function paypalRecord(Mage_Sales_Model_Order $order): array
{
    $paypalOrderId = (string) $order->getPayment()->getData('paypal_order_id');
    /** @var Maho_Paypal_Model_Api_Client $client */
    $client = Mage::getModel('paypal/api_client', ['store_id' => (int) $order->getStoreId()]);
    $result = $client->getOrder($paypalOrderId);

    $pu = $result['purchase_units'][0] ?? [];
    $payments = $pu['payments'] ?? [];
    $settlement = $payments['authorizations'][0] ?? $payments['captures'][0] ?? null;
    if ($settlement === null) {
        throw new \RuntimeException("PayPal order {$paypalOrderId} has no authorization/capture on record");
    }
    return [
        'status' => (string) ($result['status'] ?? ''),
        'invoice_id' => (string) ($pu['invoice_id'] ?? ''),
        'order_value' => (float) ($pu['amount']['value'] ?? 0),
        'order_currency' => (string) ($pu['amount']['currency_code'] ?? ''),
        'settlement_value' => (float) ($settlement['amount']['value'] ?? 0),
        'settlement_currency' => (string) ($settlement['amount']['currency_code'] ?? ''),
    ];
}

it('renders inline PayPal card fields at checkout (Advanced Checkout, no popup)', function () {
    configureStoreCurrency('EUR', 0.9);

    gotoCardFields()
        ->assertPresent('#paypal-card-fields-expiry iframe')
        ->assertPresent('#paypal-card-fields-cvv iframe')
        ->assertSee('€')
        ->assertDontSee('Server returned status')
        ->screenshot(true, 'paypal-card-fields');
});

it('completes a full card order through to the success page with reconciled totals', function (string $display, float $rate) {
    configureStoreCurrency($display, $rate);

    // The 7 CI matrix jobs hit the one shared PayPal sandbox account concurrently, so a card
    // authorization is occasionally declined or slow-walked under that load. Retry the whole
    // card flow a few times, reserving a fresh invoice id each attempt (the sandbox blocks
    // duplicate invoice ids, so a retried order needs a new one). Most runs succeed first try.
    $attempts = (int) (getenv('MAHO_PAYPAL_CARD_ATTEMPTS') ?: 3);
    $page = null;
    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        bumpOrderIncrementId();

        $page = gotoCardFields()->wait(2);

        // Type the sandbox test card into the hosted fields. Do NOT click into the billing
        // form afterwards: that fires the checkout auto-save, which re-renders the payment
        // block and drops the card session. Click Place Order directly instead.
        typeInFrame($page, '#paypal-card-fields-number iframe', '4111111111111111');
        typeInFrame($page, '#paypal-card-fields-expiry iframe', '12/2030');
        typeInFrame($page, '#paypal-card-fields-cvv iframe', '123');

        $page->click('Place Order');

        // A successful auth redirects to the success page (possibly after a few seconds); a
        // declined one stays on the checkout. Either way, stop polling and decide below.
        if (waitForUrlContains($page, 'checkout/onepage/success', 30)) {
            break;
        }
    }

    $page->screenshot(true, "paypal-success-{$display}");

    // The card payment must land on the order success page (asserting the URL surfaces the
    // actual landing page in the failure message if every attempt was declined).
    expect($page->url())->toContain('checkout/onepage/success');

    // The order is displayed in the display currency and recorded with base in USD;
    // amounts reconcile: base_grand_total * base_to_order_rate == grand_total.
    $order = latestOrder();
    expect($order->getId())->not->toBeNull();
    expect($order->getOrderCurrencyCode())->toBe($display);
    expect($order->getBaseCurrencyCode())->toBe('USD');
    expect((float) $order->getGrandTotal())->toBeGreaterThan(0.0);
    expect(round((float) $order->getBaseToOrderRate(), 4))->toBe(round($rate, 4));
    expect(round((float) $order->getBaseGrandTotal() * (float) $order->getBaseToOrderRate(), 2))
        ->toBe(round((float) $order->getGrandTotal(), 2));

    // Every stored amount (subtotal/tax/shipping/discount) must convert from base at the
    // order rate and sum to the grand total, in both the base and display currency.
    assertOrderAmountsReconcile($order, $rate);

    // Source-of-truth cross-check against PayPal's own systems: query the order from
    // PayPal's API and assert every field matches what Maho stored. PayPal transacts in the
    // base currency (USD), so its recorded order and the authorization it will settle are
    // both in base, regardless of the store's display currency.
    $baseGrandTotal = round((float) $order->getBaseGrandTotal(), 2);
    $paypal = paypalRecord($order);
    expect($paypal['status'])->toBeIn(['COMPLETED', 'APPROVED']);
    expect($paypal['invoice_id'])->toBe($order->getIncrementId());           // same order, not a replay
    expect($paypal['order_currency'])->toBe('USD');
    expect(round($paypal['order_value'], 2))->toBe($baseGrandTotal);         // order amount on PayPal
    expect($paypal['settlement_currency'])->toBe('USD');
    expect(round($paypal['settlement_value'], 2))->toBe($baseGrandTotal);    // amount PayPal will charge
})->with([
    'single currency (USD base / USD display)' => ['USD', 1.0],
    'multi currency (USD base / EUR display)' => ['EUR', 0.9],
]);

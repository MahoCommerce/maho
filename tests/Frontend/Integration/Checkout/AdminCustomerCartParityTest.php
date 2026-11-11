<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The cart tab of the admin customer page removes and configures a line of the cart of the customer through
 * Mage_Checkout_Service_Cart::removeItem() and reconfigureItem(), as the storefront cart does (#1348).
 */

/**
 * Post $fields to the configure dialog of a line of the cart of the customer, and return the JSON answer.
 *
 * @param array<string, mixed> $fields
 * @return array<string, mixed>
 */
function adminCartConfigure(int $customerId, int $itemId, array $fields): array
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create(
        "http://localhost/admin/customer_cart_product_composite_cart/update?customer_id={$customerId}&id={$itemId}&website_id=1",
        'POST',
        $fields,
    ));
    $request->setRouteName('adminhtml')->setControllerName('customer_cart_product_composite_cart')->setActionName('update')->setDispatched(true);
    Mage::app()->setRequest($request);
    $controller = new Mage_Adminhtml_Customer_Cart_Product_Composite_CartController($request, new Mage_Core_Controller_Response_Http());
    $controller->updateAction();

    return Mage::helper('core')->jsonDecode($controller->getResponse()->getBody());
}

/**
 * Post the removal of the line $itemId to the cart tab of the admin customer page. The action renders the
 * tab after the removal, so the design is the admin design that preDispatch() sets in a real request.
 */
function adminCartRemove(int $customerId, int $itemId): void
{
    Mage::getDesign()
        ->setArea(Mage_Core_Model_App_Area::AREA_ADMINHTML)
        ->setPackageName((string) Mage::getConfig()->getNode('stores/admin/design/package/name'))
        ->setTheme((string) Mage::getConfig()->getNode('stores/admin/design/theme/openmage'));
    Mage::app()->getLayout()->setArea(Mage_Core_Model_App_Area::AREA_ADMINHTML);

    AdminApiParity::admin(Mage_Adminhtml_CustomerController::class, 'cart', ['id' => $customerId, 'website_id' => 1, 'delete' => $itemId]);
}

/**
 * Post $fields to the configure page of the storefront cart, with $quote as the cart of the session. Return the
 * error message of the session, or null.
 *
 * @param array<string, mixed> $fields
 */
function storefrontCartConfigure(Mage_Sales_Model_Quote $quote, int $itemId, array $fields): ?string
{
    $session = Mage::getSingleton('checkout/session');
    $session->replaceQuote(Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId()));
    $session->getMessages(true);
    Mage::getSingleton('checkout/cart')->unsetData('quote');

    // getParams() reads the superglobals, as on a real request
    $_POST = ['id' => $itemId] + $fields;
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/checkout/cart/updateItemOptions', 'POST', $_POST));
    $request->setRouteName('checkout')->setControllerName('cart')->setActionName('updateItemOptions')->setDispatched(true);
    Mage::app()->setRequest($request);
    try {
        new Mage_Checkout_CartController($request, new Mage_Core_Controller_Response_Http())->updateItemOptionsAction();
    } finally {
        $_POST = [];
    }

    foreach ($session->getMessages(true)->getItems() as $message) {
        if ($message->getType() !== Mage_Core_Model_Message::SUCCESS) {
            return $message->getText();
        }
    }
    return null;
}

function adminCartQuote(Mage_Customer_Model_Customer $customer): Mage_Sales_Model_Quote
{
    return Mage::getModel('sales/quote')->setWebsite(Mage::app()->getWebsite(1))->loadByCustomer($customer);
}

/**
 * @return array{qty: float, engraving: string|null}
 */
function adminCartLine(Mage_Sales_Model_Quote $quote, int $optionId): array
{
    $item = Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId())->getAllVisibleItems()[0];
    return ['qty' => (float) $item->getQty(), 'engraving' => $item->getOptionByCode('option_' . $optionId)?->getValue()];
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());

    $this->product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku('pest-admin-cart-' . uniqid())->setName('Pest admin cart product')->setPrice(10.0)->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100])
        ->setHasOptions(true)->setCanSaveCustomOptions(true)
        ->setProductOptions([['title' => 'Engraving', 'type' => 'field', 'is_require' => 0, 'price' => 0, 'price_type' => 'fixed', 'sort_order' => 0]]);
    $this->product->save();
    $this->optionId = (int) Mage::getModel('catalog/product_option')->getCollection()
        ->addFieldToFilter('product_id', $this->product->getId())->getFirstItem()->getId();

    $this->customer = Mage::getModel('customer/customer')
        ->setWebsiteId(1)
        ->setEmail('admin-cart-' . uniqid() . '@example.com')
        ->setFirstname('Jane')
        ->setLastname('Doe')
        ->setPassword('Password123!');
    $this->customer->save();

    $cart = Mage::getService('checkout/cart');
    $this->quote = $cart->getForCustomer((int) $this->customer->getId(), 1);
    $this->itemId = (int) $cart->addProduct(
        $this->quote,
        $cart->getProductById((int) $this->product->getId(), 1),
        new Maho\DataObject(['qty' => 2, 'options' => [$this->optionId => 'For Jane']]),
    )->getId();
});

afterEach(function () {
    Mage::getModel('sales/quote')->loadByIdWithoutStore($this->quote->getId())->delete();
    Mage::register('isSecureArea', true, true);
    $this->customer->delete();
    Mage::getModel('catalog/product')->load($this->product->getId())->delete();
    Mage::unregister('isSecureArea');
});

it('removes a line of the cart and collects the totals again', function () {
    adminCartRemove((int) $this->customer->getId(), $this->itemId);

    $quote = adminCartQuote($this->customer);
    expect($quote->getAllVisibleItems())->toBe([])
        ->and((float) $quote->getItemsQty())->toBe(0.0)
        ->and((float) $quote->getSubtotal())->toBe(0.0);
});

it('keeps the cart when the line to remove is not in it', function () {
    adminCartRemove((int) $this->customer->getId(), $this->itemId + 1000);

    expect(adminCartLine($this->quote, $this->optionId))->toBe(['qty' => 2.0, 'engraving' => 'For Jane']);
});

it('gives the line the options and the qty of the dialog on both transports', function () {
    $fields = ['qty' => 3, 'options' => [$this->optionId => 'For John']];

    expect(adminCartConfigure((int) $this->customer->getId(), $this->itemId, $fields))->toBe(['ok' => true])
        ->and(adminCartLine($this->quote, $this->optionId))->toBe(['qty' => 3.0, 'engraving' => 'For John']);

    $itemId = (int) Mage::getModel('sales/quote')->loadByIdWithoutStore($this->quote->getId())->getAllVisibleItems()[0]->getId();
    expect(storefrontCartConfigure($this->quote, $itemId, ['qty' => 4, 'options' => [$this->optionId => 'For Ann']]))->toBeNull()
        ->and(adminCartLine($this->quote, $this->optionId))->toBe(['qty' => 4.0, 'engraving' => 'For Ann']);
});

it('refuses the same qty on both transports', function () {
    $fields = ['qty' => 20000, 'options' => [$this->optionId => 'For Jane']];

    expect(adminCartConfigure((int) $this->customer->getId(), $this->itemId, $fields))
        ->toBe(['error' => true, 'message' => 'The maximum quantity allowed for purchase is 10000.'])
        ->and(storefrontCartConfigure($this->quote, $this->itemId, $fields))->toBe('The maximum quantity allowed for purchase is 10000.')
        ->and(adminCartLine($this->quote, $this->optionId))->toBe(['qty' => 2.0, 'engraving' => 'For Jane']);
});

it('refuses a product that the website no longer sells on both transports', function () {
    Mage::getSingleton('catalog/product_action')->updateAttributes(
        [$this->product->getId()],
        ['status' => Mage_Catalog_Model_Product_Status::STATUS_DISABLED],
        0,
    );
    $message = "Product '{$this->product->getSku()}' is not available";

    expect(adminCartConfigure((int) $this->customer->getId(), $this->itemId, ['qty' => 1]))->toBe(['error' => true, 'message' => $message])
        ->and(storefrontCartConfigure($this->quote, $this->itemId, ['qty' => 1]))->toBe($message);
});

describe('kept difference: staff configures a line of a product that the storefront does not show', function () {
    it('accepts it in the admin and refuses it on the storefront', function () {
        Mage::getSingleton('catalog/product_action')->updateAttributes(
            [$this->product->getId()],
            ['visibility' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE],
            0,
        );

        expect(storefrontCartConfigure($this->quote, $this->itemId, ['qty' => 1]))->toBe("Product '{$this->product->getSku()}' is not available")
            ->and(adminCartConfigure((int) $this->customer->getId(), $this->itemId, ['qty' => 1]))->toBe(['ok' => true]);
    });
});

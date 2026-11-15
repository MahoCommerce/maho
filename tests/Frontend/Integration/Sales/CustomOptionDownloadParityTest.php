<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Get;
use Mage\Catalog\Api\ProductCustomOptionProvider;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\CartParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The storefront link sales/download/downloadCustomOption and GET /custom-option-file/{optionId}/{key} send the file
 * of a file option of a cart item through Mage_Catalog_Service_Product_Option::openUploadedFile() (#1348).
 * A file that the storefront sends ends the PHP process, so the tests compare the refusals of both transports, and
 * read an accepted file through the API.
 */

/**
 * Ask the storefront for the file of the cart item option $itemOptionId.
 *
 * @return bool whether the storefront refused the request with its 404 page
 */
function downloadRefusedByWeb(int $itemOptionId, string $key): bool
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/sales/download/downloadCustomOption', 'GET', ['id' => $itemOptionId, 'key' => $key]),
    );
    $request->setRouteName('sales')->setControllerName('download')->setActionName('downloadCustomOption')->setDispatched(true);
    Mage::app()->setRequest($request);
    new Mage_Sales_DownloadController($request, new Mage_Core_Controller_Response_Http())->downloadCustomOptionAction();

    return $request->getActionName() === 'noRoute' && !$request->isDispatched();
}

/**
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function downloadViaApi(int $itemOptionId, string $key): array
{
    $request = SymfonyRequest::create("/api/rest/v2/custom-option-file/{$itemOptionId}/{$key}");
    $provider = new ProductCustomOptionProvider(CartParity::guestSecurity());

    return AdminApiParity::api($request, fn() => $provider->provide(new Get(name: 'download_option_file'), [], ['request' => $request]));
}

/**
 * Give the item of $quote an option $code whose value names a file under the quote folder of the file options.
 *
 * @return array{option: Mage_Sales_Model_Quote_Item_Option, key: string, path: string}
 */
function downloadParityItemOption(Mage_Sales_Model_Quote $quote, string $code, string $content = 'uploaded-by-the-shopper'): array
{
    $item = $quote->getItemsCollection()->getFirstItem();
    $fileType = Mage::getModel('catalog/product_option_type_file');
    $relativePath = $fileType->getQuoteTargetDir(true) . '/parity_' . uniqid() . '.txt';
    $path = Mage::getBaseDir() . $relativePath;
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0o777, true);
    }
    file_put_contents($path, $content);

    $key = substr(md5($relativePath), 0, 20);
    $option = Mage::getModel('sales/quote_item_option')->setData([
        'item_id' => (int) $item->getId(),
        'product_id' => (int) $item->getProductId(),
        'code' => $code,
        'value' => Mage::helper('core')->jsonEncode([
            'type' => 'text/plain',
            'title' => 'upload.txt',
            'quote_path' => $relativePath,
            'order_path' => $fileType->getOrderTargetDir(true) . '/missing.txt',
            'secret_key' => $key,
        ]),
    ]);
    $option->save();

    return ['option' => $option, 'key' => $key, 'path' => $path];
}

beforeEach(function () {
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    $this->cart = CartParity::cart();
    $productId = (int) $this->cart->getItemsCollection()->getFirstItem()->getProductId();
    $this->productOptions = [];
    foreach (['file', 'field'] as $type) {
        $option = Mage::getModel('catalog/product_option')->setData([
            'product_id' => $productId,
            'type' => $type,
            'title' => 'Parity ' . $type,
            'is_require' => 0,
            'sort_order' => 0,
            'store_id' => 0,
            'price' => 0,
            'price_type' => 'fixed',
        ]);
        $option->save();
        $this->productOptions[$type] = $option;
    }
    $this->files = [];
});

afterEach(function () {
    foreach ($this->files as $path) {
        @unlink($path);
    }
    foreach ($this->productOptions as $option) {
        $option->delete();
    }
    $this->cart->delete();
});

it('sends the file of a file option for its secret key', function () {
    $file = downloadParityItemOption($this->cart, 'option_' . $this->productOptions['file']->getId());
    $this->files[] = $file['path'];

    $api = downloadViaApi((int) $file['option']->getId(), $file['key']);

    expect($api['status'])->toBe(200)
        ->and($api['result'])->toBeInstanceOf(Response::class)
        ->and($api['result']->getContent())->toBe('uploaded-by-the-shopper');
});

it('refuses a wrong key on both transports', function () {
    $file = downloadParityItemOption($this->cart, 'option_' . $this->productOptions['file']->getId());
    $this->files[] = $file['path'];
    $wrongKey = str_repeat('0', 20);

    $api = downloadViaApi((int) $file['option']->getId(), $wrongKey);

    expect(downloadRefusedByWeb((int) $file['option']->getId(), $wrongKey))->toBeTrue()
        ->and($api['status'])->toBe(403)
        ->and($api['errors'])->toBe(['Invalid key']);
});

it('refuses an unknown cart item option on both transports', function () {
    $api = downloadViaApi(999999999, str_repeat('a', 20));

    expect(downloadRefusedByWeb(999999999, str_repeat('a', 20)))->toBeTrue()
        ->and($api['status'])->toBe(404);
});

it('refuses the value of an option that is not a file option on both transports', function () {
    // The value of a text option is the text of the shopper, who can write the fields of a stored file into it
    $file = downloadParityItemOption($this->cart, 'option_' . $this->productOptions['field']->getId());
    $this->files[] = $file['path'];

    $api = downloadViaApi((int) $file['option']->getId(), $file['key']);

    expect(downloadRefusedByWeb((int) $file['option']->getId(), $file['key']))->toBeTrue()
        ->and($api['status'])->toBe(404);
});

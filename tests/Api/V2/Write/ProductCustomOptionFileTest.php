<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 custom-option file download.
 *
 * GET /custom-option-file/{optionId}/{key} authenticates with a 20-char hex
 * secret key. The {key} placeholder is not declared as a uriVariable, so the
 * framework would coerce it toward the resource's int identifier and mangle the
 * hex key to 0, making the key check fail forever (HTTP 403). The provider now
 * reads {optionId} and {key} straight from the request path; this test exercises
 * the success path with a real hex key so a regression would surface.
 *
 * @group write
 */

describe('GET /api/rest/v2/custom-option-file/{optionId}/{key}', function (): void {

    it('downloads the file when the hex secret key matches', function (): void {
        $product = Mage::getModel('catalog/product')->load((int) fixtures('product_id'));
        expect($product->getId())->not->toBeNull();

        // A quote item to satisfy the option's FK.
        $quote = Mage::getModel('sales/quote');
        $quote->addProduct($product, 1);
        $quote->save();
        $item = $quote->getAllItems()[0] ?? null;
        expect($item)->not->toBeNull();

        // A real file on disk under the Maho base dir.
        $relPath = '/public/media/custom_options/test_' . uniqid() . '.txt';
        $fullPath = Mage::getBaseDir() . $relPath;
        @mkdir(dirname($fullPath), 0o777, true);
        file_put_contents($fullPath, 'custom-option-file-contents');

        // Mirror the real flow: a 20-char hex secret key.
        $secretKey = substr(md5($relPath), 0, 20);
        $option = Mage::getModel('sales/quote_item_option');
        $option->setData('item_id', (int) $item->getId());
        $option->setData('product_id', (int) $product->getId());
        $option->setData('code', 'option_file');
        $option->setData('value', serialize([
            'type' => 'text/plain',
            'title' => 'test.txt',
            'quote_path' => $relPath,
            'order_path' => $relPath,
            'secret_key' => $secretKey,
        ]));
        $option->save();
        $optionId = (int) $option->getId();

        try {
            // Correct hex key must succeed (used to be a permanent 403).
            $ok = apiGet("/api/rest/v2/custom-option-file/{$optionId}/{$secretKey}");
            expect($ok['status'])->toBe(200);

            // A wrong key of the same shape is rejected.
            $bad = apiGet("/api/rest/v2/custom-option-file/{$optionId}/" . str_repeat('0', 20));
            expect($bad['status'])->toBe(403);
        } finally {
            $option->delete();
            $quote->delete();
            @unlink($fullPath);
        }
    });

    it('returns 404 for an unknown option id', function (): void {
        $response = apiGet('/api/rest/v2/custom-option-file/999999999/' . str_repeat('a', 20));

        expect($response['status'])->toBe(404);
    });

});

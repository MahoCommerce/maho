<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

use Intervention\Image\Format;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

it('uses the storefront logo in invoice PDFs when the PDF logo is unavailable', function (?string $pdfLogo): void {
    $store = Mage::app()->getDefaultStoreView();
    $storeId = (int) $store->getId();
    Mage::app()->setCurrentStore($storeId);

    $logoPath = 'images/pdf-logo-' . bin2hex(random_bytes(8)) . '.png';
    $skin = Mage::getBaseDir('skin');
    $adminLogo = "$skin/adminhtml/default/default/$logoPath";
    $storeLogo = "$skin/frontend/base/default/$logoPath";
    $images = Maho::getImageManager();
    $storeLogoContents = $images->createImage(30, 10)->fill('0000ff')->encodeUsingFormat(Format::PNG)->toString();
    file_put_contents($adminLogo, $images->createImage(25, 10)->fill('ff0000')->encodeUsingFormat(Format::PNG)->toString());
    file_put_contents($storeLogo, $storeLogoContents);

    try {
        $store->setConfig('design/package/name', 'base');
        $store->setConfig('design/theme/skin', 'default');
        $store->setConfig('design/header/logo_src', $logoPath);
        $store->setConfig('sales/identity/logo', $pdfLogo);
        $design = Mage::getDesign()->setArea('frontend')->setPackageName('base')->setTheme('skin', 'default');
        $fallback = Mage::getSingleton('core/design_fallback')->setStore(Mage_Core_Model_App::ADMIN_STORE_ID);

        $order = Mage::getModel('sales/order')->setStoreId($storeId)
            ->setIncrementId('100000001')->setCreatedAt('2026-01-01 12:00:00')
            ->setIsVirtual(true)->setOrderCurrencyCode('USD')->setBaseCurrencyCode('USD');
        $invoice = Mage::getModel('sales/order_invoice')->setOrder($order)
            ->setIncrementId('100000002')->setCreatedAt('2026-01-01 12:00:00')
            ->setSubtotal(0)->setDiscountAmount(0)->setTaxAmount(0)->setGrandTotal(0);
        $renderer = new class extends Mage_Sales_Model_Order_Pdf_Invoice {
            public string $html = '';

            #[\Override]
            public function generatePdf(string $html, string $filename = 'document.pdf'): string
            {
                $this->html = $html;
                return parent::generatePdf($html, $filename);
            }
        };

        expect($renderer->getPdf([$invoice]))->toStartWith('%PDF-');

        preg_match('/<img src="([^"]*)">/', $renderer->html, $image);
        expect($image[1] ?? null)->toBe('data:image/png;base64,' . base64_encode($storeLogoContents))
            ->and($design->getArea())->toBe('frontend')
            ->and($design->getPackageName())->toBe('base')
            ->and($design->getTheme('skin'))->toBe('default')
            ->and($fallback->getStore()->getId())->toEqual(Mage_Core_Model_App::ADMIN_STORE_ID)
            ->and(Mage::app()->getStore()->getId())->toEqual($storeId);
    } finally {
        unlink($adminLogo);
        unlink($storeLogo);
    }
})->with([
    'empty PDF logo' => [null],
    'missing PDF logo' => ['default/absent-pdf-logo.png'],
]);

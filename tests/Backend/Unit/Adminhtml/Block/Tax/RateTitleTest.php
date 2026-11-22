<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

class TaxRateTitleStub extends Mage_Adminhtml_Block_Tax_Rate_Title
{
    #[\Override]
    public function getTitles(): array
    {
        $titles = [];
        foreach ($this->getStores() as $store) {
            $titles[$store->getId()] = 'VAT" autofocus onfocus="alert(1)';
        }
        return $titles;
    }
}

it('escapes the tax rate title inside the input value attribute', function () {
    Mage::getDesign()->setArea('adminhtml')->setPackageName('default')->setTheme('default');
    $block = Mage::app()->getLayout()->createBlock(TaxRateTitleStub::class);

    $html = $block->toHtml();

    expect($html)->toContain('value="VAT&quot; autofocus onfocus=&quot;alert(1)"')
        ->and($html)->not->toContain('onfocus="alert(1)"');
});

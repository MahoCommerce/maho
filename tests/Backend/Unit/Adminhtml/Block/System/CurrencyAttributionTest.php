<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

class CurrencyRatesPageStub extends Mage_Adminhtml_Block_System_Currency
{
    // The buttons and the rate matrix read the database. The test needs only the template.
    #[\Override]
    protected function _prepareLayout()
    {
        return $this;
    }
}

function renderCurrencyRatesPage(bool $allratestodayActive): string
{
    Mage::getDesign()->setArea('adminhtml')->setPackageName('default')->setTheme('default');
    Mage::app()->getStore()->setConfig('currency/allratestoday/active', $allratestodayActive ? '1' : '0');

    return Mage::app()->getLayout()->createBlock(CurrencyRatesPageStub::class)->toHtml();
}

it('links to AllRatesToday on the rates page when the service is active', function () {
    expect(renderCurrencyRatesPage(true))->toContain('<a href="https://allratestoday.com/"');
});

it('shows no AllRatesToday link when the service is not active', function () {
    expect(renderCurrencyRatesPage(false))->not->toContain('allratestoday.com');
});

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function (): void {
    Mage::unregister('current_promo_quote_rule');
});

afterEach(function (): void {
    Mage::unregister('current_promo_quote_rule');
});

it('renders the grid of the shopping cart price rules after it loads the rule', function (): void {
    $request = new Mage_Core_Controller_Request_Http();
    $request->setActionName('grid');

    $controller = new class ($request, new Mage_Core_Controller_Response_Http()) extends Mage_Adminhtml_Promo_QuoteController {
        public bool $rendered = false;

        #[\Override]
        public function loadLayout($ids = null, $generateBlocks = true, $generateXml = true)
        {
            return $this;
        }

        #[\Override]
        public function renderLayout($output = '')
        {
            $this->rendered = true;
            return $this;
        }
    };

    $controller->gridAction();

    expect($controller->rendered)->toBeTrue()
        ->and(Mage::registry('current_promo_quote_rule'))->toBeInstanceOf(Mage_SalesRule_Model_Rule::class);
});

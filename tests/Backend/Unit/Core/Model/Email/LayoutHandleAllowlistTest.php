<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('{{layout}} renders only declared layout handles', function () {
    beforeEach(function () {
        $this->filter = Mage::getModel('core/email_template_filter');
    });

    it('accepts the handles of the core email templates', function (string $handle) {
        expect($this->filter->isLayoutHandleAllowed($handle))->toBeTrue();
    })->with([
        'sales_email_order_items',
        'sales_email_order_invoice_items',
        'sales_email_order_shipment_items',
        'sales_email_order_creditmemo_items',
    ]);

    it('refuses a handle that no module declares', function (string $handle) {
        expect($this->filter->isLayoutHandleAllowed($handle))->toBeFalse();
    })->with(['', 'default', 'adminhtml_dashboard_index', 'SALES_EMAIL_ORDER_ITEMS', 'sales_email_order']);

    it('accepts a handle that a module declares in its config', function () {
        Mage::getConfig()->setNode(Mage_Core_Model_Email_Template_Filter::XML_PATH_EMAIL_LAYOUT_HANDLES . '/mymodule_email_items', '');

        expect($this->filter->isLayoutHandleAllowed('mymodule_email_items'))->toBeTrue();
    });

    it('renders nothing for a handle that no module declares', function () {
        expect($this->filter->layoutDirective(['{{layout handle="default"}}', 'layout', ' handle="default"']))->toBe('')
            ->and($this->filter->layoutDirective(['{{layout area="adminhtml" handle="adminhtml_dashboard_index"}}', 'layout', ' area="adminhtml" handle="adminhtml_dashboard_index"']))->toBe('')
            ->and($this->filter->layoutDirective(['{{layout}}', 'layout', '']))->toBe('');
    });
});

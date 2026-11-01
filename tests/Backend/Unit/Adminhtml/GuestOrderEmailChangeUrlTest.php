<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * sales.xml uses the order_info block on the order, invoice, shipment and credit memo pages.
 * Only Mage_Adminhtml_Sales_OrderController has the guestOrderEmailChange action.
 */
it('posts the guest email change to the order controller', function (): void {
    $template = file_get_contents(
        Mage::getBaseDir() . '/app/design/adminhtml/default/default/template/sales/order/view/info.phtml',
    );

    expect($template)->toContain("getUrl('adminhtml/sales_order/guestOrderEmailChange')");
    expect(method_exists(Mage_Adminhtml_Sales_OrderController::class, 'guestOrderEmailChangeAction'))->toBeTrue();
});

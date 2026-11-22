<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * GHSA-8m3h-7cgh-jf96: the save validates the layout update of a CMS page, but an update that was saved
 * before a validator rule existed must not run when the page renders.
 */
function cmsLayoutUpdateRender(Mage_Core_Controller_Front_Action $action, Mage_Core_Model_Layout $layout, Mage_Cms_Model_Page $page): Mage_Core_Model_Layout_Update
{
    Mage::helper('cms/page')->renderPageModel($action, $page, false);
    return $layout->getUpdate();
}

function cmsLayoutUpdatePage(string $layoutUpdateXml): Mage_Cms_Model_Page
{
    return Mage::getModel('cms/page')
        ->setTitle('Layout update')
        ->setIdentifier('layout-update-' . uniqid())
        ->setLayoutUpdateXml($layoutUpdateXml);
}

beforeEach(function () {
    $this->layout = Mage::getModel('core/layout');
    $this->action = $this->createMock(Mage_Core_Controller_Front_Action::class);
    $this->action->method('getLayout')->willReturn($this->layout);
    $this->action->method('generateLayoutXml')->willReturnSelf();
});

it('does not apply a stored layout update that the validator refuses', function (string $xml) {
    $update = cmsLayoutUpdateRender($this->action, $this->layout, cmsLayoutUpdatePage($xml));

    expect($update->asString())->not->toContain($xml);
})->with([
    'admin grid' => ['<reference name="content"><block type="adminhtml/sales_order_grid" name="g"/></reference>'],
    'helper argument' => ['<reference name="content"><action method="setText"><text helper="core/string/truncate"/></action></reference>'],
    'invalid XML' => ['<reference name="content">'],
]);

it('applies a stored layout update that the validator accepts', function () {
    $xml = '<reference name="content"><block type="core/text" name="layout.update.text"/></reference>';
    $update = cmsLayoutUpdateRender($this->action, $this->layout, cmsLayoutUpdatePage($xml));

    expect($update->asArray())->toContain($xml);
});

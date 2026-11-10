<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

class TaxNotificationsAllowedSession extends Mage_Admin_Model_Session
{
    public function __construct() {}

    #[\Override]
    public function isAllowed($resource, $privilege = null)
    {
        return true;
    }
}

class TaxNotificationsFactory extends Mage_Core_Model_Factory
{
    #[\Override]
    public function getSingleton($modelClass = '', array $arguments = [])
    {
        return $modelClass === 'admin/session'
            ? new TaxNotificationsAllowedSession()
            : parent::getSingleton($modelClass, $arguments);
    }
}

class TaxNotificationsWrongDiscountBlock extends Mage_Tax_Block_Adminhtml_Notifications
{
    #[\Override]
    public function getWebsitesWithWrongDiscountSettings()
    {
        return true;
    }
}

it('links the tax notices to the tax calculation guide', function () {
    Mage::getDesign()->setArea('adminhtml')->setPackageName('default')->setTheme('default');
    $block = Mage::app()->getLayout()
        ->createBlock(TaxNotificationsWrongDiscountBlock::class, '', ['factory' => new TaxNotificationsFactory()])
        ->setTemplate('tax/notifications.phtml');

    $html = $block->toHtml();

    expect($html)->toContain('<a href="https://mahocommerce.com/user-guide/tax-calculation/">tax calculation guide</a>')
        ->and($html)->not->toContain('>source</a>')
        ->and(file_exists(Mage::getModuleDir('', 'Mage_Tax') . '/docs/user/tax-calculation.md'))->toBeTrue();
});

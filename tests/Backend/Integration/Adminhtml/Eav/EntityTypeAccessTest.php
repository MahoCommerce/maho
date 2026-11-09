<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Helpers\AdminRoleFixture;

uses(Tests\MahoBackendTestCase::class);

/**
 * Return if the shared controller $class allows the current admin to open the pages of $entityType.
 */
function entityTypeAccessAllowed(string $class, string $entityType): bool
{
    return (fn(): bool => (bool) $this->_isAllowed())->call(entityTypeAccessController($class, $entityType));
}

function entityTypeAccessController(string $class, string $entityType): Mage_Core_Controller_Varien_Action
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/index.php/admin/eav_attribute/index', 'GET', ['entity_type' => $entityType]),
    );
    return new $class($request, new Mage_Core_Controller_Response_Http());
}

afterEach(function () {
    if (isset($this->user)) {
        AdminRoleFixture::delete($this->user);
    }
});

it('gives each entity type of System > Attributes its own ACL resource', function () {
    $this->user = AdminRoleFixture::login(['admin/system/attributes/cms_page']);

    expect(entityTypeAccessAllowed(Mage_Adminhtml_Eav_AttributeController::class, 'cms_page'))->toBeTrue()
        ->and(entityTypeAccessAllowed(Mage_Adminhtml_Eav_AttributeController::class, 'customer'))->toBeFalse();
});

it('refuses an entity type that has no admin page settings, even for a full admin', function (string $entityType) {
    $this->user = AdminRoleFixture::login(['all']);

    expect(entityTypeAccessAllowed(Mage_Adminhtml_Eav_AttributeController::class, $entityType))->toBeFalse();
})->with(['catalog_product', 'order', '1', 'not_a_type', '']);

it('opens the one set of CMS pages, and lets only the other entity types add or delete a set', function () {
    $this->user = AdminRoleFixture::login(['all']);
    $canManageSets = fn(string $entityType): bool => (fn(): bool => $this->canManageSets())
        ->call(entityTypeAccessController(Mage_Adminhtml_Eav_SetController::class, $entityType));

    expect(entityTypeAccessAllowed(Mage_Adminhtml_Eav_SetController::class, 'cms_page'))->toBeTrue()
        ->and(entityTypeAccessAllowed(Mage_Adminhtml_Eav_SetController::class, 'customer'))->toBeTrue()
        ->and($canManageSets('cms_page'))->toBeFalse()
        ->and($canManageSets('customer'))->toBeTrue();
});

it('refuses to edit a system attribute on System > Attributes, and keeps it editable on Catalog > Attributes', function () {
    $canEdit = fn(string $class, string $entityType, string $code): bool => (fn(): bool => $this->canEditAttribute(
        Mage::getSingleton('eav/config')->getAttribute($entityType, $code),
    ))->call(entityTypeAccessController($class, $entityType));

    expect($canEdit(Mage_Adminhtml_Eav_AttributeController::class, 'customer', 'firstname'))->toBeFalse()
        ->and($canEdit(Mage_Adminhtml_Eav_AttributeController::class, Mage_Catalog_Model_Category::ENTITY, 'name'))->toBeFalse()
        ->and($canEdit(Mage_Adminhtml_Catalog_Product_AttributeController::class, Mage_Catalog_Model_Product::ENTITY, 'name'))->toBeTrue();
});

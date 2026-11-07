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
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/index.php/admin/eav_attribute/index', 'GET', ['entity_type' => $entityType]),
    );
    $controller = new $class($request, new Mage_Core_Controller_Response_Http());
    return (fn(): bool => (bool) $this->_isAllowed())->call($controller);
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

it('refuses the set pages of an entity type without sets', function () {
    $this->user = AdminRoleFixture::login(['all']);

    expect(entityTypeAccessAllowed(Mage_Adminhtml_Eav_SetController::class, 'cms_page'))->toBeFalse()
        ->and(entityTypeAccessAllowed(Mage_Adminhtml_Eav_SetController::class, 'customer'))->toBeTrue();
});

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Routing\RouteCollectionBuilder;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\RequestContext;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
 * The API role grid and edit form reach the delete action through a plain link
 * (Mage_Adminhtml_Block_Widget_Form_Container's delete button does
 * `setLocation()`), so a POST-only route answers 405 and the role can never be
 * deleted. Every other admin delete action in core is a form-key guarded GET;
 * this one has to match.
 */

function apiRoleRouteMatcher(string $httpMethod): Symfony\Component\Routing\Matcher\CompiledUrlMatcher
{
    $context = new RequestContext();
    $context->setMethod($httpMethod);
    return RouteCollectionBuilder::createMatcher($context);
}

function apiRoleAdminPath(string $action, string $suffix = ''): string
{
    return '/' . RouteCollectionBuilder::getAdminFrontName() . '/apiplatform_role/' . $action . $suffix;
}

describe('API role admin routes', function () {
    it('matches the delete action over GET', function () {
        $params = apiRoleRouteMatcher('GET')->match(apiRoleAdminPath('delete', '/role_id/1'));

        expect($params['_route'])->toBe('maho.apiplatform.adminhtml.apiplatform.role.delete');
        expect($params['_maho_action'])->toBe('deleteAction');
    });

    it('still matches the delete action over POST', function () {
        $params = apiRoleRouteMatcher('POST')->match(apiRoleAdminPath('delete', '/role_id/1'));

        expect($params['_route'])->toBe('maho.apiplatform.adminhtml.apiplatform.role.delete');
    });

    it('keeps the save action restricted to POST', function () {
        // Control: relaxing delete must not relax the form submit endpoint too.
        expect(fn() => apiRoleRouteMatcher('GET')->match(apiRoleAdminPath('save')))
            ->toThrow(MethodNotAllowedException::class);
    });
});

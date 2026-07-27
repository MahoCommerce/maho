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
 * The API role and user grids and edit forms reach the delete action through a
 * plain link (Mage_Adminhtml_Block_Widget_Form_Container's delete button does
 * `setLocation()`), so a POST-only route answers 405 and the record can never be
 * deleted. Every other admin delete action in core is a form-key guarded GET;
 * these have to match.
 */

function apiPlatformRouteMatcher(string $httpMethod): Symfony\Component\Routing\Matcher\CompiledUrlMatcher
{
    $context = new RequestContext();
    $context->setMethod($httpMethod);
    return RouteCollectionBuilder::createMatcher($context);
}

function apiPlatformAdminPath(string $controller, string $action, string $suffix = ''): string
{
    return '/' . RouteCollectionBuilder::getAdminFrontName() . '/apiplatform_' . $controller . '/' . $action . $suffix;
}

dataset('apiPlatformDeleteActions', [
    'role' => ['role', 'role_id', 'maho.apiplatform.adminhtml.apiplatform.role.delete'],
    'user' => ['user', 'user_id', 'maho.apiplatform.adminhtml.apiplatform.user.delete'],
]);

describe('API platform admin routes', function () {
    it('matches the delete action over GET', function (string $controller, string $idParam, string $route) {
        $params = apiPlatformRouteMatcher('GET')
            ->match(apiPlatformAdminPath($controller, 'delete', '/' . $idParam . '/1'));

        expect($params['_route'])->toBe($route);
        expect($params['_maho_action'])->toBe('deleteAction');
    })->with('apiPlatformDeleteActions');

    it('still matches the delete action over POST', function (string $controller, string $idParam, string $route) {
        $params = apiPlatformRouteMatcher('POST')
            ->match(apiPlatformAdminPath($controller, 'delete', '/' . $idParam . '/1'));

        expect($params['_route'])->toBe($route);
    })->with('apiPlatformDeleteActions');

    it('keeps the save action restricted to POST', function (string $controller) {
        // Control: relaxing delete must not relax the form submit endpoint too.
        expect(fn() => apiPlatformRouteMatcher('GET')->match(apiPlatformAdminPath($controller, 'save')))
            ->toThrow(MethodNotAllowedException::class);
    })->with([['role'], ['user']]);
});

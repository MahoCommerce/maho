<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function injectRouterConfig(string $innerXml): void
{
    $extra = new Maho\Simplexml\Config();
    $extra->loadString('<?xml version="1.0"?><config>' . $innerXml . '</config>');
    Mage::getConfig()->extend($extra);
}

describe('Provider_Router top-level shape', function () {
    it('returns xml_routers and attribute_routes sections', function () {
        $result = Mage::getModel('intelligence/provider_router')->getAllRoutes();

        expect($result)->toHaveKeys(['xml_routers', 'attribute_routes']);
        expect($result['xml_routers'])->toBeArray();
        expect($result['attribute_routes'])->toBeArray();
    });
});

describe('Provider_Router attribute routes', function () {
    it('surfaces compiled attribute routes', function () {
        $result = Mage::getModel('intelligence/provider_router')->getAllRoutes();
        expect($result['attribute_routes'])->not->toBeEmpty();
    });

    it('exposes per-route metadata in the expected shape', function () {
        $result = Mage::getModel('intelligence/provider_router')->getAllRoutes();
        $sample = reset($result['attribute_routes']);

        expect($sample)->toHaveKeys([
            'name', 'area', 'path', 'methods', 'class', 'action',
            'module', 'controller_name', 'defaults', 'requirements',
        ]);
        expect($sample['path'])->toBeString()->not->toBeEmpty();
        expect($sample['class'])->toBeString()->not->toBeEmpty();
        expect($sample['action'])->toBeString()->not->toBeEmpty();
        expect($sample['methods'])->toBeArray();
    });

    it('surfaces both adminhtml and frontend routes', function () {
        $result = Mage::getModel('intelligence/provider_router')->getAllRoutes();

        $areas = array_unique(array_column($result['attribute_routes'], 'area'));
        expect($areas)->toContain('adminhtml');
        expect($areas)->toContain('frontend');
    });

    it('sorts attribute routes by name', function () {
        $result = Mage::getModel('intelligence/provider_router')->getAllRoutes();
        $names = array_keys($result['attribute_routes']);
        $sorted = $names;
        sort($sorted, SORT_NATURAL | SORT_FLAG_CASE);

        expect($names)->toBe($sorted);
    });
});

describe('Provider_Router XML routers', function () {
    it('returns an injected XML frontend router with frontName and module', function () {
        injectRouterConfig('
            <frontend>
                <routers>
                    <testrouter>
                        <use>standard</use>
                        <args>
                            <module>My_Test</module>
                            <frontName>testpath</frontName>
                        </args>
                    </testrouter>
                </routers>
            </frontend>
        ');

        $result = Mage::getModel('intelligence/provider_router')->getAllRoutes();

        expect($result['xml_routers'])->toHaveKey('frontend/testrouter');
        $router = $result['xml_routers']['frontend/testrouter'];
        expect($router['name'])->toBe('testrouter');
        expect($router['area'])->toBe('frontend');
        expect($router['type'])->toBe('standard');
        expect($router['module'])->toBe('My_Test');
        expect($router['front_name'])->toBe('testpath');
    });

    it('captures controller-override chains with before/after attributes', function () {
        injectRouterConfig('
            <admin>
                <routers>
                    <adminhtml>
                        <args>
                            <modules>
                                <Custom_Override before="Mage_Adminhtml">Custom_Override</Custom_Override>
                            </modules>
                        </args>
                    </adminhtml>
                </routers>
            </admin>
        ');

        $result = Mage::getModel('intelligence/provider_router')->getAllRoutes();
        $router = $result['xml_routers']['admin/adminhtml'] ?? null;

        expect($router)->not->toBeNull();
        expect($router)->toHaveKey('module_overrides');
        expect($router['module_overrides'])->toHaveKey('Custom_Override');
        expect($router['module_overrides']['Custom_Override']['before'])->toBe('Mage_Adminhtml');
        expect($router['module_overrides']['Custom_Override']['module'])->toBe('Custom_Override');
    });

    it('captures after attribute on override chain entries', function () {
        injectRouterConfig('
            <frontend>
                <routers>
                    <catalog>
                        <args>
                            <modules>
                                <Custom_After after="Mage_Catalog">Custom_After</Custom_After>
                            </modules>
                        </args>
                    </catalog>
                </routers>
            </frontend>
        ');

        $result = Mage::getModel('intelligence/provider_router')->getAllRoutes();
        $entry = $result['xml_routers']['frontend/catalog']['module_overrides']['Custom_After'] ?? null;

        expect($entry)->not->toBeNull();
        expect($entry['after'])->toBe('Mage_Catalog');
    });

    it('omits module_overrides when none are defined', function () {
        injectRouterConfig('
            <frontend>
                <routers>
                    <minimalrouter>
                        <use>standard</use>
                        <args>
                            <module>Min_Mod</module>
                            <frontName>min</frontName>
                        </args>
                    </minimalrouter>
                </routers>
            </frontend>
        ');

        $result = Mage::getModel('intelligence/provider_router')->getAllRoutes();
        expect($result['xml_routers']['frontend/minimalrouter'])->not->toHaveKey('module_overrides');
    });

    it('sorts xml_routers by composite area/name key', function () {
        injectRouterConfig('
            <frontend>
                <routers>
                    <zrouter><use>s</use><args><module>Z</module><frontName>z</frontName></args></zrouter>
                    <arouter><use>s</use><args><module>A</module><frontName>a</frontName></args></arouter>
                </routers>
            </frontend>
        ');

        $result = Mage::getModel('intelligence/provider_router')->getAllRoutes();
        $keys = array_keys($result['xml_routers']);

        $aPos = array_search('frontend/arouter', $keys, true);
        $zPos = array_search('frontend/zrouter', $keys, true);
        expect($aPos)->toBeLessThan($zPos);
    });
});

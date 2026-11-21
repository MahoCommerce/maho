<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api2
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('keeps enabled authentication adapters when another adapter is disabled', function (): void {
    $config = Mage::getConfig();
    $path = Mage_Api2_Helper_Data::XML_PATH_AUTH_ADAPTERS;
    $oauthEnabled = (string) $config->getNode("{$path}/oauth/enabled");

    try {
        $config->setNode("{$path}/oauth/enabled", '0');
        $config->setNode("{$path}/pest/model", 'api2/auth_adapter_oauth');
        $config->setNode("{$path}/pest/label", 'Pest');
        $config->setNode("{$path}/pest/enabled", '1');
        $config->setNode("{$path}/pest/order", '20');

        $adapters = Mage::helper('api2')->getAuthAdapters(true);
        expect($adapters)->toHaveKey('pest')
            ->and($adapters)->not->toHaveKey('oauth');
    } finally {
        $config->setNode("{$path}/oauth/enabled", $oauthEnabled);
        unset($config->getNode($path)->pest);
    }
});

it('sorts an authentication adapter that declares no order', function (): void {
    $config = Mage::getConfig();
    $path = Mage_Api2_Helper_Data::XML_PATH_AUTH_ADAPTERS;
    $oauthEnabled = (string) $config->getNode("{$path}/oauth/enabled");

    try {
        $config->setNode("{$path}/oauth/enabled", '1');
        $config->setNode("{$path}/pest/model", 'api2/auth_adapter_oauth');
        $config->setNode("{$path}/pest/label", 'Pest');
        $config->setNode("{$path}/pest/enabled", '1');

        $adapters = Mage::helper('api2')->getAuthAdapters(true);
        expect(array_keys($adapters))->toBe(['pest', 'oauth']);
    } finally {
        $config->setNode("{$path}/oauth/enabled", $oauthEnabled);
        unset($config->getNode($path)->pest);
    }
});

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $this->helper = Mage::helper('core');
    $this->configPath = 'test/honeypot/enabled_' . uniqid();
});

describe('Honeypot field name', function () {
    it('is stable across calls and uses the install-specific prefix', function () {
        $name = $this->helper->getHoneypotFieldName();
        expect($name)->toStartWith('_h_');
        expect($this->helper->getHoneypotFieldName())->toBe($name);
    });
});

describe('Honeypot trigger detection', function () {
    it('returns false when the config flag is disabled, even if the trap is filled', function () {
        Mage::app()->getStore()->setConfig($this->configPath, '0');
        $body = [$this->helper->getHoneypotFieldName() => 'i-am-a-bot'];

        expect($this->helper->isHoneypotTriggered($body, $this->configPath))->toBeFalse();
    });

    it('returns true when enabled and the trap field is filled', function () {
        Mage::app()->getStore()->setConfig($this->configPath, '1');
        $body = [$this->helper->getHoneypotFieldName() => 'http://spam.example'];

        expect($this->helper->isHoneypotTriggered($body, $this->configPath))->toBeTrue();
    });

    it('returns false when enabled and the trap field is empty or absent', function () {
        Mage::app()->getStore()->setConfig($this->configPath, '1');
        $field = $this->helper->getHoneypotFieldName();

        expect($this->helper->isHoneypotTriggered([$field => ''], $this->configPath))->toBeFalse();
        expect($this->helper->isHoneypotTriggered([], $this->configPath))->toBeFalse();
    });
});

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
});

describe('Honeypot field name', function () {
    it('is stable across calls and uses the install-specific prefix', function () {
        $name = $this->helper->getHoneypotFieldName();
        expect($name)->toStartWith('_h_');
        expect($this->helper->getHoneypotFieldName())->toBe($name);
    });
});

describe('Honeypot trigger detection', function () {
    it('returns true when the trap field is filled', function () {
        $body = [$this->helper->getHoneypotFieldName() => 'http://spam.example'];

        expect($this->helper->isHoneypotTriggered($body))->toBeTrue();
    });

    it('returns false when the trap field is empty, whitespace-only or absent', function () {
        $field = $this->helper->getHoneypotFieldName();

        expect($this->helper->isHoneypotTriggered([$field => '']))->toBeFalse();
        expect($this->helper->isHoneypotTriggered([$field => '   ']))->toBeFalse();
        expect($this->helper->isHoneypotTriggered([]))->toBeFalse();
    });
});

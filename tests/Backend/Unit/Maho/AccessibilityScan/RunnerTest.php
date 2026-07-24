<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Invoke a protected Runner method for testing its pure derivation logic
 */
function a11yRunnerInvoke(string $method, array $tags): ?string
{
    $runner = Mage::getModel('accessibilityscan/runner');
    $reflection = new ReflectionMethod($runner, $method);
    return $reflection->invoke($runner, $tags);
}

describe('AccessibilityScan runner WCAG tag derivation', function () {
    it('derives the strictest conformance level from axe tags', function () {
        expect(a11yRunnerInvoke('wcagLevelFromTags', ['wcag2a']))->toBe('A');
        expect(a11yRunnerInvoke('wcagLevelFromTags', ['wcag2a', 'wcag21aa']))->toBe('AA');
        expect(a11yRunnerInvoke('wcagLevelFromTags', ['wcag22aa']))->toBe('AA');
        expect(a11yRunnerInvoke('wcagLevelFromTags', ['wcag2a', 'wcag2aaa']))->toBe('AAA');
        expect(a11yRunnerInvoke('wcagLevelFromTags', ['cat.semantics', 'best-practice']))->toBeNull();
        expect(a11yRunnerInvoke('wcagLevelFromTags', []))->toBeNull();
        // Success-criteria tags (wcag143) must not be mistaken for level tags
        expect(a11yRunnerInvoke('wcagLevelFromTags', ['wcag143']))->toBeNull();
    });

    it('extracts success criteria references from axe tags', function () {
        expect(a11yRunnerInvoke('wcagCriteriaFromTags', ['wcag143']))->toBe('1.4.3');
        expect(a11yRunnerInvoke('wcagCriteriaFromTags', ['wcag111', 'wcag412']))->toBe('1.1.1, 4.1.2');
        expect(a11yRunnerInvoke('wcagCriteriaFromTags', ['wcag111', 'wcag111']))->toBe('1.1.1');
        // Two-digit third parts keep all digits (2.4.11)
        expect(a11yRunnerInvoke('wcagCriteriaFromTags', ['wcag2411']))->toBe('2.4.11');
        expect(a11yRunnerInvoke('wcagCriteriaFromTags', ['wcag2a', 'cat.forms']))->toBeNull();
        expect(a11yRunnerInvoke('wcagCriteriaFromTags', []))->toBeNull();
    });
});

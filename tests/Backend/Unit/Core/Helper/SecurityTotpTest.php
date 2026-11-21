<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Capture E_USER_DEPRECATED calls, including @-suppressed ones from trigger_deprecation().
 *
 * @template T
 * @param callable(): T $callback
 * @return array{result: T, deprecations: list<string>}
 */
function captureTotpDeprecations(callable $callback): array
{
    $deprecations = [];
    set_error_handler(static function (int $severity, string $message) use (&$deprecations): bool {
        if ($severity === E_USER_DEPRECATED) {
            $deprecations[] = $message;
            return true;
        }
        return false;
    });
    try {
        $result = $callback();
    } finally {
        restore_error_handler();
    }

    return ['result' => $result, 'deprecations' => $deprecations];
}

it('generates a TOTP secret without triggering a clock deprecation', function () {
    $run = captureTotpDeprecations(
        static fn(): string => Mage::helper('core/security')->generateTotpSecret(),
    );

    expect($run['result'])->toBeString()->not->toBe('')
        ->and($run['deprecations'])->toBe([]);
});

it('builds a TOTP QR code without triggering a clock deprecation', function () {
    $secret = Mage::helper('core/security')->generateTotpSecret();
    $run = captureTotpDeprecations(
        static fn(): string => Mage::helper('core/security')->getTotpQrCode('user@example.com', $secret, 'Maho'),
    );

    expect($run['result'])->toContain('<svg')
        ->and($run['deprecations'])->toBe([]);
});

it('verifies a TOTP code without triggering a clock deprecation', function () {
    $secret = Mage::helper('core/security')->generateTotpSecret();
    $totp = \OTPHP\TOTP::createFromSecret($secret, new \Maho\UtcClock());
    if ($totp->expiresIn() < 5) {
        sleep($totp->expiresIn() + 1);
    }
    $validCode = $totp->now();
    $invalidCode = $validCode === '000000' ? '111111' : '000000';

    $validRun = captureTotpDeprecations(
        static fn(): bool => Mage::helper('core/security')->verifyTotpCode($secret, $validCode),
    );
    $invalidRun = captureTotpDeprecations(
        static fn(): bool => Mage::helper('core/security')->verifyTotpCode($secret, $invalidCode),
    );

    expect($validRun['result'])->toBeTrue()
        ->and($validRun['deprecations'])->toBe([])
        ->and($invalidRun['result'])->toBeFalse()
        ->and($invalidRun['deprecations'])->toBe([]);
});

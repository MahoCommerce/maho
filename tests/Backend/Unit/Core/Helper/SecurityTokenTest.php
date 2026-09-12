<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Every emailed or URL-borne secret must come from the CSPRNG. A value derived from the clock
 * or from a seeded generator can be narrowed down by an attacker who knows when it was issued.
 */
describe('Security token generators', function () {
    $generators = [
        'core helper uniqHash' => fn() => Mage::helper('core')->uniqHash(),
        'customer reset token' => fn() => Mage::helper('customer')->generateResetPasswordLinkToken(),
        'customer reset link id' => fn() => Mage::helper('customer')->generateResetPasswordLinkCustomerId(1),
        'customer confirmation key' => fn() => Mage::getModel('customer/customer')->getRandomConfirmationKey(),
        'admin reset token' => fn() => Mage::helper('admin')->generateResetPasswordLinkToken(),
        'magic link token' => fn() => Mage::getModel('customer/customer')->generateMagicLinkToken(),
        'newsletter confirm code' => fn() => Mage::getModel('newsletter/subscriber')->randomSequence(),
        'wishlist sharing code' => fn() => Mage::getModel('wishlist/wishlist')->generateSharingCode()->getSharingCode(),
    ];

    foreach ($generators as $label => $generator) {
        test("the $label is 32 characters long and unique", function () use ($generator) {
            $tokens = [];
            for ($i = 0; $i < 50; $i++) {
                $token = $generator();
                expect($token)->toMatch('/^[a-z0-9]{32}$/');
                $tokens[] = $token;
            }

            expect(array_unique($tokens))->toHaveCount(50);
        });
    }

    $sources = [
        [Mage_Core_Helper_Data::class, 'uniqHash'],
        [Mage_Customer_Helper_Data::class, 'generateResetPasswordLinkCustomerId'],
        [Mage_Customer_Model_Customer::class, 'getRandomConfirmationKey'],
        [Mage_Newsletter_Model_Subscriber::class, 'randomSequence'],
        [Mage_Api_Model_Session::class, 'start'],
        [Maho_CustomerSegmentation_Helper_Coupon::class, 'generateUniqueCouponCode'],
    ];

    foreach ($sources as [$class, $method]) {
        test("$class::$method() does not derive its value from the clock or a seeded generator", function () use ($class, $method) {
            $reflection = new ReflectionMethod($class, $method);
            $lines = file($reflection->getFileName());
            $body = implode('', array_slice(
                $lines,
                $reflection->getStartLine() - 1,
                $reflection->getEndLine() - $reflection->getStartLine() + 1,
            ));

            expect($body)->not->toMatch('/\b(uniqid|mt_rand|rand|md5|microtime|time)\s*\(/')
                ->toContain('random_');
        });
    }
});

<?php

/**
 * A CAPTCHA module for the API that accepts only the token "solved", and records the data of each check.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

namespace Tests\Helpers;

class CaptchaStub extends \Maho_ApiPlatform_Helper_Data
{
    public const SOLVED = 'solved';

    /** @var list<array<array-key, mixed>> */
    public array $checks = [];

    /**
     * Put a new stub in the place of Mage::helper('apiplatform'). Call Mage::unregister('_helper/apiplatform') after the test.
     */
    public static function install(): self
    {
        $stub = new self();
        \Mage::unregister('_helper/apiplatform');
        \Mage::register('_helper/apiplatform', $stub);
        return $stub;
    }

    #[\Override]
    public function verifyCaptcha(array $data): ?string
    {
        $this->checks[] = $data;
        return ($data['captchaToken'] ?? null) === self::SOLVED ? null : 'Incorrect CAPTCHA.';
    }
}

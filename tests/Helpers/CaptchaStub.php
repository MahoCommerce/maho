<?php

/**
 * A CAPTCHA module that accepts only the token "solved", and records the fields of each check.
 *
 * It observes api_verify_captcha in the place of Maho_Captcha, so it answers Mage::helper('core')->assertCaptcha()
 * for every transport. Call uninstall() after the test.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

namespace Tests\Helpers;

class CaptchaStub
{
    public const SOLVED = 'solved';
    public const ERROR = 'Incorrect CAPTCHA.';

    private const SINGLETON = 'tests_captcha_stub';

    /** @var list<array<array-key, mixed>> */
    public array $checks = [];

    public function verify(\Maho\Event\Observer $observer): void
    {
        $fields = (array) $observer->getEvent()->getData('data');
        $this->checks[] = $fields;
        if (($fields['captchaToken'] ?? $fields['maho_captcha'] ?? null) !== self::SOLVED) {
            $observer->getEvent()->getResult()->setVerified(false)->setError(self::ERROR);
        }
    }

    public static function install(): self
    {
        $stub = new self();
        \Mage::register('_singleton/' . self::SINGLETON, $stub, true);
        $observers = [self::SINGLETON => ['type' => 'singleton', 'model' => self::SINGLETON, 'method' => 'verify']];
        (function () use ($observers): void {
            $this->_events['global']['api_verify_captcha'] = ['observers' => $observers];
        })->call(\Mage::app());
        return $stub;
    }

    public static function uninstall(): void
    {
        (function (): void {
            unset($this->_events['global']['api_verify_captcha']);
        })->call(\Mage::app());
        \Mage::unregister('_singleton/' . self::SINGLETON);
    }
}

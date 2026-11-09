<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Core_Exception_Input::withFieldNames()', function () {
    it('gives each listed field its new name and keeps the other fields and the messages', function () {
        $e = (new Mage_Core_Exception_Input())
            ->addError('code', 'Too long.')
            ->addError('name', 'Required.');

        $renamed = $e->withFieldNames(['code' => 'coupon_code']);

        expect($renamed)->not->toBe($e)
            ->and($renamed->getErrors())->toBe([
                ['field' => 'coupon_code', 'message' => 'Too long.'],
                ['field' => 'name', 'message' => 'Required.'],
            ])
            ->and($renamed->getMessage())->toBe($e->getMessage())
            ->and($renamed->getPrevious())->toBe($e);
    });

    it('returns the same exception when no field is listed', function () {
        $e = (new Mage_Core_Exception_Input())->addError('name', 'Required.');

        expect($e->withFieldNames(['code' => 'coupon_code']))->toBe($e);
    });
});

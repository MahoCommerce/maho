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

describe('Mage_Core_Exception_Input::addValidationResult()', function () {
    it('records nothing for true', function () {
        $e = (new Mage_Core_Exception_Input())->addValidationResult(true, 'address');

        expect($e->getErrors())->toBe([]);
    });

    it('records each message of a list as a problem of the given field', function () {
        $e = (new Mage_Core_Exception_Input())->addValidationResult(['Please enter the city.', 'Please enter the street.'], 'address');

        expect($e->getErrors())->toBe([
            ['field' => 'address', 'message' => 'Please enter the city.'],
            ['field' => 'address', 'message' => 'Please enter the street.'],
        ])
            ->and($e->getMessage())->toBe("Please enter the city.\nPlease enter the street.");
    });

    it('records a message with a text key as a problem of that field', function () {
        $e = (new Mage_Core_Exception_Input())->addValidationResult(['title' => 'Required.', 3 => 'Too long.'], 'review');

        expect($e->getErrors())->toBe([
            ['field' => 'title', 'message' => 'Required.'],
            ['field' => 'review', 'message' => 'Too long.'],
        ]);
    });

    it('records a message one time for each field', function () {
        $e = (new Mage_Core_Exception_Input())
            ->addError('address', 'Please enter the city.')
            ->addValidationResult(['Please enter the city.', 'Please enter the city.', 'city' => 'Please enter the city.'], 'address');

        expect($e->getErrors())->toBe([
            ['field' => 'address', 'message' => 'Please enter the city.'],
            ['field' => 'city', 'message' => 'Please enter the city.'],
        ]);
    });

    it('takes the ArrayObject content and the Stringable messages that a model returns', function () {
        $message = new class implements Stringable {
            #[\Override]
            public function __toString(): string
            {
                return 'User Name is required field.';
            }
        };
        $e = (new Mage_Core_Exception_Input())->addValidationResult((array) new ArrayObject([$message]), 'user');

        expect($e->getErrors())->toBe([['field' => 'user', 'message' => 'User Name is required field.']]);
    });

    it('records one general problem for false', function () {
        $e = (new Mage_Core_Exception_Input())->addValidationResult(false, 'address');

        expect($e->getErrors())->toBe([['field' => 'address', 'message' => 'The data is not valid.']]);
    });
});

describe('Mage_Core_Exception_Input::assertValid()', function () {
    it('throws nothing for true', function () {
        Mage_Core_Exception_Input::assertValid(true, 'address');

        expect(true)->toBeTrue();
    });

    it('throws an exception that holds each problem', function () {
        try {
            Mage_Core_Exception_Input::assertValid(['Please enter the city.', 'postcode' => 'Please enter the postcode.'], 'address');
            $this->fail('assertValid() must throw');
        } catch (Mage_Core_Exception_Input $e) {
            expect($e->getErrors())->toBe([
                ['field' => 'address', 'message' => 'Please enter the city.'],
                ['field' => 'postcode', 'message' => 'Please enter the postcode.'],
            ]);
        }
    });
});

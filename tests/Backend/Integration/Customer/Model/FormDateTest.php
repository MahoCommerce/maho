<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('keeps the date of birth when the form does not send it, and clears it when the field is empty', function (array $post, ?string $expected): void {
    $customer = Mage::getModel('customer/customer')->setDob('1990-05-17');
    $form = Mage::getModel('customer/form')
        ->setFormCode('adminhtml_customer')
        ->setEntity($customer)
        ->ignoreInvisible(false);

    $form->compactData($form->extractData($form->prepareRequest($post)));

    expect($customer->getDob())->toBe($expected);
})->with([
    'no field' => [[], '1990-05-17'],
    'an empty field' => [['dob' => ''], null],
]);

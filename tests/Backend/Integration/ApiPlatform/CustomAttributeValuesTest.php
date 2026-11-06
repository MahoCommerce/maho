<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Maho\ApiPlatform\Metadata\CustomAttributeSchema;
use Maho\ApiPlatform\Service\CustomAttributes;

uses(Tests\MahoBackendTestCase::class);

function customAttributeValuesTestAttribute(string $input, string $backendType): Mage_Eav_Model_Entity_Attribute
{
    return Mage::getModel('eav/entity_attribute')->setData([
        'attribute_code' => 'pest_' . $input,
        'source_model' => $input === 'boolean' ? 'eav/entity_attribute_source_boolean' : null,
        'frontend_input' => $input,
        'backend_type' => $backendType,
        'frontend_label' => 'Pest ' . $input,
    ]);
}

it('reads a stored value in the shape that a write takes', function () {
    $read = fn(string $input, string $backend, mixed $value): mixed
        => CustomAttributes::toApiValue(customAttributeValuesTestAttribute($input, $backend), $value);

    expect($read('multiselect', 'text', '12,13'))->toBe([12, 13])
        ->and($read('multiline', 'text', "Dock 4\nGate 2"))->toBe(['Dock 4', 'Gate 2'])
        ->and($read('boolean', 'int', '0'))->toBeFalse()
        ->and($read('boolean', 'int', '1'))->toBeTrue()
        ->and($read('select', 'int', '7'))->toBe(7)
        ->and($read('date', 'datetime', '2026-11-05 00:00:00'))->toBe('2026-11-05')
        ->and($read('text', 'int', '42'))->toBe(42)
        ->and($read('price', 'decimal', '9.5000'))->toBe(9.5)
        ->and($read('text', 'varchar', 'Made in Italy'))->toBe('Made in Italy');
});

it('reads back the values that it wrote', function () {
    $attributes = [
        'pest_multiselect' => customAttributeValuesTestAttribute('multiselect', 'text'),
        'pest_boolean' => customAttributeValuesTestAttribute('boolean', 'int'),
        'pest_text' => customAttributeValuesTestAttribute('text', 'varchar'),
    ];
    $model = new Maho\DataObject();

    CustomAttributes::apply($model, ['pest_boolean' => false, 'pest_text' => 'A'], $attributes);

    expect(CustomAttributes::read($model, $attributes))->toBe(['pest_boolean' => false, 'pest_text' => 'A']);
});

it('describes each value type in the schema', function () {
    $schema = fn(string $input, string $backend): array
        => CustomAttributeSchema::valueSchema(customAttributeValuesTestAttribute($input, $backend));

    expect($schema('boolean', 'int'))->toBe(['type' => ['boolean', 'null']])
        ->and($schema('date', 'datetime'))->toBe(['type' => ['string', 'null'], 'format' => 'date'])
        ->and($schema('multiline', 'text'))->toBe(['type' => ['array', 'null'], 'items' => ['type' => 'string']])
        ->and($schema('text', 'decimal'))->toBe(['type' => ['number', 'null']])
        ->and($schema('textarea', 'text'))->toBe(['type' => ['string', 'null']]);
});

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use Maho\ApiPlatform\Processor;
use Maho\ApiPlatform\Security\ApiUser;
use Tests\Helpers\TransportParity;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/**
 * The default process() of Processor loads, saves and deletes the model itself. An error of the model reaches
 * ApiExceptionListener as it is, so the client gets the status and the fields of the model.
 */
function persistenceProcessor(): Processor
{
    return new class (TransportParity::adminSecurity()) extends Processor {
        protected ?string $modelAlias = 'tax/class';
        protected ?string $entityType = 'tax_class';
        protected ?string $entityLabel = 'tax class';

        #[\Override]
        protected function applyData(object $model, mixed $data, ApiUser $user): void
        {
            $model->setData((array) $data);
        }

        #[\Override]
        protected function buildResponse(object $model, mixed $data): mixed
        {
            return (int) $model->getId();
        }
    };
}

it('gives a 404 for an ID that no model has', function () {
    $result = TransportParity::api(fn() => persistenceProcessor()->process(null, new Delete(), ['id' => 999999999]));

    expect($result['status'])->toBe(404)
        ->and($result['message'])->toBe('Tax class not found');
});

it('gives the status and the fields of a model that refuses the save', function () {
    $result = TransportParity::api(fn() => persistenceProcessor()->process(['class_name' => ' '], new Post()));

    expect($result['status'])->toBe(422)
        ->and($result['message'])->toBe(Mage::helper('tax')->__('Tax class name is required.'))
        ->and($result['fields'])->toBe(['className']);
});

it('saves and deletes the model', function () {
    $id = persistenceProcessor()->process(['class_name' => 'Persistence test', 'class_type' => 'PRODUCT'], new Post());
    persistenceProcessor()->process(null, new Delete(), ['id' => $id]);

    expect($id)->toBeGreaterThan(0)
        ->and(Mage::getModel('tax/class')->load($id)->getId())->toBeNull();
});

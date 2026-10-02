<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\CrudProcessor;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\HttpFoundation\Request;

final class DesignChangeProcessor extends CrudProcessor
{
    /**
     * Keys of the request body. A date sent as null removes that bound, which
     * the null-skipping property mapping cannot express on its own.
     *
     * @var array<string, mixed>
     */
    private array $body = [];

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $request = $context['request'] ?? null;
        $this->body = $request instanceof Request ? $this->parseRequestBody($request) : [];

        return parent::process($data, $operation, $uriVariables, $context);
    }

    #[\Override]
    protected function validate(CrudResource $data, object $model, bool $isNew): void
    {
        /** @var DesignChange $data */
        if ($isNew && $data->storeId === null) {
            throw ValidationException::requiredField('storeId');
        }
        if ($data->storeId !== null && !array_key_exists($data->storeId, \Mage::app()->getStores())) {
            throw ValidationException::invalidValue('storeId', "store view {$data->storeId} does not exist");
        }

        if ($isNew && ($data->design === null || $data->design === '')) {
            throw ValidationException::requiredField('design');
        }
        if ($data->design !== null && !in_array($data->design, Theme::installedIds(), true)) {
            throw ValidationException::invalidValue(
                'design',
                'must be an installed theme as "package/theme"; list them with the themes resource',
            );
        }

        $dateFrom = $this->validDate('dateFrom', $data->dateFrom, $model->getData('date_from'));
        $dateTo = $this->validDate('dateTo', $data->dateTo, $model->getData('date_to'));
        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            throw ValidationException::invalidValue('dateFrom', 'must not be after dateTo');
        }
    }

    #[\Override]
    protected function beforeSave(object $model, CrudResource $data, ApiUser $user): void
    {
        foreach (['dateFrom' => 'date_from', 'dateTo' => 'date_to'] as $property => $column) {
            if (array_key_exists($property, $this->body) && $this->body[$property] === null) {
                $model->setData($column, null);
            }
        }
    }

    /**
     * The date this save ends up with for one bound: the sent value, or the
     * stored value when the body omits the field, or null when the body sends null.
     */
    private function validDate(string $property, ?string $sent, mixed $stored): ?string
    {
        if (array_key_exists($property, $this->body) && $this->body[$property] === null) {
            return null;
        }
        if ($sent === null) {
            return $stored === null || $stored === '' ? null : substr((string) $stored, 0, 10);
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $sent);
        if ($date === false || $date->format('Y-m-d') !== $sent) {
            throw ValidationException::invalidValue($property, 'must be a date as Y-m-d');
        }

        return $sent;
    }
}

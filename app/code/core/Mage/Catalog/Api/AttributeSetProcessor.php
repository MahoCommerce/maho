<?php

/**
 * Give the writes of product attribute sets, their groups and their attributes to the attribute set service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

final class AttributeSetProcessor extends \Maho\ApiPlatform\Processor
{
    private const IGNORED_FIELDS = ['extensions', '@context', '@id', '@type'];

    /** The request body sends the name of a set or of a group as "name", so an error of that name gives this field */
    private const ERROR_FIELDS = ['attribute_set_name' => 'name', 'attribute_group_name' => 'name'];

    public function __construct(
        Security $security,
        private readonly AttributeSetProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?AttributeSet
    {
        $this->requireUser();
        $id = (int) ($uriVariables['id'] ?? 0);
        $name = (string) $operation->getName();

        if ($operation instanceof DeleteOperationInterface) {
            $set = $this->setService()->getById($id, \Mage_Catalog_Model_Product::ENTITY);
            $oldData = $set->getData();
            if ($name === 'attribute_set_attribute_unassign') {
                $this->setService()->unassignAttribute($set, (int) ($uriVariables['attributeId'] ?? 0));
                $this->logApiActivity('attribute_set', 'update', $oldData, $set);
            } else {
                $this->setService()->delete($set);
                $this->logApiActivity('attribute_set', 'delete', $oldData, null);
            }
            return null;
        }

        $body = $this->parseRequestBody($context['request'] ?? null);

        try {
            return match ($name) {
                'attribute_set_group_create' => $this->createGroup($id, $body),
                'attribute_set_attribute_assign' => $this->assignAttribute($id, $body),
                default => $id > 0 ? $this->rename($id, $body) : $this->create($body),
            };
        } catch (\Mage_Core_Exception_Input $e) {
            throw $e->withFieldNames(self::ERROR_FIELDS);
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function create(array $body): AttributeSet
    {
        $this->rejectUnknownFields($body, ['name', 'skeletonId']);
        $set = $this->setService()->newSet(\Mage_Catalog_Model_Product::ENTITY)
            ->setAttributeSetName($this->readString($body, 'name'));
        $this->setService()->save($set, $this->readInteger($body, 'skeletonId'));
        $this->logApiActivity('attribute_set', 'create', null, $set);

        return $this->toDto((int) $set->getId());
    }

    /**
     * @param array<string, mixed> $body
     */
    private function rename(int $id, array $body): AttributeSet
    {
        $this->rejectUnknownFields($body, ['name', 'skeletonId']);
        $set = $this->setService()->getById($id, \Mage_Catalog_Model_Product::ENTITY);
        $oldData = $set->getData();
        $this->setService()->save($set->setAttributeSetName($this->readString($body, 'name')));
        $this->logApiActivity('attribute_set', 'update', $oldData, $set);

        return $this->toDto($id);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function createGroup(int $id, array $body): AttributeSet
    {
        $this->rejectUnknownFields($body, ['name', 'sortOrder']);
        $set = $this->setService()->getById($id, \Mage_Catalog_Model_Product::ENTITY);
        /** @var \Mage_Eav_Model_Entity_Attribute_Group $group */
        $group = \Mage::getModel('eav/entity_attribute_group');
        $group->setAttributeGroupName($this->readString($body, 'name'))
            ->setSortOrder($this->readInteger($body, 'sortOrder') ?? 0);
        $oldData = $set->getData();
        $this->setService()->addGroup($set, $group);
        $this->logApiActivity('attribute_set', 'update', $oldData, $set);

        return $this->toDto($id);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function assignAttribute(int $id, array $body): AttributeSet
    {
        $this->rejectUnknownFields($body, ['attributeId', 'attributeCode', 'groupId', 'groupName', 'sortOrder']);
        $set = $this->setService()->getById($id, \Mage_Catalog_Model_Product::ENTITY);
        $oldData = $set->getData();
        $this->setService()->assignAttribute(
            $set,
            $this->readInteger($body, 'attributeId') ?? $this->readString($body, 'attributeCode'),
            $this->readInteger($body, 'groupId') ?? $this->readString($body, 'groupName'),
            $this->readInteger($body, 'sortOrder') ?? 0,
        );
        $this->logApiActivity('attribute_set', 'update', $oldData, $set);

        return $this->toDto($id);
    }

    private function toDto(int $id): AttributeSet
    {
        return $this->provider->setDto($this->setService()->getById($id, \Mage_Catalog_Model_Product::ENTITY));
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string> $allowed
     */
    private function rejectUnknownFields(array $body, array $allowed): void
    {
        $unknown = array_diff(array_keys($body), $allowed, self::IGNORED_FIELDS);
        if ($unknown !== []) {
            throw new ExtraAttributesException(array_map(strval(...), array_values($unknown)));
        }
    }

    /**
     * Return the integer in $body[$field], or null when the field is missing or null. Refuse another type with a 400.
     *
     * @param array<string, mixed> $body
     */
    private function readInteger(array $body, string $field): ?int
    {
        $value = $body[$field] ?? null;
        if ($value === null) {
            return null;
        }
        $number = is_bool($value) || !is_scalar($value) ? false : filter_var($value, FILTER_VALIDATE_INT);
        return $number === false ? throw self::wrongType($field, 'int', $value) : $number;
    }

    /**
     * Return the text in $body[$field], or null when the field is missing or null. Refuse another type with a 400.
     *
     * @param array<string, mixed> $body
     */
    private function readString(array $body, string $field): ?string
    {
        $value = $body[$field] ?? null;
        if ($value !== null && !is_string($value)) {
            throw self::wrongType($field, 'string', $value);
        }
        return $value;
    }

    private static function wrongType(string $field, string $type, mixed $value): NotNormalizableValueException
    {
        return NotNormalizableValueException::createForUnexpectedDataType(
            sprintf('The type of the "%s" attribute must be "%s", "%s" given.', $field, $type, get_debug_type($value)),
            $value,
            [$type],
            $field,
            true,
        );
    }

    private function setService(): \Mage_Eav_Service_Attribute_Set
    {
        /** @var \Mage_Eav_Service_Attribute_Set */
        return \Mage::getService('eav/attribute_set');
    }
}

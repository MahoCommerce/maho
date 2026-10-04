<?php

/**
 * Create, rename and delete product attribute sets, and manage their groups and attributes.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class AttributeSetProcessor extends \Maho\ApiPlatform\Processor
{
    private const IGNORED_FIELDS = ['extensions', '@context', '@id', '@type'];

    /** @var list<array{field: string, message: string}> */
    private array $errors = [];

    public function __construct(
        Security $security,
        private readonly AttributeSetProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?AttributeSet
    {
        $user = $this->requireUser();
        $id = (int) ($uriVariables['id'] ?? 0);
        $name = (string) $operation->getName();

        if ($operation instanceof DeleteOperationInterface) {
            if ($name === 'attribute_set_attribute_unassign') {
                $this->unassignAttribute($id, (int) ($uriVariables['attributeId'] ?? 0), $user);
            } else {
                $this->delete($id, $user);
            }
            return null;
        }

        $body = $this->parseRequestBody($context['request'] ?? null);

        return match ($name) {
            'attribute_set_group_create' => $this->createGroup($id, $body, $user),
            'attribute_set_attribute_assign' => $this->assignAttribute($id, $body, $user),
            default => $id > 0 ? $this->rename($id, $body, $user) : $this->create($body, $user),
        };
    }

    /**
     * @param array<string, mixed> $body
     */
    private function create(array $body, ApiUser $user): AttributeSet
    {
        $this->errors = [];
        $this->rejectUnknownFields($body, ['name', 'skeletonId']);
        $name = $this->readName($body, true);

        $entityTypeId = $this->productEntityTypeId();
        $skeletonId = $this->defaultSetId();
        if (array_key_exists('skeletonId', $body) && $body['skeletonId'] !== null) {
            $skeletonId = $this->readInteger($body['skeletonId']) ?? 0;
            $skeleton = \Mage::getModel('eav/entity_attribute_set')->load($skeletonId);
            if (!$skeleton->getId() || (int) $skeleton->getEntityTypeId() !== $entityTypeId) {
                $this->addError('skeletonId', 'skeletonId must be the ID of a product attribute set');
            }
        }
        $this->throwErrors();

        /** @var \Mage_Eav_Model_Entity_Attribute_Set $set */
        $set = \Mage::getModel('eav/entity_attribute_set');
        $set->setEntityTypeId($entityTypeId)->setAttributeSetName($name);
        $this->validateName($set);

        $this->safeSave($set, 'create attribute set');
        try {
            $set->initFromSkeleton($skeletonId);
            $set->save();
        } catch (\Throwable $e) {
            // The copy of the skeleton failed, so do not leave an empty set behind
            $set->delete();
            \Mage::logException($e instanceof \Exception ? $e : new \Exception($e->getMessage(), 0, $e));
            throw new UnprocessableEntityHttpException('Failed to copy the skeleton attribute set');
        }
        $this->clearEavCache();
        $this->logApiActivity('attribute_set', 'create', null, $set, $user);

        return $this->provider->setDto($this->loadSet((int) $set->getId()));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function rename(int $id, array $body, ApiUser $user): AttributeSet
    {
        $set = $this->loadSet($id);
        $oldData = $set->getData();

        $this->errors = [];
        $this->rejectUnknownFields($body, ['name', 'skeletonId']);
        $name = $this->readName($body, true);
        $this->throwErrors();

        $set->setAttributeSetName($name);
        $this->validateName($set);
        $this->safeSave($set, 'rename attribute set');
        $this->clearEavCache();
        $this->logApiActivity('attribute_set', 'update', $oldData, $set, $user);

        return $this->provider->setDto($this->loadSet($id));
    }

    private function delete(int $id, ApiUser $user): void
    {
        $set = $this->loadSet($id);
        if ($id === $this->defaultSetId()) {
            throw new UnprocessableEntityHttpException('The default attribute set of products cannot be deleted');
        }
        $oldData = $set->getData();
        $this->safeDelete($set, 'delete attribute set');
        $this->clearEavCache();
        $this->logApiActivity('attribute_set', 'delete', $oldData, null, $user);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function createGroup(int $id, array $body, ApiUser $user): AttributeSet
    {
        $set = $this->loadSet($id);

        $this->errors = [];
        $this->rejectUnknownFields($body, ['name', 'sortOrder']);
        $name = $this->readName($body, true);
        $sortOrder = $this->readSortOrder($body);
        $this->throwErrors();

        /** @var \Mage_Eav_Model_Entity_Attribute_Group $group */
        $group = \Mage::getModel('eav/entity_attribute_group');
        $group->setAttributeSetId($id)->setAttributeGroupName($name)->setSortOrder($sortOrder);
        if ($group->itemExists()) {
            throw self::duplicateName("A group named '{$name}' already exists in this attribute set");
        }

        $oldData = $set->getData();
        $this->safeSave($group, 'create attribute group');
        $this->clearEavCache();
        $this->logApiActivity('attribute_set', 'update', $oldData, $set, $user);

        return $this->provider->setDto($this->loadSet($id));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function assignAttribute(int $id, array $body, ApiUser $user): AttributeSet
    {
        $set = $this->loadSet($id);

        $this->errors = [];
        $this->rejectUnknownFields($body, ['attributeId', 'attributeCode', 'groupId', 'groupName', 'sortOrder']);
        $attribute = $this->readAttribute($body);
        $groupId = $this->readGroupId($id, $body);
        $sortOrder = $this->readSortOrder($body);
        $this->throwErrors();

        $oldData = $set->getData();
        $attribute->setAttributeSetId($id)->setAttributeGroupId($groupId)->setSortOrder($sortOrder);
        try {
            $attribute->getResource()->saveInSetIncluding($attribute);
        } catch (\Throwable $e) {
            \Mage::logException($e instanceof \Exception ? $e : new \Exception($e->getMessage(), 0, $e));
            throw new UnprocessableEntityHttpException('Failed to assign the attribute to the set');
        }
        $this->clearEavCache();
        $this->logApiActivity('attribute_set', 'update', $oldData, $set, $user);

        return $this->provider->setDto($this->loadSet($id));
    }

    private function unassignAttribute(int $id, int $attributeId, ApiUser $user): void
    {
        $set = $this->loadSet($id);

        $resource = \Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $select = $adapter->select()
            ->from($resource->getTableName('eav/entity_attribute'), ['entity_attribute_id'])
            ->where('attribute_set_id = ?', $id)
            ->where('attribute_id = ?', $attributeId);
        $entityAttributeId = (int) $adapter->fetchOne($select);
        if ($entityAttributeId === 0) {
            throw new NotFoundHttpException('The attribute is not in this attribute set');
        }

        /** @var \Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
        $attribute = \Mage::getModel('catalog/resource_eav_attribute')->load($attributeId);
        if (!$attribute->getIsUserDefined()) {
            throw new UnprocessableEntityHttpException('A system attribute cannot be removed from an attribute set');
        }
        if ($attribute->getResource()->isUsedBySuperProducts($attribute, $id)) {
            throw new UnprocessableEntityHttpException('Configurable products of this attribute set use the attribute, so it cannot be removed');
        }

        $oldData = $set->getData();
        try {
            \Mage::getModel('eav/entity_attribute')->setEntityAttributeId($entityAttributeId)->deleteEntity();
        } catch (\Throwable $e) {
            \Mage::logException($e instanceof \Exception ? $e : new \Exception($e->getMessage(), 0, $e));
            throw new UnprocessableEntityHttpException('Failed to remove the attribute from the set');
        }
        $this->clearEavCache();
        $this->logApiActivity('attribute_set', 'update', $oldData, $set, $user);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function readAttribute(array $body): \Mage_Catalog_Model_Resource_Eav_Attribute
    {
        /** @var \Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
        $attribute = \Mage::getModel('catalog/resource_eav_attribute');

        if (array_key_exists('attributeId', $body) && $body['attributeId'] !== null) {
            $attributeId = $this->readInteger($body['attributeId']);
            if ($attributeId === null || $attributeId <= 0) {
                $this->addError('attributeId', 'attributeId must be a positive integer');
                return $attribute;
            }
            $attribute->load($attributeId);
            if (!$attribute->getId() || (int) $attribute->getEntityTypeId() !== $this->productEntityTypeId()) {
                $this->addError('attributeId', "No product attribute has the ID {$attributeId}");
            }
            return $attribute;
        }

        $code = $body['attributeCode'] ?? null;
        if (!is_string($code) || $code === '') {
            $this->addError('attributeId', 'attributeId or attributeCode is required');
            return $attribute;
        }
        $attribute->loadByCode(\Mage_Catalog_Model_Product::ENTITY, $code);
        if (!$attribute->getId()) {
            $this->addError('attributeCode', "No product attribute has the code '{$code}'");
        }
        return $attribute;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function readGroupId(int $setId, array $body): int
    {
        $collection = \Mage::getResourceModel('eav/entity_attribute_group_collection')->setAttributeSetFilter($setId);

        if (array_key_exists('groupId', $body) && $body['groupId'] !== null) {
            $groupId = $this->readInteger($body['groupId']);
            if ($groupId === null || $groupId <= 0) {
                $this->addError('groupId', 'groupId must be a positive integer');
                return 0;
            }
            $collection->addFieldToFilter('attribute_group_id', $groupId);
            if (!$collection->getSize()) {
                $this->addError('groupId', "No group of this attribute set has the ID {$groupId}");
            }
            return $groupId;
        }

        $name = $body['groupName'] ?? null;
        if (!is_string($name) || trim($name) === '') {
            $this->addError('groupId', 'groupId or groupName is required');
            return 0;
        }
        $collection->addFieldToFilter('attribute_group_name', trim($name));
        $group = $collection->getFirstItem();
        if (!$group->getId()) {
            $this->addError('groupName', "No group of this attribute set is named '{$name}'");
            return 0;
        }
        return (int) $group->getId();
    }

    /**
     * @param array<string, mixed> $body
     */
    private function readName(array $body, bool $required): string
    {
        if (!array_key_exists('name', $body)) {
            if ($required) {
                $this->addError('name', 'name is required');
            }
            return '';
        }
        $name = is_string($body['name']) ? trim(\Mage::helper('adminhtml')->stripTags($body['name'])) : '';
        if ($name === '' || mb_strlen($name) > 255) {
            $this->addError('name', 'name must be a text of 1 to 255 characters');
        }
        return $name;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function readSortOrder(array $body): int
    {
        if (!array_key_exists('sortOrder', $body) || $body['sortOrder'] === null) {
            return 0;
        }
        $value = $this->readInteger($body['sortOrder']);
        if ($value === null || $value < 0) {
            $this->addError('sortOrder', 'sortOrder must be an integer of 0 or more');
            return 0;
        }
        return $value;
    }

    /**
     * The set resource refuses a name that another set of the entity type has.
     */
    private function validateName(\Mage_Eav_Model_Entity_Attribute_Set $set): void
    {
        try {
            $set->validate();
        } catch (\Mage_Core_Exception $e) {
            throw self::duplicateName($e->getMessage());
        }
    }

    /**
     * Load a product attribute set or answer 404.
     */
    public function loadSet(int $id): \Mage_Eav_Model_Entity_Attribute_Set
    {
        /** @var \Mage_Eav_Model_Entity_Attribute_Set $set */
        $set = $this->loadOrFail('eav/entity_attribute_set', $id, 'Attribute set not found');
        if ((int) $set->getEntityTypeId() !== $this->productEntityTypeId()) {
            throw new NotFoundHttpException('Attribute set not found');
        }
        return $set;
    }

    private function productEntityTypeId(): int
    {
        return (int) \Mage::getSingleton('eav/config')->getEntityType(\Mage_Catalog_Model_Product::ENTITY)->getId();
    }

    private function defaultSetId(): int
    {
        return (int) \Mage::getSingleton('eav/config')->getEntityType(\Mage_Catalog_Model_Product::ENTITY)->getDefaultAttributeSetId();
    }

    private function clearEavCache(): void
    {
        \Mage::getSingleton('eav/config')->clear();
        \Mage::app()->cleanCache([\Mage_Eav_Model_Entity_Attribute::CACHE_TAG]);
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string> $allowed
     */
    private function rejectUnknownFields(array $body, array $allowed): void
    {
        foreach (array_keys($body) as $key) {
            if (!in_array($key, $allowed, true) && !in_array($key, self::IGNORED_FIELDS, true)) {
                $this->addError((string) $key, 'Unknown field');
            }
        }
    }

    private function readInteger(mixed $value): ?int
    {
        if (is_bool($value) || !is_scalar($value)) {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT);
        return $number === false ? null : $number;
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[] = ['field' => $field, 'message' => $message];
    }

    private static function duplicateName(string $message): ValidationException
    {
        return new ValidationException($message, 'name', 'Duplicate', ['errors' => [['field' => 'name', 'message' => $message]]]);
    }

    private function throwErrors(): void
    {
        if ($this->errors === []) {
            return;
        }
        $first = $this->errors[0];
        throw new ValidationException($first['message'], $first['field'], 'Invalid', ['errors' => $this->errors]);
    }
}

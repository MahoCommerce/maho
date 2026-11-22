<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

/**
 * Entity attribute backend interface
 * Backend is responsible for saving the values of the attribute
 * and performing pre and post actions
 */

interface Mage_Eav_Model_Entity_Attribute_Backend_Interface
{
    public function getTable(): ?string;
    public function isStatic(): bool;
    public function getType(): ?string;
    public function getEntityIdField(): ?string;

    public function setValueId(?int $valueId): static;

    public function getValueId(): ?int;

    /**
     * @param object $object
     * @return mixed
     */
    public function afterLoad($object);

    /**
     * @param object $object
     * @return mixed
     */
    public function beforeSave($object);

    /**
     * @param object $object
     * @return mixed
     */
    public function afterSave($object);

    /**
     * @param object $object
     * @return mixed
     */
    public function beforeDelete($object);

    /**
     * @param object $object
     * @return mixed
     */
    public function afterDelete($object);

    public function getEntityValueId(?\Maho\DataObject $entity): ?int;

    public function setEntityValueId(?\Maho\DataObject $entity, ?int $valueId): static;
}

<?php

/**
 * Load, save and delete websites, store groups and store views for the admin pages, the API and the importer.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use Mage_Core_Model_Store as Store;
use Mage_Core_Model_Store_Group as Group;
use Mage_Core_Model_Website as Website;

class Mage_Core_Service_Store
{
    /**
     * @return list<Store> the active store views, without the admin store
     */
    public function getActiveStores(): array
    {
        return array_values(array_filter(Mage::app()->getStores(), fn(Store $store): bool => (bool) $store->getIsActive()));
    }

    /**
     * Find the active store view with the ID or the code $idOrCode. A code never starts with a digit.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getActiveStore(int|string $idOrCode): Store
    {
        $field = is_int($idOrCode) || ctype_digit($idOrCode) ? 'store_id' : 'code';
        foreach ($this->getActiveStores() as $store) {
            if ((string) $store->getData($field) === (string) $idOrCode) {
                return $store;
            }
        }
        throw new Mage_Core_Exception_NoSuchEntity(is_int($idOrCode)
            ? Mage::helper('core')->__('Store not found')
            : Mage::helper('core')->__("Store with code '%s' not found", $idOrCode));
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getWebsiteById(int $id): Website
    {
        /** @var Website */
        return $this->load('core/website', $id, Mage::app()->getWebsites(true), Mage::helper('core')->__('The website does not exist.'));
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getGroupById(int $id): Group
    {
        /** @var Group */
        return $this->load('core/store_group', $id, Mage::app()->getGroups(true), Mage::helper('core')->__('The store does not exist'));
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getStoreById(int $id): Store
    {
        /** @var Store */
        return $this->load('core/store', $id, Mage::app()->getStores(true), Mage::helper('core')->__("Store view doesn't exist"));
    }

    /**
     * Save $model. Its name loses its HTML tags, and a store view gets the website of its store group.
     * The resource models check the format and the uniqueness of the code.
     */
    public function save(Website|Group|Store $model): Website|Group|Store
    {
        if ($model->getName() !== null) {
            $model->setName(Mage::helper('core')->removeTags($model->getName()));
        }
        $isNew = $model->getId() === null;
        if ($model instanceof Store) {
            $model->setWebsiteId((int) Mage::getModel('core/store_group')->load($model->getGroupId())->getWebsiteId());
        }

        $model->save();

        if ($model instanceof Group) {
            Mage::dispatchEvent('store_group_save', ['group' => $model]);
        } elseif ($model instanceof Store) {
            Mage::app()->reinitStores();
            Mage::dispatchEvent($isNew ? 'store_add' : 'store_edit', ['store' => $model]);
        }
        return $model;
    }

    /**
     * @throws Mage_Core_Exception_Conflict for the default website, store group or store view
     */
    public function delete(Website|Group|Store $model): void
    {
        $this->validateDelete($model);
        $model->delete();
        if ($model instanceof Store) {
            Mage::dispatchEvent('store_delete', ['store' => $model]);
        }
    }

    /**
     * Refuse to delete the default website, the default store group of a website, the default store view
     * of a store group, and the last website.
     *
     * @throws Mage_Core_Exception_Conflict
     */
    public function validateDelete(Website|Group|Store $model): void
    {
        if (!$model->isCanDelete()) {
            throw new Mage_Core_Exception_Conflict(match (true) {
                $model instanceof Website => Mage::helper('core')->__('This website cannot be deleted.'),
                $model instanceof Group => Mage::helper('core')->__('This store cannot be deleted.'),
                default => Mage::helper('core')->__('This store view cannot be deleted.'),
            });
        }
    }

    /**
     * Load a fresh $alias model. The lookup in $known keeps an ID that is out of range for the column away from PostgreSQL.
     *
     * @param array<int|string, mixed> $known
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    private function load(string $alias, int $id, array $known, string $message): Mage_Core_Model_Abstract
    {
        $model = Mage::getModel($alias);
        if (isset($known[$id])) {
            $model->load($id);
        }
        if ($model->getId() === null) {
            throw new Mage_Core_Exception_NoSuchEntity($message);
        }
        return $model;
    }
}

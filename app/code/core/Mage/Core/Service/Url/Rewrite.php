<?php

/**
 * Load, check, save and delete the URL rewrites of the store views, for the admin page and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Service_Url_Rewrite
{
    /** The redirect types: none (an internal rewrite), a 302 redirect, a 301 redirect. */
    public const OPTIONS = ['', 'R', 'RP'];

    public function newRewrite(): Mage_Core_Model_Url_Rewrite
    {
        return Mage::getSingleton('core/factory')->getUrlRewriteInstance();
    }

    /**
     * Load the rewrite $id. With $storeIds, a rewrite of another store view does not exist for the caller.
     *
     * @param list<int>|null $storeIds
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id, ?array $storeIds = null): Mage_Core_Model_Url_Rewrite
    {
        $rewrite = $this->newRewrite();
        try {
            $rewrite->load($id);
        } catch (\Doctrine\DBAL\Exception\DriverException) {
            // An ID that the column cannot hold matches no row
        }
        if (!$rewrite->getId() || ($storeIds !== null && !in_array((int) $rewrite->getStoreId(), $storeIds, true))) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('core')->__('This URL rewrite no longer exists.'));
        }
        return $rewrite;
    }

    /**
     * Refuse a system rewrite. The catalog URL indexer writes a system rewrite again, so a change to it does not last.
     *
     * @throws Mage_Core_Exception_Conflict
     */
    public function assertCustom(Mage_Core_Model_Url_Rewrite $rewrite): void
    {
        if ($rewrite->getIsSystem()) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('core')->__(
                'This is a system URL rewrite. The catalog URL indexer generates and regenerates it, so it cannot be changed through the API. Create a custom rewrite instead.',
            ));
        }
    }

    /**
     * Check $rewrite and save it. The transport puts its input into the rewrite through the typed setters first.
     * A rewrite with a product or a category gets the ID path and the target path of that product or category.
     *
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_Conflict when another rewrite of the store view has the request path or the ID path
     */
    public function save(Mage_Core_Model_Url_Rewrite $rewrite): Mage_Core_Model_Url_Rewrite
    {
        $helper = Mage::helper('core');
        $errors = new Mage_Core_Exception_Input();

        $storeId = $rewrite->getStoreId();
        if ($storeId === null || $storeId === '') {
            $errors->addError('store_id', $helper->__('The store view is required.'));
        } elseif (!array_key_exists((int) $storeId, Mage::app()->getStores())) {
            $errors->addError('store_id', $helper->__('The store view %s does not exist.', $storeId));
        }

        $requestPath = strtolower(trim((string) $rewrite->getRequestPath()));
        $rewrite->setRequestPath($requestPath);
        if ($requestPath === '') {
            $errors->addError('request_path', $helper->__('The request path is required.'));
        } else {
            try {
                Mage::helper('core/url_rewrite')->validateRequestPath($requestPath);
            } catch (Mage_Core_Exception $e) {
                $errors->addError('request_path', $e->getMessage());
            }
        }

        if ($rewrite->getOptions() !== null && !in_array($rewrite->getOptions(), self::OPTIONS, true)) {
            $errors->addError('options', $helper->__('The redirect must be "" (no redirect), "R" (302) or "RP" (301).'));
        }

        $product = $this->loadTarget('catalog/product', $rewrite->getProductId(), 'product_id', $errors);
        $category = $this->loadTarget('catalog/category', $rewrite->getCategoryId(), 'category_id', $errors);
        if ($rewrite->getProductId() === null && $rewrite->getCategoryId() === null) {
            if (trim((string) $rewrite->getIdPath()) === '') {
                $errors->addError('id_path', $helper->__('The ID path is required for a rewrite without a product or a category.'));
            }
            if (trim((string) $rewrite->getTargetPath()) === '') {
                $errors->addError('target_path', $helper->__('The target path is required for a rewrite without a product or a category.'));
            }
        }

        $errors->throwIfErrors();

        if (!$rewrite->getId()) {
            $rewrite->setIsSystem(false);
        }
        if ($product || $category) {
            $this->setCatalogPaths($rewrite, $product, $category);
        }

        $storeId = (int) $storeId;
        if ($this->isInUse('request_path', $requestPath, $rewrite)) {
            throw new Mage_Core_Exception_Conflict(
                $helper->__("A URL rewrite for request path '%s' already exists in store view %s", $requestPath, $storeId),
            );
        }
        if ($this->isInUse('id_path', (string) $rewrite->getIdPath(), $rewrite)) {
            throw new Mage_Core_Exception_Conflict(
                $helper->__("A URL rewrite with ID path '%s' already exists in store view %s", $rewrite->getIdPath(), $storeId),
            );
        }

        $rewrite->save();
        return $rewrite;
    }

    public function delete(Mage_Core_Model_Url_Rewrite $rewrite): void
    {
        $rewrite->delete();
    }

    /**
     * Load the product or the category $id of a rewrite. Add an error to $errors for $field when it does not exist.
     */
    private function loadTarget(string $alias, ?int $id, string $field, Mage_Core_Exception_Input $errors): ?Mage_Core_Model_Abstract
    {
        if ($id === null) {
            return null;
        }
        $model = Mage::getModel($alias)->load($id);
        if (!$model->getId()) {
            $errors->addError($field, $field === 'product_id'
                ? Mage::helper('core')->__('The product %s does not exist.', $id)
                : Mage::helper('core')->__('The category %s does not exist.', $id));
            return null;
        }
        return $model;
    }

    /**
     * Write the ID path and the target path of $product and $category into $rewrite. A redirect goes to the
     * request path of their own rewrite, so the browser shows the URL that the store uses.
     *
     * @throws Mage_Core_Exception_Input when a redirect has no rewrite of the product or the category to go to
     */
    private function setCatalogPaths(
        Mage_Core_Model_Url_Rewrite $rewrite,
        ?Mage_Core_Model_Abstract $product,
        ?Mage_Core_Model_Abstract $category,
    ): void {
        $catalogUrl = Mage::getSingleton('catalog/url');
        $idPath = $catalogUrl->generatePath('id', $product, $category);
        $rewrite->setIdPath($idPath);

        if (in_array($rewrite->getOptions(), ['R', 'RP'], true)) {
            $target = Mage::getResourceModel('catalog/url')->getRewriteByIdPath($idPath, (int) $rewrite->getStoreId());
            if (!$target) {
                throw new Mage_Core_Exception_Input()->addError('product_id', Mage::helper('core')->__(
                    'The product or category has no URL in the chosen store view, so there is no page to redirect to. Check the store view, the product and the category.',
                ));
            }
            if ($target->getId() && (int) $target->getId() !== (int) $rewrite->getId()) {
                $rewrite->setTargetPath($target->getRequestPath());
                return;
            }
        }
        $rewrite->setTargetPath($catalogUrl->generatePath('target', $product, $category));
    }

    /**
     * Return whether another rewrite of the store view of $rewrite has the value $value in $column.
     * The pair of request path and store view is unique, and so is the triple of ID path, type and store view.
     */
    private function isInUse(string $column, string $value, Mage_Core_Model_Url_Rewrite $rewrite): bool
    {
        $collection = $this->newRewrite()->getCollection()
            ->addFieldToFilter($column, $value)
            ->addFieldToFilter('store_id', (int) $rewrite->getStoreId());
        if ($column === 'id_path') {
            $collection->addFieldToFilter('is_system', $rewrite->getIsSystem() ? 1 : 0);
        }
        if ($rewrite->getId()) {
            $collection->addFieldToFilter('url_rewrite_id', ['neq' => (int) $rewrite->getId()]);
        }
        return $collection->getSize() > 0;
    }
}

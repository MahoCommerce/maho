<?php

/**
 * Load, save and delete the URL rewrites of the store views, for the admin page and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Service_Url_Rewrite
{
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
        $rewrite = $this->newRewrite()->load($id);
        if (!$rewrite->getId() || ($storeIds !== null && !in_array((int) $rewrite->getStoreId(), $storeIds, true))) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('core')->__('This URL rewrite no longer exists.'));
        }
        return $rewrite;
    }

    /**
     * Save $rewrite. The transport puts its input into the rewrite through the typed setters first. A new rewrite is
     * custom. Check the store view, the request path, the redirect type, the product and the category. A rewrite
     * without a product or a category needs an ID path and a target path. The request path is stored in lower case.
     * A rewrite with a product or a category gets the target path of that product or category. A system rewrite
     * gets the ID path of its product or category. A custom rewrite keeps its ID path, or it gets a new unique ID
     * path. So a product or a category can have many custom rewrites in a store view.
     * The resource model checks the uniqueness of the request path and the ID path. A system rewrite keeps its
     * store view. The catalog URL indexer writes a system rewrite again when it reindexes its product or
     * category, so a change to a system rewrite lasts until then.
     *
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_Conflict when another rewrite of the store view has the request path or the ID path
     */
    public function save(Mage_Core_Model_Url_Rewrite $rewrite): Mage_Core_Model_Url_Rewrite
    {
        if (!$rewrite->getId()) {
            $rewrite->setIsSystem(false);
        } elseif ($rewrite->getIsSystem() && $rewrite->dataHasChangedFor('store_id')) {
            throw new Mage_Core_Exception_Input()
                ->addError('store_id', Mage::helper('core')->__('The store view of a system URL rewrite cannot be changed.'));
        }
        $product = $rewrite->getProductId() === null ? null : Mage::getModel('catalog/product')->load($rewrite->getProductId());
        $category = $rewrite->getCategoryId() === null ? null : Mage::getModel('catalog/category')->load($rewrite->getCategoryId());
        $exists = ($product === null || $product->getId()) && ($category === null || $category->getId());
        if ($exists && ($product || $category)) {
            $this->setCatalogPaths($rewrite, $product, $category);
        }
        $this->validate($rewrite, $product, $category);

        $rewrite->save();
        return $rewrite;
    }

    /**
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     */
    private function validate(
        Mage_Core_Model_Url_Rewrite $rewrite,
        ?Mage_Core_Model_Abstract $product,
        ?Mage_Core_Model_Abstract $category,
    ): void {
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

        if ($rewrite->getOptions() !== null && !in_array($rewrite->getOptions(), Mage_Core_Model_Url_Rewrite::OPTIONS, true)) {
            $errors->addError('options', $helper->__('The redirect must be "" (no redirect), "R" (302) or "RP" (301).'));
        }

        if ($product !== null && !$product->getId()) {
            $errors->addError('product_id', $helper->__('The product %s does not exist.', $rewrite->getProductId()));
        }
        if ($category !== null && !$category->getId()) {
            $errors->addError('category_id', $helper->__('The category %s does not exist.', $rewrite->getCategoryId()));
        }
        if ($product === null && $category === null) {
            if (trim((string) $rewrite->getIdPath()) === '') {
                $errors->addError('id_path', $helper->__('The ID path is required for a rewrite without a product or a category.'));
            }
            if (trim((string) $rewrite->getTargetPath()) === '') {
                $errors->addError('target_path', $helper->__('The target path is required for a rewrite without a product or a category.'));
            }
        }

        $errors->throwIfErrors();
    }

    public function delete(Mage_Core_Model_Url_Rewrite $rewrite): void
    {
        $rewrite->delete();
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
        $rewrite->setIdPath(
            $rewrite->getIsSystem() ? $idPath : ((string) $rewrite->getOrigData('id_path') ?: $catalogUrl->generateUniqueIdPath()),
        );

        if (in_array($rewrite->getOptions(), ['R', 'RP'], true)) {
            $target = $this->newRewrite()->setStoreId((int) $rewrite->getStoreId())->loadByIdPath($idPath);
            if (!$target->getId()) {
                throw new Mage_Core_Exception_Input()->addError('product_id', Mage::helper('core')->__(
                    'The product or category has no URL in the chosen store view, so there is no page to redirect to. Check the store view, the product and the category.',
                ));
            }
            if ((int) $target->getId() !== (int) $rewrite->getId()) {
                $rewrite->setTargetPath($target->getRequestPath());
                return;
            }
        }
        $rewrite->setTargetPath($catalogUrl->generatePath('target', $product, $category));
    }
}

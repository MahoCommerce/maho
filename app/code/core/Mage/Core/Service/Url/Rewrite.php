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
     * Save $rewrite. The transport puts its input into the rewrite through the typed setters first. A new rewrite is
     * custom. A rewrite with a product or a category gets the ID path and the target path of that product or category.
     * The resource model checks the fields and the uniqueness of the request path and the ID path. A system rewrite
     * keeps its store view. The catalog URL indexer writes a system rewrite again when it reindexes its product or
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
        // A product or a category that does not exist is an error of the resource model
        $exists = ($product === null || $product->getId()) && ($category === null || $category->getId());
        if ($exists && ($product || $category)) {
            $this->setCatalogPaths($rewrite, $product, $category);
        }

        $rewrite->save();
        return $rewrite;
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
}

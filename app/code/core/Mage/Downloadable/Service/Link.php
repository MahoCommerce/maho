<?php

/**
 * Load, save and delete the links of a downloadable product.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

declare(strict_types=1);

use Mage_Catalog_Model_Product as Product;
use Mage_Downloadable_Model_Link as Link;

/**
 * A write saves the product with all its links in the format of the admin product save, so the downloadable product
 * type escapes the URLs, saves the price of each website and sets links_exist, and the link model checks each link.
 * The write uses the admin scope.
 */
class Mage_Downloadable_Service_Link
{
    private const FIELDS = ['title', 'price', 'number_of_downloads', 'is_shareable', 'sort_order', 'link_url', 'sample_type', 'sample_url'];

    /**
     * @throws Mage_Core_Exception_Input when $linkId is not a positive ID
     * @throws Mage_Core_Exception_NoSuchEntity when $product has no link $linkId
     */
    public function getById(Product $product, int $linkId): Link
    {
        if ($linkId <= 0) {
            throw new Mage_Core_Exception_Input()->addError('link_id', Mage::helper('downloadable')->__('linkId is required'));
        }
        /** @var Link $link */
        $link = Mage::getResourceModel('downloadable/link_collection')
            ->addProductToFilter($product->getId())
            ->addFieldToFilter('main_table.link_id', $linkId)
            ->addTitleToResult()
            ->addPriceToResult(0)
            ->getFirstItem();
        if (!$link->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('downloadable')->__('Downloadable link not found'));
        }
        return $link;
    }

    /**
     * Save $link, a new link of $product or one from getById().
     *
     * @throws Mage_Core_Exception_Input for an invalid title, link type, URL or shareable setting
     * @throws Mage_Core_Exception for a URL whose domain is not allowed
     */
    public function save(Product $product, Link $link): Link
    {
        $before = $this->getLinkIds($product);
        $this->write($product, $link, false);
        $linkId = (int) $link->getId() ?: (int) max(array_diff($this->getLinkIds($product), $before) ?: [0]);
        return $this->getById($product, $linkId);
    }

    public function delete(Product $product, Link $link): void
    {
        $this->write($product, $link, true);
    }

    private function write(Product $product, Link $target, bool $delete): void
    {
        /** @var Product $product */
        $product = Mage::getModel('catalog/product')->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID)->load($product->getId());
        /** @var Mage_Downloadable_Model_Product_Type $typeInstance */
        $typeInstance = $product->getTypeInstance(true);
        $items = [];
        foreach ($typeInstance->getLinks($product) as $link) {
            if ((int) $link->getId() !== (int) $target->getId()) {
                $items[] = $this->toArray($link);
            }
        }
        $items[] = ['is_delete' => $delete ? '1' : ''] + $this->toArray($target);

        $product->setDownloadableData(['link' => $items]);
        $product->save();
    }

    /**
     * @return array<string, mixed> $link in the format of the admin product save
     */
    private function toArray(Link $link): array
    {
        $item = array_intersect_key($link->getData(), array_flip(self::FIELDS));
        $item['link_id'] = $link->getId() ? (string) $link->getId() : '';
        $item['type'] = (string) $link->getLinkType();
        $item['is_delete'] = '';
        $helper = Mage::helper('core');
        if ($link->getLinkFile()) {
            $item['file'] = $helper->jsonEncode([['file' => $link->getLinkFile(), 'status' => 'old']]);
        }
        if ($link->getSampleType()) {
            $item['sample'] = [
                'type' => $link->getSampleType(),
                'url' => (string) $link->getSampleUrl(),
                'file' => $helper->jsonEncode($link->getSampleFile() ? [['file' => $link->getSampleFile(), 'status' => 'old']] : []),
            ];
        }
        return $item;
    }

    /**
     * @return list<int>
     */
    private function getLinkIds(Product $product): array
    {
        return array_map(intval(...), Mage::getResourceModel('downloadable/link_collection')->addProductToFilter($product->getId())->getAllIds());
    }
}

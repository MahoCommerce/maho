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
     * @throws Mage_Core_Exception_Input for a negative price, or an invalid title, link type, URL or shareable setting
     * @throws Mage_Core_Exception for a URL whose domain is not allowed
     */
    public function save(Product $product, Link $link): Link
    {
        if ((float) $link->getPrice() < 0) {
            throw new Mage_Core_Exception_Input()->addError('price', Mage::helper('downloadable')->__('Price must not be negative'));
        }
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
        $product = Mage::getService('catalog/product')->getById((int) $product->getId());
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
        Mage::getService('catalog/product')->savePart($product);
    }

    /**
     * @return array<string, mixed> $link in the format of the admin product save
     */
    private function toArray(Link $link): array
    {
        $item = array_intersect_key($link->getData(), array_flip(self::FIELDS));
        if (isset($item['link_url'])) {
            $item['link_url'] = $this->getUrlForSave($link, 'link_url');
        }
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
                'url' => $this->getUrlForSave($link, 'sample_url'),
                'file' => $helper->jsonEncode($link->getSampleFile() ? [['file' => $link->getSampleFile(), 'status' => 'old']] : []),
            ];
        }
        return $item;
    }

    /**
     * Return the URL in $field of $link as the admin form posts it. The product type escapes each URL of a save,
     * so a URL that comes from the database loses its escape here. A URL that the caller wrote stays as it is.
     */
    private function getUrlForSave(Link $link, string $field): string
    {
        $url = (string) $link->getData($field);
        if ($link->getId() && $url === (string) $link->getOrigData($field)) {
            return htmlspecialchars_decode($url, ENT_QUOTES | ENT_HTML5);
        }
        return $url;
    }

    /**
     * @return list<int>
     */
    private function getLinkIds(Product $product): array
    {
        return array_map(intval(...), Mage::getResourceModel('downloadable/link_collection')->addProductToFilter($product->getId())->getAllIds());
    }
}

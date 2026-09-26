<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

/**
 * Search Catalog Model
 *
 * @package    Mage_Adminhtml
 *
 * @method bool hasLimit()
 * @method bool hasQuery()
 * @method bool hasStart()
 */
class Mage_Adminhtml_Model_Search_Catalog extends \Maho\DataObject
{
    /**
     * Load search results
     *
     * @return $this
     */
    public function load()
    {
        $arr = [];

        if (!$this->hasStart() || !$this->hasLimit() || !$this->hasQuery()) {
            $this->setResults($arr);
            return $this;
        }

        // A part of the name or of the SKU matches, like the filters of the admin grids.
        // The storefront search collection matches only the start of a value.
        $like = Mage::getResourceHelper('core')->addLikeEscape($this->getQuery(), ['position' => 'any']);
        $collection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('description')
            ->addAttributeToFilter([
                ['attribute' => 'name', 'like' => $like],
                ['attribute' => 'sku', 'like' => $like],
            ])
            ->setOrder('name', 'asc')
            ->setCurPage($this->getStart())
            ->setPageSize($this->getLimit())
            ->load();

        foreach ($collection as $product) {
            $description = strip_tags((string) $product->getDescription());
            $arr[] = [
                'id'            => 'product/1/' . $product->getId(),
                'type'          => Mage::helper('adminhtml')->__('Product'),
                'name'          => $product->getName(),
                'description'   => Mage::helper('core/string')->substr($description, 0, 30),
                'url' => Mage::helper('adminhtml')->getUrl('*/catalog_product/edit', ['id' => $product->getId()]),
            ];
        }

        $this->setResults($arr);

        return $this;
    }

    public function getLimit(): ?int
    {
        $value = $this->getData('limit');
        return $value === null ? null : (int) $value;
    }

    public function getQuery(): ?string
    {
        $value = $this->getData('query');
        return $value === null ? null : (string) $value;
    }

    public function setResults(?array $value): static
    {
        return $this->setData('results', $value);
    }

    public function getStart(): ?int
    {
        $value = $this->getData('start');
        return $value === null ? null : (int) $value;
    }

}

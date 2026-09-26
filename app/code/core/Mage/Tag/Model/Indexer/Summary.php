<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tag
 */

declare(strict_types=1);

/**
 * @method Mage_Tag_Model_Resource_Indexer_Summary _getResource()
 * @method Mage_Tag_Model_Resource_Indexer_Summary getResource()
 */
class Mage_Tag_Model_Indexer_Summary extends Mage_Index_Model_Indexer_Abstract
{
    /**
     * @var array
     */
    #[\Override]
    protected $_matchedEntities = [
        Mage_Catalog_Model_Product::ENTITY => [
            Mage_Index_Model_Event::TYPE_SAVE,
            Mage_Index_Model_Event::TYPE_DELETE,
            Mage_Index_Model_Event::TYPE_MASS_ACTION,
        ],
        Mage_Tag_Model_Tag::ENTITY => [
            Mage_Index_Model_Event::TYPE_SAVE,
        ],
        Mage_Tag_Model_Tag_Relation::ENTITY => [
            Mage_Index_Model_Event::TYPE_SAVE,
        ],
    ];

    #[\Override]
    protected function _construct()
    {
        $this->_init('tag/indexer_summary');
    }

    /**
     * Retrieve Indexer name
     *
     * @return string
     */
    #[\Override]
    public function getName()
    {
        return Mage::helper('tag')->__('Tag Aggregation Data');
    }

    /**
     * Retrieve Indexer description
     *
     * @return string
     */
    #[\Override]
    public function getDescription()
    {
        return Mage::helper('tag')->__('Rebuild Tag aggregation data');
    }

    /**
     * Retrieve attribute list that has an effect on tags
     *
     * @return array
     */
    protected function _getProductAttributesDependOn()
    {
        return [
            'visibility',
            'status',
            'website_ids',
        ];
    }

    /**
     * Register data required by process in event object
     */
    #[\Override]
    protected function _registerEvent(Mage_Index_Model_Event $event)
    {
        if ($event->getEntity() == Mage_Catalog_Model_Product::ENTITY) {
            $this->_registerCatalogProduct($event);
        } elseif ($event->getEntity() == Mage_Tag_Model_Tag::ENTITY) {
            $this->_registerTag($event);
        } elseif ($event->getEntity() == Mage_Tag_Model_Tag_Relation::ENTITY) {
            $this->_registerTagRelation($event);
        }
    }

    /**
     * Register data required by catalog product save process
     */
    protected function _registerCatalogProductSaveEvent(Mage_Index_Model_Event $event)
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = $event->getDataObject();
        $reindexTag = $product->getForceReindexRequired();

        foreach ($this->_getProductAttributesDependOn() as $attributeCode) {
            $reindexTag = $reindexTag || $product->dataHasChangedFor($attributeCode);
        }

        if (!$product->isObjectNew() && $reindexTag) {
            $event->addNewData('tag_reindex_required', true);
        }
    }

    /**
     * Register data required by catalog product delete process
     */
    protected function _registerCatalogProductDeleteEvent(Mage_Index_Model_Event $event)
    {
        $tagIds = Mage::getModel('tag/tag_relation')
            ->setProductId($event->getEntityPk())
            ->getRelatedTagIds();
        if ($tagIds) {
            $event->addNewData('tag_reindex_tag_ids', $tagIds);
        }
    }

    /**
     * Register data required by catalog product massaction process
     */
    protected function _registerCatalogProductMassActionEvent(Mage_Index_Model_Event $event)
    {
        $actionObject = $event->getDataObject();
        $attributes   = $this->_getProductAttributesDependOn();
        $reindexTags  = false;

        // check if attributes changed
        $attrData = $actionObject->getAttributesData();
        if (is_array($attrData)) {
            foreach ($attributes as $attributeCode) {
                if (array_key_exists((string) $attributeCode, $attrData)) {
                    $reindexTags = true;
                    break;
                }
            }
        }

        // check changed websites
        if ($actionObject->getWebsiteIds()) {
            $reindexTags = true;
        }

        // register affected tags
        if ($reindexTags) {
            $tagIds = Mage::getModel('tag/tag_relation')
                ->setProductId($actionObject->getProductIds())
                ->getRelatedTagIds();
            if ($tagIds) {
                $event->addNewData('tag_reindex_tag_ids', $tagIds);
            }
        }
    }

    protected function _registerCatalogProduct(Mage_Index_Model_Event $event)
    {
        switch ($event->getType()) {
            case Mage_Index_Model_Event::TYPE_SAVE:
                $this->_registerCatalogProductSaveEvent($event);
                break;

            case Mage_Index_Model_Event::TYPE_DELETE:
                $this->_registerCatalogProductDeleteEvent($event);
                break;

            case Mage_Index_Model_Event::TYPE_MASS_ACTION:
                $this->_registerCatalogProductMassActionEvent($event);
                break;
        }
    }

    protected function _registerTag(Mage_Index_Model_Event $event)
    {
        if ($event->getType() == Mage_Index_Model_Event::TYPE_SAVE) {
            $event->addNewData('tag_reindex_tag_id', $event->getEntityPk());
        }
    }

    protected function _registerTagRelation(Mage_Index_Model_Event $event)
    {
        if ($event->getType() == Mage_Index_Model_Event::TYPE_SAVE) {
            $event->addNewData('tag_reindex_tag_id', $event->getDataObject()->getTagId());
        }
    }

    /**
     * Process event
     */
    #[\Override]
    protected function _processEvent(Mage_Index_Model_Event $event)
    {
        $this->callEventHandler($event);
    }

    public function getBasePopularity(): ?int
    {
        $value = $this->getData('base_popularity');
        return $value === null ? null : (int) $value;
    }

    public function setBasePopularity(?int $value): static
    {
        return $this->setData('base_popularity', $value);
    }

    public function getCustomers(): ?int
    {
        $value = $this->getData('customers');
        return $value === null ? null : (int) $value;
    }

    public function setCustomers(?int $value): static
    {
        return $this->setData('customers', $value);
    }

    public function getHistoricalUses(): ?int
    {
        $value = $this->getData('historical_uses');
        return $value === null ? null : (int) $value;
    }

    public function setHistoricalUses(?int $value): static
    {
        return $this->setData('historical_uses', $value);
    }

    public function getPopularity(): ?int
    {
        $value = $this->getData('popularity');
        return $value === null ? null : (int) $value;
    }

    public function setPopularity(?int $value): static
    {
        return $this->setData('popularity', $value);
    }

    public function getProducts(): ?int
    {
        $value = $this->getData('products');
        return $value === null ? null : (int) $value;
    }

    public function setProducts(?int $value): static
    {
        return $this->setData('products', $value);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getTagId(): ?int
    {
        $value = $this->getData('tag_id');
        return $value === null ? null : (int) $value;
    }

    public function setTagId(?int $value): static
    {
        return $this->setData('tag_id', $value);
    }

    public function getUses(): ?int
    {
        $value = $this->getData('uses');
        return $value === null ? null : (int) $value;
    }

    public function setUses(?int $value): static
    {
        return $this->setData('uses', $value);
    }

}

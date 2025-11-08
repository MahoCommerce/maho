<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * Catalog Link Rule Model
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 *
 * @method int getRuleId()
 * @method $this setRuleId(int $value)
 * @method string getName()
 * @method $this setName(string $value)
 * @method string getDescription()
 * @method $this setDescription(string $value)
 * @method int getLinkTypeId()
 * @method $this setLinkTypeId(int $value)
 * @method int getIsActive()
 * @method $this setIsActive(int $value)
 * @method int getPriority()
 * @method $this setPriority(int $value)
 * @method string getSortOrder()
 * @method $this setSortOrder(string $value)
 * @method int|null getMaxLinks()
 * @method $this setMaxLinks(int|null $value)
 * @method string|null getFromDate()
 * @method $this setFromDate(string|null $value)
 * @method string|null getToDate()
 * @method $this setToDate(string|null $value)
 * @method string getConditionsSerialized()
 * @method $this setConditionsSerialized(string $value)
 * @method string getTargetConditionsSerialized()
 * @method $this setTargetConditionsSerialized(string $value)
 */
class Maho_CatalogLinkRule_Model_Rule extends Mage_Rule_Model_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('cataloglinkrule/rule');
    }

    /**
     * Get rule conditions instance
     */
    #[\Override]
    public function getConditionsInstance()
    {
        return Mage::getModel('cataloglinkrule/rule_condition_combine');
    }

    /**
     * Get rule actions instance (used for target conditions)
     */
    #[\Override]
    public function getActionsInstance()
    {
        return Mage::getModel('cataloglinkrule/rule_target_combine');
    }

    /**
     * Load post data
     *
     * @return $this
     */
    #[\Override]
    public function loadPost(array $data): self
    {
        $this->addData($data);

        if (isset($data['rule'])) {
            $this->getConditions()->loadArray($data['rule']['conditions']);
        }

        if (isset($data['target'])) {
            $this->getActions()->loadArray($data['target']['conditions']);
        }

        return $this;
    }

    /**
     * Get matching source product IDs
     */
    public function getMatchingSourceProductIds(): array
    {
        $productCollection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);

        /** @var Maho_CatalogLinkRule_Model_Rule_Condition_Combine $conditions */
        $conditions = $this->getConditions();
        $conditions->collectValidatedAttributes($productCollection);

        $productIds = [];
        foreach ($productCollection as $product) {
            if ($conditions->validate($product)) {
                $productIds[] = (int) $product->getId();
            }
        }

        return $productIds;
    }

    /**
     * Get matching target product IDs with sorting for a specific source product
     */
    public function getMatchingTargetProductIds(?Mage_Catalog_Model_Product $sourceProduct = null): array
    {
        $productCollection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect(['name', 'price', 'created_at'])
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);

        /** @var Maho_CatalogLinkRule_Model_Rule_Target_Combine $actions */
        $actions = $this->getActions();
        $actions->collectValidatedAttributes($productCollection);

        // Set source product for source-matching conditions
        if ($sourceProduct) {
            $this->setSourceProduct($sourceProduct);
        }

        // Apply sorting
        switch ($this->getSortOrder()) {
            case 'price_asc':
                $productCollection->addAttributeToSort('price', 'ASC');
                break;
            case 'price_desc':
                $productCollection->addAttributeToSort('price', 'DESC');
                break;
            case 'name_asc':
                $productCollection->addAttributeToSort('name', 'ASC');
                break;
            case 'name_desc':
                $productCollection->addAttributeToSort('name', 'DESC');
                break;
            case 'newest':
                $productCollection->addAttributeToSort('created_at', 'DESC');
                break;
            case 'oldest':
                $productCollection->addAttributeToSort('created_at', 'ASC');
                break;
            case 'random':
                // For better performance on large catalogs, shuffle in PHP
                $productIds = [];
                foreach ($productCollection as $product) {
                    if ($actions->validate($product)) {
                        $productIds[] = (int) $product->getId();
                    }
                }
                shuffle($productIds);
                return $productIds;
            case 'position':
            default:
                // Natural order (entity_id)
                $productCollection->addAttributeToSort('entity_id', 'ASC');
                break;
        }

        $productIds = [];
        foreach ($productCollection as $product) {
            if ($actions->validate($product)) {
                $productIds[] = (int) $product->getId();
            }
        }

        return $productIds;
    }
}

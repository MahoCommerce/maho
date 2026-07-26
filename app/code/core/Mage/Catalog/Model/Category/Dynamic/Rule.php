<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

/**
 * @method int getRuleId()
 * @method $this setRuleId(int $value)
 * @method int getCategoryId()
 * @method $this setCategoryId(int $value)
 * @method string getConditionsSerialized()
 * @method $this setConditionsSerialized(string $value)
 * @method string|null getParentResolution()
 * @method $this setParentResolution(string $value)
 * @method int getIsActive()
 * @method $this setIsActive(int $value)
 * @method string getCreatedAt()
 * @method $this setCreatedAt(string $value)
 * @method string getUpdatedAt()
 * @method $this setUpdatedAt(string $value)
 */

class Mage_Catalog_Model_Category_Dynamic_Rule extends Mage_Rule_Model_Abstract
{
    /** Matched products go into the category as they are. */
    public const PARENT_RESOLUTION_NONE = 'none';

    /** Matched products are swapped for their parents; matched products without a parent stay. */
    public const PARENT_RESOLUTION_KEEP_ORPHANS = 'keep_orphans';

    /** Matched products are swapped for their parents; matched simples without a parent are dropped. */
    public const PARENT_RESOLUTION_DISCARD_ORPHANS = 'discard_orphans';

    protected ?array $_productIds = null;
    protected int|array|null $_productsFilter = null;
    protected ?Mage_Catalog_Model_Category $_category = null;

    /**
     * Options for the "When products match" rule setting.
     */
    public static function getParentResolutionOptions(): array
    {
        return [
            self::PARENT_RESOLUTION_NONE => Mage::helper('catalog')->__('Do not replace'),
            self::PARENT_RESOLUTION_KEEP_ORPHANS => Mage::helper('catalog')->__('Replace with parent products, keep products that have no parent'),
            self::PARENT_RESOLUTION_DISCARD_ORPHANS => Mage::helper('catalog')->__('Replace with parent products, discard products that have no parent'),
        ];
    }

    #[\Override]
    protected function _construct(): void
    {
        parent::_construct();
        $this->_init('catalog/category_dynamic_rule');
        $this->setIdFieldName('rule_id');
    }

    #[\Override]
    public function getConditions(): ?Mage_Rule_Model_Condition_Combine
    {
        if (!$this->_conditions) {
            $this->_resetConditions();

            if ($this->getConditionsSerialized()) {
                $conditions = $this->_decodeRuleData($this->getConditionsSerialized(), 'conditions_serialized');
                if (is_array($conditions) && !empty($conditions)) {
                    $this->_conditions->setConditions([])->loadArray($conditions);
                }
            }
        }

        return $this->_conditions;
    }

    #[\Override]
    protected function _resetConditions(mixed $conditions = null): self
    {
        if (is_null($conditions)) {
            $conditions = $this->getConditionsInstance();
        }
        $conditions->setRule($this)->setId('1')->setPrefix('conditions');
        $this->setConditions($conditions);

        return $this;
    }

    #[\Override]
    public function getConditionsInstance(): Mage_Catalog_Model_Rule_Condition_Combine
    {
        return Mage::getModel('catalog/category_dynamic_rule_condition_combine');
    }

    #[\Override]
    public function getActionsInstance(): Mage_Rule_Model_Action_Collection
    {
        return Mage::getModel('rule/action_collection');
    }

    #[\Override]
    protected function _beforeSave(): self
    {
        // loadPost() writes every posted key straight onto the model, so the column is normalised here
        // rather than at the call site: whichever path set it, only a known option reaches the table
        if (!array_key_exists((string) $this->getParentResolution(), self::getParentResolutionOptions())) {
            $this->setParentResolution(self::PARENT_RESOLUTION_NONE);
        }

        // Encode conditions as JSON
        if ($this->getConditions()) {
            try {
                $this->setConditionsSerialized(Mage::helper('core')->jsonEncode($this->getConditions()->asArray()));
            } catch (\JsonException $e) {
                Mage::logException($e);
                throw $e;
            }
            $this->unsConditions();
        }

        parent::_beforeSave();
        return $this;
    }

    #[\Override]
    public function setConditions(mixed $conditions): self
    {
        $this->_conditions = $conditions;
        return $this;
    }

    #[\Override]
    public function loadPost(array $arr): self
    {
        $arr = $this->_convertFlatToRecursive($arr);
        if (isset($arr['conditions'])) {
            $this->getConditions()->loadArray($arr['conditions'][1]);
        }
        return $this;
    }

    public function asArray(array $arrAttributes = []): array
    {
        $out = [];
        $out['conditions'] = $this->getConditions()->asArray();

        return $out;
    }

    public function loadArray(array $rule): self
    {
        if (!empty($rule['conditions']) && is_array($rule['conditions'])) {
            $this->getConditions()->loadArray($rule['conditions']);
        }
        return $this;
    }

    #[\Override]
    protected function _afterLoad(): self
    {
        parent::_afterLoad();

        // Initialize conditions from serialized data
        if ($this->getConditionsSerialized()) {
            $conditions = $this->_decodeRuleData($this->getConditionsSerialized(), 'conditions_serialized');
            if (is_array($conditions) && !empty($conditions)) {
                // Reset and reload conditions
                $this->_conditions = $this->getConditionsInstance();
                $this->_conditions->setRule($this)->setId('1')->setPrefix('conditions');
                $this->_conditions->setConditions([])->loadArray($conditions);
            }
        }

        return $this;
    }

    #[\Override]
    public function validate(\Maho\DataObject $object): bool
    {
        $conditions = $this->getConditions();
        if (!$conditions || !$conditions->getConditions()) {
            // No conditions means NO match, not ALL match
            return false;
        }

        return $conditions->validate($object);
    }

    public function getMatchingProductIds(): array
    {
        if ($this->_productIds === null) {
            $this->_productIds = [];
            $this->setCollectedAttributes([]);

            // Check if we have conditions
            $conditions = $this->getConditions();
            if (!$conditions || !$conditions->getConditions()) {
                return $this->_productIds;
            }

            /** @var Mage_Catalog_Model_Resource_Product_Collection $productCollection */
            $productCollection = Mage::getResourceModel('catalog/product_collection');

            if ($this->_productsFilter) {
                $productCollection->addIdFilter($this->_productsFilter);
            }

            if (method_exists($this->getConditions(), 'collectValidatedAttributes')) {
                $this->getConditions()->collectValidatedAttributes($productCollection);
            }

            Mage::getSingleton('core/resource_iterator')->walk(
                $productCollection->getSelect(),
                [[$this, 'callbackValidateProduct']],
                [
                    'attributes' => $this->getCollectedAttributes(),
                    'product'    => Mage::getModel('catalog/product'),
                ],
            );
        }

        return $this->_productIds;
    }

    /**
     * Product IDs this rule wants in the category: the matched products, after parent resolution.
     */
    public function getResolvedProductIds(): array
    {
        return $this->resolveParents($this->getMatchingProductIds());
    }

    /**
     * Map matched products onto the products that should actually be assigned.
     *
     * A matched product with parents contributes every one of its parents (a simple can belong to more
     * than one configurable). A matched product with no parent is kept when the rule keeps orphans, and
     * also when it is composite itself: a configurable that matched the rule directly is parentless by
     * nature and is never what "discard orphans" is aimed at.
     */
    public function resolveParents(array $productIds): array
    {
        $mode = $this->getParentResolution() ?: self::PARENT_RESOLUTION_NONE;
        if ($mode === self::PARENT_RESOLUTION_NONE || empty($productIds)) {
            return $productIds;
        }

        /** @var Mage_Catalog_Model_Resource_Category_Dynamic_Rule $resource */
        $resource = $this->getResource();
        $parentsByChild = $resource->getParentIdsByChildIds($productIds);

        $orphans = array_values(array_diff($productIds, array_keys($parentsByChild)));
        $keepOrphans = $mode !== self::PARENT_RESOLUTION_DISCARD_ORPHANS;
        $composites = $keepOrphans || empty($orphans) ? [] : $resource->getCompositeProductIds($orphans);

        // Keys dedupe two matched children that share a parent
        $resolved = [];
        foreach ($productIds as $productId) {
            if (isset($parentsByChild[$productId])) {
                foreach ($parentsByChild[$productId] as $parentId) {
                    $resolved[$parentId] = true;
                }
            } elseif ($keepOrphans || in_array($productId, $composites)) {
                $resolved[$productId] = true;
            }
        }

        return array_keys($resolved);
    }

    public function callbackValidateProduct(array $args): void
    {
        $product = clone $args['product'];
        $product->setData($args['row']);

        if ($this->validate($product)) {
            $this->_productIds[] = $product->getId();
        }
    }

    public function setProductsFilter(int|array $productIds): self
    {
        $this->_productsFilter = $productIds;
        return $this;
    }

    public function getCategory(): ?Mage_Catalog_Model_Category
    {
        if (!$this->_category && $this->getCategoryId()) {
            $this->_category = Mage::getModel('catalog/category')->load($this->getCategoryId());
        }
        return $this->_category;
    }

    public function setCategory(Mage_Catalog_Model_Category $category): self
    {
        $this->_category = $category;
        if ($category->getId()) {
            $this->setCategoryId($category->getId());
        }
        return $this;
    }
}

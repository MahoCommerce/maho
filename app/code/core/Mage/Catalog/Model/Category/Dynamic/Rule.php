<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @method int getRuleId()
 * @method $this setRuleId(int $value)
 * @method int getCategoryId()
 * @method $this setCategoryId(int $value)
 * @method string getConditionsSerialized()
 * @method $this setConditionsSerialized(string $value)
 * @method int getIsActive()
 * @method $this setIsActive(int $value)
 * @method string getCreatedAt()
 * @method $this setCreatedAt(string $value)
 * @method string getUpdatedAt()
 * @method $this setUpdatedAt(string $value)
 */

class Mage_Catalog_Model_Category_Dynamic_Rule extends Mage_Rule_Model_Abstract
{
    protected ?array $_productIds = null;
    protected int|array|null $_productsFilter = null;
    protected ?Mage_Catalog_Model_Category $_category = null;

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
    public function getConditionsInstance(): Mage_CatalogRule_Model_Rule_Condition_Combine
    {
        return Mage::getModel('catalogrule/rule_condition_combine');
    }

    #[\Override]
    public function getActionsInstance(): Mage_Rule_Model_Action_Collection
    {
        return Mage::getModel('rule/action_collection');
    }

    #[\Override]
    protected function _beforeSave(): self
    {
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

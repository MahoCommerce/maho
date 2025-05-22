<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Catalog Category Dynamic Rule Model
 *
 * @package    Mage_Catalog
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
    /**
     * Store matched product Ids
     *
     * @var array
     */
    protected $_productIds;

    /**
     * Limitation for products collection
     *
     * @var int|array|null
     */
    protected $_productsFilter = null;

    /**
     * Store current category model
     *
     * @var Mage_Catalog_Model_Category
     */
    protected $_category;

    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init('catalog/category_dynamic_rule');
        $this->setIdFieldName('rule_id');
    }

    /**
     * Getter for rule conditions collection
     *
     * @return Mage_CatalogRule_Model_Rule_Condition_Combine
     */
    public function getConditions()
    {
        if (empty($this->_conditions)) {
            $this->_resetConditions();
        }

        // Migration from conditions serialized
        if (!$this->getConditionsSerialized()) {
            $this->setConditionsSerialized($this->_conditions->asArray());
        }

        return $this->_conditions;
    }

    /**
     * Reset rule conditions
     *
     * @param null|Mage_Rule_Model_Condition_Combine $conditions
     * @return $this
     */
    protected function _resetConditions($conditions = null)
    {
        if (is_null($conditions)) {
            $conditions = $this->getConditionsInstance();
        }
        $conditions->setRule($this)->setId('1')->setPrefix('conditions');
        $this->setConditions($conditions);

        return $this;
    }

    /**
     * Retrieve rule conditions instance
     *
     * @return Mage_CatalogRule_Model_Rule_Condition_Combine
     */
    public function getConditionsInstance()
    {
        return Mage::getModel('catalogrule/rule_condition_combine');
    }

    /**
     * Retrieve rule actions instance
     *
     * @return Mage_Rule_Model_Action_Collection
     */
    public function getActionsInstance()
    {
        return Mage::getModel('rule/action_collection');
    }

    /**
     * Prepare data before saving
     */
    protected function _beforeSave()
    {
        // Serialize conditions
        if ($this->getConditions()) {
            $this->setConditionsSerialized(serialize($this->getConditions()->asArray()));
            $this->unsConditions();
        }

        parent::_beforeSave();
        return $this;
    }

    /**
     * Set conditions
     *
     * @param Mage_Rule_Model_Condition_Combine|null $conditions
     * @return $this
     */
    public function setConditions($conditions)
    {
        $this->_conditions = $conditions;
        return $this;
    }

    /**
     * Load rule conditions from array
     *
     * @param array $arr
     * @return $this
     */
    public function loadPost(array $arr)
    {
        $arr = $this->_convertFlatToRecursive($arr);
        if (isset($arr['conditions'])) {
            $this->getConditions()->loadArray($arr['conditions'][1]);
        }
        return $this;
    }

    /**
     * Returns rule as an array for admin interface
     *
     * @param array $arrAttributes
     * @return array
     */
    public function asArray(array $arrAttributes = [])
    {
        $out = parent::asArray($arrAttributes);
        $out['conditions'] = $this->getConditions()->asArray();

        return $out;
    }

    /**
     * Initialize rule model data from array
     *
     * @param array $rule
     * @return $this
     */
    public function loadArray($rule)
    {
        if (!empty($rule['conditions']) && is_array($rule['conditions'])) {
            $this->getConditions()->loadArray($rule['conditions']);
        }
        return $this;
    }

    /**
     * After loading, unserialize conditions
     */
    protected function _afterLoad()
    {
        if ($this->getConditionsSerialized()) {
            $conditions = $this->getConditionsSerialized();
            if (is_string($conditions)) {
                $conditions = unserialize($conditions);
            }
            if (is_array($conditions) && !empty($conditions)) {
                $this->getConditions()->loadArray($conditions);
            }
        }
        parent::_afterLoad();
        return $this;
    }

    /**
     * Validate rule conditions to determine if rule can be applied
     *
     * @param Varien_Object $object
     * @return bool
     */
    public function validate(Varien_Object $object)
    {
        return $this->getConditions()->validate($object);
    }

    /**
     * Get array of product ids which are matched by rule
     *
     * @return array
     */
    public function getMatchingProductIds()
    {
        if ($this->_productIds === null) {
            $this->_productIds = [];
            $this->setCollectedAttributes([]);

            /** @var Mage_Catalog_Model_Resource_Product_Collection $productCollection */
            $productCollection = Mage::getResourceModel('catalog/product_collection');
            
            if ($this->_productsFilter) {
                $productCollection->addIdFilter($this->_productsFilter);
            }

            $this->getConditions()->collectValidatedAttributes($productCollection);

            Mage::getSingleton('core/resource_iterator')->walk(
                $productCollection->getSelect(),
                [[$this, 'callbackValidateProduct']],
                [
                    'attributes' => $this->getCollectedAttributes(),
                    'product'    => Mage::getModel('catalog/product'),
                ]
            );
        }

        return $this->_productIds;
    }

    /**
     * Callback function for product matching
     *
     * @param array $args
     * @return void
     */
    public function callbackValidateProduct($args)
    {
        $product = clone $args['product'];
        $product->setData($args['row']);

        if ($this->validate($product)) {
            $this->_productIds[] = $product->getId();
        }
    }

    /**
     * Set products filter for rule matching
     *
     * @param int|array $productIds
     * @return $this
     */
    public function setProductsFilter($productIds)
    {
        $this->_productsFilter = $productIds;
        return $this;
    }

    /**
     * Get category model
     *
     * @return Mage_Catalog_Model_Category
     */
    public function getCategory()
    {
        if (!$this->_category && $this->getCategoryId()) {
            $this->_category = Mage::getModel('catalog/category')->load($this->getCategoryId());
        }
        return $this->_category;
    }

    /**
     * Set category model
     *
     * @param Mage_Catalog_Model_Category $category
     * @return $this
     */
    public function setCategory($category)
    {
        $this->_category = $category;
        if ($category->getId()) {
            $this->setCategoryId($category->getId());
        }
        return $this;
    }
}
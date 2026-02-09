<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Dynamic Attribute Rule Model
 *
 * Supports multiple condition→output cases with first-match-wins evaluation.
 *
 * @method string getName()
 * @method $this setName(string $name)
 * @method string getCode()
 * @method $this setCode(string $code)
 * @method string|null getDescription()
 * @method $this setDescription(?string $description)
 * @method int getIsSystem()
 * @method $this setIsSystem(int $isSystem)
 * @method int getIsEnabled()
 * @method $this setIsEnabled(int $isEnabled)
 * @method int getSortOrder()
 * @method $this setSortOrder(int $sortOrder)
 * @method string getCreatedAt()
 * @method $this setCreatedAt(string $createdAt)
 * @method string getUpdatedAt()
 * @method $this setUpdatedAt(string $updatedAt)
 */
class Maho_FeedManager_Model_DynamicRule extends Mage_Core_Model_Abstract
{
    public const OUTPUT_TYPE_STATIC = 'static';
    public const OUTPUT_TYPE_ATTRIBUTE = 'attribute';
    public const OUTPUT_TYPE_COMBINED = 'combined';

    public const COMBINED_POSITION_PREFIX = 'prefix';
    public const COMBINED_POSITION_SUFFIX = 'suffix';

    /**
     * Cached cases array
     */
    protected ?array $_cases = null;

    /**
     * Form instance for conditions rendering
     */
    protected ?\Maho\Data\Form $_form = null;

    /**
     * Get form instance for conditions (required by Mage_Rule_Model_Condition_Abstract)
     */
    public function getForm(): \Maho\Data\Form
    {
        if ($this->_form === null) {
            $this->_form = new \Maho\Data\Form();
        }
        return $this->_form;
    }

    /**
     * Set form instance
     */
    public function setForm(\Maho\Data\Form $form): self
    {
        $this->_form = $form;
        return $this;
    }

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('feedmanager/dynamicRule');
    }

    /**
     * Load rule by code
     */
    public function loadByCode(string $code): self
    {
        $this->_getResource()->loadByCode($this, $code);
        return $this;
    }

    /**
     * Check if this is a system rule (cannot be deleted)
     */
    public function isSystem(): bool
    {
        return (bool) $this->getIsSystem();
    }

    /**
     * Check if rule is enabled
     */
    public function isEnabled(): bool
    {
        return (bool) $this->getIsEnabled();
    }

    /**
     * Get cases array
     *
     * @return array Array of case data
     */
    public function getCases(): array
    {
        if ($this->_cases === null) {
            $casesJson = $this->getData('cases');
            if ($casesJson) {
                try {
                    $this->_cases = Mage::helper('core')->jsonDecode($casesJson) ?: [];
                } catch (\JsonException $e) {
                    Mage::log(
                        'Failed to decode cases JSON for rule ' . $this->getId() . ': ' . $e->getMessage(),
                        Mage::LOG_WARNING,
                    );
                    $this->_cases = [];
                }
            } else {
                $this->_cases = [];
            }
        }
        return $this->_cases;
    }

    /**
     * Set cases array
     *
     * @param array $cases Array of case data
     * @return $this
     */
    public function setCases(array $cases): self
    {
        $this->_cases = $cases;
        $this->setData('cases', Mage::helper('core')->jsonEncode($cases));
        return $this;
    }

    /**
     * Add a new case
     *
     * @param array $conditions Conditions array (null for default case)
     * @param string $outputType Output type
     * @param string|null $outputValue Static value or prefix/suffix
     * @param string|null $outputAttribute Attribute code
     * @param string $combinedPosition 'prefix' or 'suffix'
     * @param bool $isDefault Whether this is the default case
     * @return $this
     */
    public function addCase(
        ?array $conditions,
        string $outputType,
        ?string $outputValue = null,
        ?string $outputAttribute = null,
        string $combinedPosition = self::COMBINED_POSITION_PREFIX,
        bool $isDefault = false,
    ): self {
        $cases = $this->getCases();
        $cases[] = [
            'conditions' => $conditions,
            'output_type' => $outputType,
            'output_value' => $outputValue,
            'output_attribute' => $outputAttribute,
            'combined_position' => $combinedPosition,
            'is_default' => $isDefault,
        ];
        return $this->setCases($cases);
    }

    /**
     * Get output type options for dropdown
     */
    public static function getOutputTypeOptions(): array
    {
        return [
            self::OUTPUT_TYPE_STATIC => Mage::helper('feedmanager')->__('Static Value'),
            self::OUTPUT_TYPE_ATTRIBUTE => Mage::helper('feedmanager')->__('Product Attribute'),
            self::OUTPUT_TYPE_COMBINED => Mage::helper('feedmanager')->__('Combined'),
        ];
    }

    /**
     * Get combined position options
     */
    public static function getCombinedPositionOptions(): array
    {
        return [
            self::COMBINED_POSITION_PREFIX => Mage::helper('feedmanager')->__('Prefix'),
            self::COMBINED_POSITION_SUFFIX => Mage::helper('feedmanager')->__('Suffix'),
        ];
    }

    /**
     * Get available product attributes for output
     */
    public static function getOutputAttributeOptions(): array
    {
        $attributes = [];

        // Special/computed attributes
        $special = [
            'qty' => Mage::helper('feedmanager')->__('Quantity'),
            'is_in_stock' => Mage::helper('feedmanager')->__('Is In Stock'),
            'type_id' => Mage::helper('feedmanager')->__('Product Type'),
            'entity_id' => Mage::helper('feedmanager')->__('Product ID'),
            'parent_id' => Mage::helper('feedmanager')->__('Parent ID'),
        ];

        foreach ($special as $code => $label) {
            $attributes[] = ['value' => $code, 'label' => $label];
        }

        // EAV product attributes
        $collection = Mage::getResourceModel('catalog/product_attribute_collection')
            ->addVisibleFilter()
            ->setOrder('frontend_label', 'ASC');

        foreach ($collection as $attribute) {
            $label = $attribute->getFrontendLabel();
            if ($label) {
                $attributes[] = [
                    'value' => $attribute->getAttributeCode(),
                    'label' => $label,
                ];
            }
        }

        return $attributes;
    }

    /**
     * Validate rule data before save
     */
    public function validateData(\Maho\DataObject $dataObject): array|bool
    {
        $errors = [];

        if (!$dataObject->getName()) {
            $errors[] = Mage::helper('feedmanager')->__('Name is required.');
        }

        if (!$dataObject->getCode()) {
            $errors[] = Mage::helper('feedmanager')->__('Code is required.');
        } elseif (!preg_match('/^[a-z][a-z0-9_]*$/', $dataObject->getCode())) {
            $errors[] = Mage::helper('feedmanager')->__('Code must start with a letter and contain only lowercase letters, numbers, and underscores.');
        }

        // Check for duplicate code
        if ($dataObject->getCode()) {
            $existing = Mage::getModel('feedmanager/dynamicRule')->loadByCode($dataObject->getCode());
            if ($existing->getId() && $existing->getId() != $this->getId()) {
                $errors[] = Mage::helper('feedmanager')->__('A rule with this code already exists.');
            }
        }

        return empty($errors) ? true : $errors;
    }

    #[\Override]
    protected function _beforeSave(): self
    {
        parent::_beforeSave();

        $now = Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
        if (!$this->getCreatedAt()) {
            $this->setCreatedAt($now);
        }
        $this->setUpdatedAt($now);

        // Ensure code is lowercase
        if ($this->getCode()) {
            $this->setCode(strtolower($this->getCode()));
        }

        return $this;
    }

    #[\Override]
    protected function _beforeDelete(): self
    {
        parent::_beforeDelete();

        if ($this->isSystem()) {
            Mage::throwException(
                Mage::helper('feedmanager')->__('System rules cannot be deleted.'),
            );
        }

        return $this;
    }

    /**
     * Evaluate this rule against a product (first match wins)
     *
     * @return mixed The output value from the first matching case, or null
     */
    public function evaluate(Mage_Catalog_Model_Product $product): mixed
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $cases = $this->getCases();
        $defaultCase = null;

        foreach ($cases as $case) {
            // Store default case for later
            if (!empty($case['is_default'])) {
                $defaultCase = $case;
                continue;
            }

            // Check if conditions match
            if ($this->_evaluateCaseConditions($case, $product)) {
                return $this->_getCaseOutput($case, $product);
            }
        }

        // If no case matched, use default case
        if ($defaultCase !== null) {
            return $this->_getCaseOutput($defaultCase, $product);
        }

        return null;
    }

    /**
     * Evaluate case conditions against a product
     */
    protected function _evaluateCaseConditions(array $case, Mage_Catalog_Model_Product $product): bool
    {
        $conditions = $case['conditions'] ?? null;

        // No conditions = always match (unless it's a default case)
        if (empty($conditions)) {
            return true;
        }

        // Create a temporary rule model for condition evaluation
        $conditionModel = Mage::getModel('feedmanager/rule_condition_combine');
        $conditionModel->setRule($this);

        // Load conditions into the model
        if (is_array($conditions)) {
            $conditionModel->loadArray($conditions);
        }

        return $conditionModel->validate($product);
    }

    /**
     * Get output value for a case
     */
    protected function _getCaseOutput(array $case, Mage_Catalog_Model_Product $product): mixed
    {
        $outputType = $case['output_type'] ?? self::OUTPUT_TYPE_STATIC;
        $outputValue = $case['output_value'] ?? null;
        $outputAttribute = $case['output_attribute'] ?? null;
        $combinedPosition = $case['combined_position'] ?? self::COMBINED_POSITION_PREFIX;

        return match ($outputType) {
            self::OUTPUT_TYPE_STATIC => $outputValue,
            self::OUTPUT_TYPE_ATTRIBUTE => $this->_getProductAttributeValue($product, $outputAttribute),
            self::OUTPUT_TYPE_COMBINED => $this->_getCombinedOutput(
                $product,
                $outputValue,
                $outputAttribute,
                $combinedPosition,
            ),
            default => null,
        };
    }

    /**
     * Get product attribute value (including special attributes)
     */
    protected function _getProductAttributeValue(Mage_Catalog_Model_Product $product, ?string $attributeCode): mixed
    {
        if (!$attributeCode) {
            return null;
        }

        // Handle special attributes
        if ($attributeCode === 'qty') {
            $stockItem = $product->getStockItem();
            if (!$stockItem) {
                $stockItem = Mage::getModel('cataloginventory/stock_item');
                $stockItem->loadByProduct($product->getId());
            }
            return $stockItem->getQty();
        }

        if ($attributeCode === 'is_in_stock') {
            $stockItem = $product->getStockItem();
            if (!$stockItem) {
                $stockItem = Mage::getModel('cataloginventory/stock_item');
                $stockItem->loadByProduct($product->getId());
            }
            return $stockItem->getIsInStock() ? '1' : '0';
        }

        return $product->getData($attributeCode);
    }

    /**
     * Get combined output (prefix or suffix + attribute)
     */
    protected function _getCombinedOutput(
        Mage_Catalog_Model_Product $product,
        ?string $staticValue,
        ?string $attributeCode,
        string $position,
    ): string {
        $attrValue = (string) $this->_getProductAttributeValue($product, $attributeCode);
        $staticValue = (string) $staticValue;

        return $position === self::COMBINED_POSITION_PREFIX
            ? $staticValue . $attrValue
            : $attrValue . $staticValue;
    }
}

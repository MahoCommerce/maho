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
 * @method string|null getRuleData()
 * @method $this setRuleData(?string $ruleData)
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
     * Get rule data as array
     */
    public function getRuleDataArray(): array
    {
        $data = $this->getRuleData();
        if (empty($data)) {
            return ['output_rows' => []];
        }

        $decoded = Mage::helper('core')->jsonDecode($data);
        return is_array($decoded) ? $decoded : ['output_rows' => []];
    }

    /**
     * Set rule data from array
     */
    public function setRuleDataArray(array $data): self
    {
        $this->setRuleData(Mage::helper('core')->jsonEncode($data));
        return $this;
    }

    /**
     * Get output rows
     */
    public function getOutputRows(): array
    {
        $data = $this->getRuleDataArray();
        return $data['output_rows'] ?? [];
    }

    /**
     * Set output rows
     */
    public function setOutputRows(array $rows): self
    {
        return $this->setRuleDataArray(['output_rows' => $rows]);
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
     * Get output type options for dropdown
     */
    public static function getOutputTypeOptions(): array
    {
        return [
            self::OUTPUT_TYPE_STATIC => Mage::helper('feedmanager')->__('Static Value'),
            self::OUTPUT_TYPE_ATTRIBUTE => Mage::helper('feedmanager')->__('Product Attribute'),
            self::OUTPUT_TYPE_COMBINED => Mage::helper('feedmanager')->__('Combined (Prefix + Attribute)'),
        ];
    }

    /**
     * Validate rule data before save
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->getName())) {
            $errors[] = Mage::helper('feedmanager')->__('Name is required.');
        }

        if (empty($this->getCode())) {
            $errors[] = Mage::helper('feedmanager')->__('Code is required.');
        } elseif (!preg_match('/^[a-z][a-z0-9_]*$/', $this->getCode())) {
            $errors[] = Mage::helper('feedmanager')->__('Code must start with a letter and contain only lowercase letters, numbers, and underscores.');
        }

        // Check for duplicate code
        if ($this->getCode()) {
            $existing = Mage::getModel('feedmanager/dynamicRule')->loadByCode($this->getCode());
            if ($existing->getId() && $existing->getId() != $this->getId()) {
                $errors[] = Mage::helper('feedmanager')->__('A rule with this code already exists.');
            }
        }

        return $errors;
    }

    #[\Override]
    protected function _beforeSave(): self
    {
        parent::_beforeSave();

        $now = Mage_Core_Model_Locale::now();

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
}

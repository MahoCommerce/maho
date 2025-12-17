<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Segment_Condition_Customer_Attributes extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    /**
     * Initialize model
     */
    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_customer_attributes');
        $this->setValue(null);
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        return [
            'value' => $this->getType(),
            'label' => Mage::helper('customersegmentation')->__('Customer Personal Information'),
        ];
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $attributes = [
            'email' => Mage::helper('customersegmentation')->__('Email'),
            'firstname' => Mage::helper('customersegmentation')->__('First Name'),
            'lastname' => Mage::helper('customersegmentation')->__('Last Name'),
            'gender' => Mage::helper('customersegmentation')->__('Gender'),
            'dob' => Mage::helper('customersegmentation')->__('Date of Birth'),
            'created_at' => Mage::helper('customersegmentation')->__('Customer Since'),
            'group_id' => Mage::helper('customersegmentation')->__('Customer Group'),
            'store_id' => Mage::helper('customersegmentation')->__('Account Created In Store'),
            'days_since_registration' => Mage::helper('customersegmentation')->__('Days Since Registration'),
            'days_until_birthday' => Mage::helper('customersegmentation')->__('Days Until Birthday'),
        ];

        asort($attributes);
        $this->setAttributeOption($attributes);
        return $this;
    }

    #[\Override]
    public function getInputType(): string
    {
        return match ($this->getAttribute()) {
            'gender' => 'select',
            'group_id', 'store_id' => 'multiselect',
            'dob', 'created_at' => 'date',
            'days_since_registration', 'days_until_birthday' => 'numeric',
            default => 'string',
        };
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return match ($this->getAttribute()) {
            'gender', 'group_id', 'store_id' => 'select',
            'dob', 'created_at' => 'date',
            default => 'text',
        };
    }

    #[\Override]
    public function getOperatorSelectOptions(): array
    {
        $operators = parent::getOperatorSelectOptions();

        // For selection fields, use simple is/is not operators
        if (in_array($this->getAttribute(), ['gender', 'group_id', 'store_id'])) {
            return [
                ['value' => '==', 'label' => Mage::helper('rule')->__('is')],
                ['value' => '!=', 'label' => Mage::helper('rule')->__('is not')],
            ];
        }

        return $operators;
    }

    #[\Override]
    public function getValueSelectOptions(): array
    {
        if (!$this->hasData('value_select_options')) {
            $options = match ($this->getAttribute()) {
                'gender' => Mage::getResourceSingleton('customer/customer')
                    ->getAttribute('gender')
                    ->getSource()
                    ->getAllOptions(),
                'group_id' => Mage::getResourceModel('customer/group_collection')
                    ->toOptionArray(),
                'store_id' => Mage::getSingleton('adminhtml/system_store')
                    ->getStoreValuesForForm(),
                default => [],
            };
            $this->setData('value_select_options', $options);
        }
        return $this->getData('value_select_options');
    }

    #[\Override]
    public function getConditionsSql(\Maho\Db\Adapter\AdapterInterface $adapter, ?int $websiteId = null): string|false
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = $this->getValue();

        switch ($attribute) {
            case 'email':
            case 'group_id':
            case 'store_id':
                $field = 'e.' . $attribute;
                return $this->buildSqlCondition($adapter, $field, $operator, $value);

            case 'created_at':
                // Handle datetime field by comparing just the date part
                $field = 'DATE(e.created_at)';
                return $this->buildSqlCondition($adapter, $field, $operator, $value);

            case 'firstname':
            case 'lastname':
            case 'gender':
            case 'dob':
                return $this->buildAttributeCondition($adapter, $attribute, $operator, $value);

            case 'days_since_registration':
                return $this->buildDaysSinceCondition($adapter, 'e.created_at', $operator, $value);

            case 'days_until_birthday':
                return $this->buildDaysUntilBirthdayCondition($adapter, $operator, $value);
        }

        return false;
    }

    protected function buildAttributeCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $attributeCode, string $operator, mixed $value): string|false
    {
        $attributeData = $this->getCustomerAttributeTable($attributeCode);
        if (!$attributeData) {
            return false;
        }

        // For datetime tables with equality operator, use DATE() for SQLite compatibility
        // (datetime stored as '1990-06-15 00:00:00' won't match '1990-06-15' as exact string)
        $field = 'attr.value';
        if (str_ends_with($attributeData['table'], '_datetime') && in_array($operator, ['=', '=='], true)) {
            $field = (string) $adapter->getDatePartSql($field);
        }

        $subselect = $adapter->select()
            ->from(['attr' => $attributeData['table']], ['entity_id'])
            ->where('attr.attribute_id = ?', $attributeData['attribute_id'])
            ->where($this->buildSqlCondition($adapter, $field, $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildDaysSinceCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $field, string $operator, mixed $value): string
    {
        $currentDate = Mage_Core_Model_Locale::now();
        $dateDiff = $adapter->getDateDiffSql("'{$currentDate}'", $field);
        return $this->buildSqlCondition($adapter, (string) $dateDiff, $operator, $value);
    }

    protected function buildDaysUntilBirthdayCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $operator, mixed $value): string|false
    {
        $attributeData = $this->getCustomerAttributeTable('dob');
        if (!$attributeData) {
            return false;
        }

        $subselect = $adapter->select()
            ->from(['attr' => $attributeData['table']], ['entity_id'])
            ->where('attr.attribute_id = ?', $attributeData['attribute_id'])
            ->where($this->buildSqlCondition(
                $adapter,
                $this->getBirthdayDiffSql($adapter),
                $operator,
                $value,
            ));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function getBirthdayDiffSql(\Maho\Db\Adapter\AdapterInterface $adapter): string
    {
        $currentDate = Mage_Core_Model_Locale::now();

        // Use the adapter's platform-specific implementation for anniversary calculation
        // This handles MySQL vs PostgreSQL differences and leap year edge cases
        return (string) $adapter->getDaysUntilAnniversarySql('attr.value', $currentDate);
    }

    #[\Override]
    public function getOperatorName(): string
    {
        // For selection fields, provide custom operator names
        if (in_array($this->getAttribute(), ['gender', 'group_id', 'store_id'])) {
            return match ($this->getOperator()) {
                '==' => Mage::helper('rule')->__('is'),
                '!=' => Mage::helper('rule')->__('is not'),
                default => parent::getOperatorName(),
            };
        }

        return parent::getOperatorName();
    }

    #[\Override]
    public function getAttributeName(): string
    {
        $attributeName = parent::getAttributeName();
        return Mage::helper('customersegmentation')->__('Customer') . ':' . ' ' . $attributeName;
    }

    #[\Override]
    public function asString($format = ''): string
    {
        $attribute = $this->getAttribute();
        $this->loadAttributeOptions();
        $attributeOptions = $this->getAttributeOption();
        $attributeLabel = is_array($attributeOptions) && isset($attributeOptions[$attribute]) ? $attributeOptions[$attribute] : $attribute;

        $operatorName = $this->getOperatorName();
        $valueName = $this->getValueName();
        return Mage::helper('customersegmentation')->__('Customer') . ':' . ' ' . $attributeLabel . ' ' . $operatorName . ' ' . $valueName;
    }
}

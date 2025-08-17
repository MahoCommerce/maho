<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Segment_Condition_Customer_Address extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_customer_address');
        $this->setValue(null);
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        return [
            'value' => $this->getType(),
            'label' => Mage::helper('customersegmentation')->__('Customer Address'),
        ];
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $attributes = [
            'firstname' => Mage::helper('customersegmentation')->__('First Name'),
            'lastname' => Mage::helper('customersegmentation')->__('Last Name'),
            'company' => Mage::helper('customersegmentation')->__('Company'),
            'street' => Mage::helper('customersegmentation')->__('Street Address'),
            'city' => Mage::helper('customersegmentation')->__('City'),
            'region' => Mage::helper('customersegmentation')->__('State/Province'),
            'postcode' => Mage::helper('customersegmentation')->__('ZIP/Postal Code'),
            'country_id' => Mage::helper('customersegmentation')->__('Country'),
            'telephone' => Mage::helper('customersegmentation')->__('Telephone'),
        ];

        $this->setAttributeOption($attributes);
        return $this;
    }

    #[\Override]
    public function getAttributeElement(): Varien_Data_Form_Element_Abstract
    {
        if (!$this->hasAttributeOption()) {
            $this->loadAttributeOptions();
        }

        $element = parent::getAttributeElement();
        // Don't set ShowAsText - allow dropdown selection for customer address attributes
        return $element;
    }

    #[\Override]
    public function getInputType(): string
    {
        return match ($this->getAttribute()) {
            'country_id', 'region' => 'select',
            default => 'string',
        };
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return match ($this->getAttribute()) {
            'country_id', 'region' => 'select',
            default => 'text',
        };
    }

    #[\Override]
    public function getValueSelectOptions(): array
    {
        $options = [];
        $options = match ($this->getAttribute()) {
            'country_id' => Mage::getResourceModel('directory/country_collection')->toOptionArray(),
            'region' => Mage::getResourceModel('directory/region_collection')->toOptionArray(),
            default => $options,
        };
        return $options;
    }

    #[\Override]
    public function getConditionsSql(Varien_Db_Adapter_Interface $adapter, ?int $websiteId = null): string|false
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = $this->getValue();

        if ($attribute === 'region') {
            return $this->_buildRegionCondition($adapter, $operator, $value);
        }

        $subselect = $adapter->select()
            ->from(['ca' => $this->_getCustomerAddressTable()], ['parent_id'])
            ->where($this->_buildSqlCondition($adapter, "ca.{$attribute}", $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildRegionCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['ca' => $this->_getCustomerAddressTable()], ['parent_id'])
            ->joinLeft(['dr' => $this->_getDirectoryRegionTable()], 'ca.region_id = dr.region_id', [])
            ->where(
                '(' . $this->_buildSqlCondition($adapter, 'ca.region', $operator, $value) .
                ' OR ' . $this->_buildSqlCondition($adapter, 'dr.name', $operator, $value) . ')',
            );

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _getCustomerAddressTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('customer/address_entity');
    }

    protected function _getDirectoryRegionTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('directory/country_region');
    }

    #[\Override]
    public function getAttributeName(): string
    {
        $attributeName = parent::getAttributeName();
        return Mage::helper('customersegmentation')->__('Address') . ': ' . $attributeName;
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
        return Mage::helper('customersegmentation')->__('Address') . ': ' . $attributeLabel . ' ' . $operatorName . ' ' . $valueName;
    }
}

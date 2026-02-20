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

        asort($attributes);
        $this->setAttributeOption($attributes);
        return $this;
    }

    #[\Override]
    public function getAttributeElement(): \Maho\Data\Form\Element\AbstractElement
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
    public function getConditionsSql(\Maho\Db\Adapter\AdapterInterface $adapter, ?int $websiteId = null): string|false
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = $this->getValue();

        if ($attribute === 'region') {
            return $this->buildRegionCondition($adapter, $operator, $value);
        }

        return $this->buildAddressAttributeCondition($adapter, $attribute, $operator, $value);
    }

    protected function buildAddressAttributeCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $attributeCode, string $operator, mixed $value): string|false
    {
        $attributeData = $this->getCustomerAddressAttributeTable($attributeCode);
        if (!$attributeData) {
            return false;
        }

        $subselect = $adapter->select()
            ->from(['attr' => $attributeData['table']], ['entity_id'])
            ->where('attr.attribute_id = ?', $attributeData['attribute_id'])
            ->where($this->buildSqlCondition($adapter, 'attr.value', $operator, $value));

        return 'e.entity_id IN (SELECT ca.parent_id FROM ' . $this->getCustomerAddressTable() . ' ca WHERE ca.entity_id IN (' . $subselect . '))';
    }

    protected function buildRegionCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $operator, mixed $value): string|false
    {
        // Handle region field which can be either text (EAV varchar attribute) or region_id (EAV int attribute)
        $regionAttributeData = $this->getCustomerAddressAttributeTable('region');
        $regionIdAttributeData = $this->getCustomerAddressAttributeTable('region_id');

        $conditions = [];

        // Check text region field
        if ($regionAttributeData) {
            $regionSubselect = $adapter->select()
                ->from(['region_attr' => $regionAttributeData['table']], ['entity_id'])
                ->where('region_attr.attribute_id = ?', $regionAttributeData['attribute_id'])
                ->where($this->buildSqlCondition($adapter, 'region_attr.value', $operator, $value));
            $conditions[] = 'ca.entity_id IN (' . $regionSubselect . ')';
        }

        // Check directory region lookup via region_id
        if ($regionIdAttributeData) {
            $regionIdSubselect = $adapter->select()
                ->from(['rid_attr' => $regionIdAttributeData['table']], ['entity_id'])
                ->joinLeft(['dr' => $this->getDirectoryRegionTable()], 'rid_attr.value = dr.region_id', [])
                ->where('rid_attr.attribute_id = ?', $regionIdAttributeData['attribute_id'])
                ->where($this->buildSqlCondition($adapter, 'dr.default_name', $operator, $value));
            $conditions[] = 'ca.entity_id IN (' . $regionIdSubselect . ')';
        }

        if (empty($conditions)) {
            return false;
        }

        $combinedCondition = implode(' OR ', $conditions);
        $subselect = $adapter->select()
            ->from(['ca' => $this->getCustomerAddressTable()], ['parent_id'])
            ->where($combinedCondition);

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function getCustomerAddressTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('customer/address_entity');
    }

    protected function getDirectoryRegionTable(): string
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

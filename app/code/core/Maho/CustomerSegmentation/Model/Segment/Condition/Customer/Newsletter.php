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

class Maho_CustomerSegmentation_Model_Segment_Condition_Customer_Newsletter extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_customer_newsletter');
        $this->setValue(null);
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        return [
            'value' => $this->getType(),
            'label' => Mage::helper('customersegmentation')->__('Newsletter Subscription'),
        ];
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $attributes = [
            'subscriber_status' => Mage::helper('customersegmentation')->__('Subscription Status'),
            'change_status_at' => Mage::helper('customersegmentation')->__('Status Change Date'),
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
        $element->setShowAsText(true);
        return $element;
    }

    #[\Override]
    public function getInputType(): string
    {
        return match ($this->getAttribute()) {
            'subscriber_status' => 'select',
            'change_status_at' => 'date',
            default => 'string',
        };
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return match ($this->getAttribute()) {
            'subscriber_status' => 'select',
            'change_status_at' => 'date',
            default => 'text',
        };
    }

    #[\Override]
    public function getValueSelectOptions(): array
    {
        $options = [];
        switch ($this->getAttribute()) {
            case 'subscriber_status':
                $options = [
                    ['value' => '', 'label' => Mage::helper('customersegmentation')->__('Please select...')],
                    ['value' => Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED, 'label' => Mage::helper('customersegmentation')->__('Subscribed')],
                    ['value' => Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE, 'label' => Mage::helper('customersegmentation')->__('Not Active')],
                    ['value' => Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED, 'label' => Mage::helper('customersegmentation')->__('Unsubscribed')],
                    ['value' => Mage_Newsletter_Model_Subscriber::STATUS_UNCONFIRMED, 'label' => Mage::helper('customersegmentation')->__('Unconfirmed')],
                ];
                break;
        }
        return $options;
    }

    #[\Override]
    public function getConditionsSql(Varien_Db_Adapter_Interface $adapter, ?int $websiteId = null): string|false
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = $this->getValue();
        return match ($attribute) {
            'subscriber_status' => $this->_buildSubscriberStatusCondition($adapter, $operator, $value),
            'change_status_at' => $this->_buildStatusChangeDateCondition($adapter, $operator, $value),
            default => false,
        };
    }

    protected function _buildSubscriberStatusCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['ns' => $this->_getNewsletterSubscriberTable()], ['customer_id'])
            ->where('ns.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, 'ns.subscriber_status', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildStatusChangeDateCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['ns' => $this->_getNewsletterSubscriberTable()], ['customer_id'])
            ->where('ns.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, 'ns.change_status_at', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _getNewsletterSubscriberTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('newsletter/subscriber');
    }

    #[\Override]
    public function getAttributeName()
    {
        $attributeName = parent::getAttributeName();
        if ($attributeName) {
            return Mage::helper('customersegmentation')->__('Newsletter:') . ' ' . $attributeName;
        }
        return $attributeName;
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
        return Mage::helper('customersegmentation')->__('Newsletter:') . ' ' . $attributeLabel . ' ' . (is_string($operatorName) ? $operatorName : '') . ' ' . (is_string($valueName) ? $valueName : '');
    }
}

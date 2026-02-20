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

        asort($attributes);
        $this->setAttributeOption($attributes);
        return $this;
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
    public function getConditionsSql(\Maho\Db\Adapter\AdapterInterface $adapter, ?int $websiteId = null): string|false
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = $this->getValue();
        return match ($attribute) {
            'subscriber_status' => $this->buildSubscriberStatusCondition($adapter, $operator, $value),
            'change_status_at' => $this->buildStatusChangeDateCondition($adapter, $operator, $value),
            default => false,
        };
    }

    protected function buildSubscriberStatusCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['ns' => $this->getNewsletterSubscriberTable()], ['customer_id'])
            ->where('ns.customer_id IS NOT NULL')
            ->where($this->buildSqlCondition($adapter, 'ns.subscriber_status', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildStatusChangeDateCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['ns' => $this->getNewsletterSubscriberTable()], ['customer_id'])
            ->where('ns.customer_id IS NOT NULL')
            ->where($this->buildSqlCondition($adapter, 'ns.change_status_at', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function getNewsletterSubscriberTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('newsletter/subscriber');
    }

    #[\Override]
    public function getAttributeName(): string
    {
        $attributeName = parent::getAttributeName();
        return Mage::helper('customersegmentation')->__('Newsletter') . ':' . ' ' . $attributeName;
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
        return Mage::helper('customersegmentation')->__('Newsletter') . ':' . ' ' . $attributeLabel . ' ' . $operatorName . ' ' . $valueName;
    }
}

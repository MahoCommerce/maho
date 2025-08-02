<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Payment_Model_Restriction_Rule_Condition_Customer_Segment extends Mage_Rule_Model_Condition_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('payment/restriction_rule_condition_customer_segment')
            ->setValue(null);
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $attributes = [
            'customer_segment' => Mage::helper('payment')->__('Customer Segment'),
        ];
        $this->setAttributeOption($attributes);
        return $this;
    }

    #[\Override]
    public function getInputType(): string
    {
        return 'select';
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return 'select';
    }

    #[\Override]
    public function getValueSelectOptions(): array
    {
        $options = [];
        if (!$this->hasData('value_select_options')) {
            if (Mage::helper('core')->isModuleEnabled('Maho_CustomerSegmentation')) {
                $segments = Mage::getResourceModel('customersegmentation/segment_collection')
                    ->addIsActiveFilter()
                    ->load();

                foreach ($segments as $segment) {
                    $options[] = [
                        'value' => $segment->getId(),
                        'label' => $segment->getName(),
                    ];
                }
            }
            $this->setData('value_select_options', $options);
        }
        return $this->getData('value_select_options');
    }

    #[\Override]
    public function validate(Varien_Object $object): bool
    {
        if (!Mage::helper('core')->isModuleEnabled('Maho_CustomerSegmentation')) {
            return false;
        }

        $address = $object;
        $quote = $address->getQuote();
        $customerId = $quote->getCustomerId();

        if (!$customerId) {
            return false; // Guest customers
        }

        $websiteId = $quote->getStore()->getWebsiteId();
        $segmentIds = Mage::helper('customersegmentation')->getCustomerSegmentIds($customerId, $websiteId);

        return $this->validateAttribute($segmentIds);
    }

    #[\Override]
    public function asHtml(): string
    {
        $html = $this->getTypeElement()->getHtml() .
            Mage::helper('rule')->__(
                'If %s %s %s',
                $this->getAttributeElement()->getHtml(),
                $this->getOperatorElement()->getHtml(),
                $this->getValueElement()->getHtml(),
            );

        if ($this->getId() != '1') {
            $html .= $this->getRemoveLinkHtml();
        }

        return $html;
    }
}

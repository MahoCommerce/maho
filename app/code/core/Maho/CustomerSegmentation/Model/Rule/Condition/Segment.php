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

class Maho_CustomerSegmentation_Model_Rule_Condition_Segment extends Mage_Rule_Model_Condition_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/rule_condition_segment')
            ->setValue(null);
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $attributes = [
            'customer_segment' => Mage::helper('customersegmentation')->__('Customer Segment'),
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
            $segments = Mage::getResourceModel('customersegmentation/segment_collection')
                ->addIsActiveFilter()
                ->load();

            foreach ($segments as $segment) {
                $options[] = [
                    'value' => $segment->getId(),
                    'label' => $segment->getName(),
                ];
            }
            $this->setData('value_select_options', $options);
        }
        return $this->getData('value_select_options');
    }

    #[\Override]
    public function validate(\Maho\DataObject $object): bool
    {
        $customerId = $object->getCustomerId();
        $websiteId = $object->getStore() ? $object->getStore()->getWebsiteId() : null;

        if (!$customerId) {
            return false; // Guest customers
        }

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

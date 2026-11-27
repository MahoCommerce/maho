<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Request as Request;

class Maho_PrivacyCenter_Block_Adminhtml_Request extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'privacycenter';
        $this->_controller = 'adminhtml_request';
        $this->_headerText = Mage::helper('privacycenter')->__('Privacy Requests');
        parent::__construct();
        $this->_removeButton('add');
    }

    /**
     * The counters above the grid.
     *
     * @return array<string, array{label: string, value: int}>
     */
    public function getCounters(): array
    {
        $helper = Mage::helper('privacycenter');
        $locale = Mage::app()->getLocale();
        $count = function (array $filters): int {
            $collection = Mage::getResourceModel('privacycenter/request_collection');
            foreach ($filters as $field => $condition) {
                $collection->addFieldToFilter($field, $condition);
            }
            return (int) $collection->getSize();
        };
        return [
            'pending' => [
                'label' => $helper->__('Awaiting Confirmation'),
                'value' => $count(['status' => Request::STATUS_PENDING]),
            ],
            'approval' => [
                'label' => $helper->__('Erasures Awaiting Approval'),
                'value' => $count(['type' => Request::TYPE_ERASURE, 'status' => Request::STATUS_CONFIRMED, 'approved_at' => ['null' => true]]),
            ],
            'due' => [
                'label' => $helper->__('Open for More Than %d Days', Maho_PrivacyCenter_Helper_Data::RESPONSE_DAYS - 7),
                'value' => $count([
                    'status' => ['in' => [Request::STATUS_CONFIRMED, Request::STATUS_PROCESSING]],
                    'confirmed_at' => ['lt' => $locale->formatDateForDb(time() - (Maho_PrivacyCenter_Helper_Data::RESPONSE_DAYS - 7) * 86400)],
                ]),
            ],
            'completed' => [
                'label' => $helper->__('Completed in the Last 30 Days'),
                'value' => $count([
                    'status' => Request::STATUS_COMPLETED,
                    'processed_at' => ['gteq' => $locale->formatDateForDb(time() - 30 * 86400)],
                ]),
            ],
        ];
    }

    #[\Override]
    protected function _toHtml()
    {
        $counters = $this->getLayout()->createBlock('adminhtml/template')
            ->setTemplate('privacycenter/request/counters.phtml')
            ->setCounters($this->getCounters());
        return $counters->toHtml() . parent::_toHtml();
    }
}

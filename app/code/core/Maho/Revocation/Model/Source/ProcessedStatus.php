<?php

/**
 * Maho
 *
 * @package    Maho_Revocation
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Revocation_Model_Source_ProcessedStatus
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $helper = Mage::helper('revocation');
        return [
            ['value' => Maho_Revocation_Model_Request::PROCESSED_STATUS_ACCEPTED, 'label' => $helper->__('Accepted')],
            ['value' => Maho_Revocation_Model_Request::PROCESSED_STATUS_REJECTED, 'label' => $helper->__('Rejected')],
            ['value' => Maho_Revocation_Model_Request::PROCESSED_STATUS_INFO_REQUESTED, 'label' => $helper->__('Information Requested')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function toOptionHash(): array
    {
        $options = [];
        foreach ($this->toOptionArray() as $option) {
            $options[$option['value']] = $option['label'];
        }
        return $options;
    }
}

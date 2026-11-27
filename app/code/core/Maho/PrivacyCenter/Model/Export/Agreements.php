<?php

/**
 * The terms and conditions that the subject accepted.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_PrivacyCenter_Model_Export_Agreements extends Maho_PrivacyCenter_Model_Export_AbstractExporter
{
    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('privacycenter')->__('Accepted Terms and Conditions');
    }

    #[\Override]
    public function export(Subject $subject): array
    {
        $acceptances = Mage::getService('checkout/agreement')->getAcceptanceCollection();
        $acceptances->getSelect()->where(
            $subject->getRowsCondition($acceptances->getConnection(), 'main_table.customer_id', 'main_table.email'),
            $subject->email,
        );
        $data = [];
        foreach ($acceptances as $acceptance) {
            $data[] = $this->clean(array_intersect_key(
                $acceptance->getData(),
                array_flip(['name', 'version', 'checkbox_text', 'context', 'order_id', 'remote_ip', 'user_agent', 'created_at']),
            ));
        }
        return $data;
    }
}

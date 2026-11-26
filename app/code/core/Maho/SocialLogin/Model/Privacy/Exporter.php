<?php

/**
 * The social login links of the customer, for the export of the Privacy Center.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_SocialLogin_Model_Privacy_Exporter extends Maho_PrivacyCenter_Model_Export_AbstractExporter
{
    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('sociallogin')->__('Social Login');
    }

    #[\Override]
    public function export(Subject $subject): array
    {
        if ($subject->isGuest()) {
            return [];
        }
        $data = [];
        foreach (Mage::getResourceModel('sociallogin/identity_collection')->addFieldToFilter('customer_id', $subject->customerId) as $identity) {
            $data[] = $this->clean(array_intersect_key(
                $identity->getData(),
                array_flip(['provider', 'provider_id', 'provider_email', 'created_at']),
            ));
        }
        return $data;
    }
}

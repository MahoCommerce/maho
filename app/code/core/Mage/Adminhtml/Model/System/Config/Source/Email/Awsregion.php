<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_System_Config_Source_Email_Awsregion
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => Mage::helper('adminhtml')->__('-- Please Select --')],
            ['value' => 'us-east-1', 'label' => 'US East (N. Virginia)'],
            ['value' => 'us-east-2', 'label' => 'US East (Ohio)'],
            ['value' => 'us-west-1', 'label' => 'US West (N. California)'],
            ['value' => 'us-west-2', 'label' => 'US West (Oregon)'],
            ['value' => 'af-south-1', 'label' => 'Africa (Cape Town)'],
            ['value' => 'ap-east-1', 'label' => 'Asia Pacific (Hong Kong)'],
            ['value' => 'ap-south-1', 'label' => 'Asia Pacific (Mumbai)'],
            ['value' => 'ap-northeast-1', 'label' => 'Asia Pacific (Tokyo)'],
            ['value' => 'ap-northeast-2', 'label' => 'Asia Pacific (Seoul)'],
            ['value' => 'ap-northeast-3', 'label' => 'Asia Pacific (Osaka)'],
            ['value' => 'ap-southeast-1', 'label' => 'Asia Pacific (Singapore)'],
            ['value' => 'ap-southeast-2', 'label' => 'Asia Pacific (Sydney)'],
            ['value' => 'ap-southeast-3', 'label' => 'Asia Pacific (Jakarta)'],
            ['value' => 'ca-central-1', 'label' => 'Canada (Central)'],
            ['value' => 'eu-central-1', 'label' => 'Europe (Frankfurt)'],
            ['value' => 'eu-west-1', 'label' => 'Europe (Ireland)'],
            ['value' => 'eu-west-2', 'label' => 'Europe (London)'],
            ['value' => 'eu-west-3', 'label' => 'Europe (Paris)'],
            ['value' => 'eu-south-1', 'label' => 'Europe (Milan)'],
            ['value' => 'eu-north-1', 'label' => 'Europe (Stockholm)'],
            ['value' => 'me-south-1', 'label' => 'Middle East (Bahrain)'],
            ['value' => 'sa-east-1', 'label' => 'South America (São Paulo)'],
        ];
    }
}

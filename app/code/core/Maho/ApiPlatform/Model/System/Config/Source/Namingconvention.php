<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * GraphQL naming convention source model
 */
class Maho_ApiPlatform_Model_System_Config_Source_Namingconvention
{
    public const GRAPHQL_STANDARD = 'graphql';
    public const MAGENTO2 = 'magento2';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::GRAPHQL_STANDARD, 'label' => Mage::helper('maho_apiplatform')->__('GraphQL Standard (camelCase) - Recommended')],
            ['value' => self::MAGENTO2, 'label' => Mage::helper('maho_apiplatform')->__('Magento 2 (snake_case)')],
        ];
    }

    public function toArray(): array
    {
        return [
            self::GRAPHQL_STANDARD => Mage::helper('maho_apiplatform')->__('GraphQL Standard (camelCase)'),
            self::MAGENTO2 => Mage::helper('maho_apiplatform')->__('Magento 2 (snake_case)'),
        ];
    }
}

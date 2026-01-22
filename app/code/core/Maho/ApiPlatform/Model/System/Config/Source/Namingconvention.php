<?php

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

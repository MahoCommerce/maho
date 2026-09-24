<?php

/**
 * Give the condition metadata of cart price rules to the providers and processors of the cart price rule API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

namespace Mage\SalesRule\Api;

use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Trait\CacheTrait;

trait ConditionMetadataTrait
{
    use CacheTrait;

    /**
     * Customer segments come from the database without a cache tag, so the document expires after this time.
     */
    private int $conditionMetadataLifetime = 900;

    private ?\Mage_SalesRule_Model_Rule_Condition_Metadata $conditionMetadataModel = null;

    /**
     * Return the interface locale of the admin user of the token, as the web admin uses it.
     * A token without an admin user gets the default locale of the admin.
     */
    protected function adminLocale(): string
    {
        $adminId = $this->requireUser()->getAdminId();
        if ($adminId) {
            /** @var \Mage_Admin_Model_User $admin */
            $admin = \Mage::getModel('admin/user')->load($adminId);
            $locale = (string) $admin->getData('backend_locale');
            if ($this->isLocaleCode($locale)) {
                return $locale;
            }
        }

        $locale = (string) \Mage::getStoreConfig(\Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, \Mage_Core_Model_App::ADMIN_STORE_ID);
        return $this->isLocaleCode($locale) ? $locale : \Mage_Core_Model_Locale::DEFAULT_LOCALE;
    }

    protected function conditionMetadataModel(): \Mage_SalesRule_Model_Rule_Condition_Metadata
    {
        return $this->conditionMetadataModel ??= \Mage::getModel('salesrule/rule_condition_metadata');
    }

    /**
     * Return the metadata document of $locale without the scope part: version, locale, roots, types and rule.
     *
     * @return array<string, mixed>
     */
    protected function conditionMetadata(string $locale): array
    {
        return $this->remember(
            'API_CART_PRICE_RULE_CONDITION_METADATA_' . $locale,
            [\Mage_Core_Model_Config::CACHE_TAG, \Mage_Eav_Model_Entity_Attribute::CACHE_TAG],
            $this->conditionMetadataLifetime,
            fn(): array => $this->buildConditionMetadata($locale),
            fn(array $document): array => $document,
            fn(array $document): array => $document,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConditionMetadata(string $locale): array
    {
        $document = \Mage_Rule_Model_Condition_Metadata::runInLocale($locale, function (): array {
            $document = $this->conditionMetadataModel()->build();
            $document['rule'] = $this->ruleOptions();
            return $document;
        });

        return ['version' => hash('sha256', (string) json_encode($document)), 'locale' => $locale] + $document;
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleOptions(): array
    {
        $salesRule = \Mage::helper('salesrule');
        /** @var \Mage_SalesRule_Helper_Coupon $couponHelper */
        $couponHelper = \Mage::helper('salesrule/coupon');

        $formats = [];
        foreach ($couponHelper->getFormatsList() as $value => $label) {
            $formats[] = ['value' => (string) $value, 'label' => (string) $label];
        }
        // The shipped configuration has the format "1", which is not a format code
        $defaultFormat = (string) $couponHelper->getDefaultFormat();
        if (!array_key_exists($defaultFormat, $couponHelper->getFormatsList())) {
            $defaultFormat = \Mage_SalesRule_Helper_Coupon::COUPON_FORMAT_ALPHANUMERIC;
        }

        return [
            'simpleAction' => [
                ['value' => \Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION, 'label' => $salesRule->__('Percent of product price discount')],
                ['value' => \Mage_SalesRule_Model_Rule::BY_FIXED_ACTION, 'label' => $salesRule->__('Fixed amount discount')],
                ['value' => \Mage_SalesRule_Model_Rule::CART_FIXED_ACTION, 'label' => $salesRule->__('Fixed amount discount for whole cart')],
                ['value' => \Mage_SalesRule_Model_Rule::BUY_X_GET_Y_ACTION, 'label' => $salesRule->__('Buy X get Y free (discount amount is Y)')],
            ],
            'couponType' => [
                ['value' => 'none', 'label' => $salesRule->__('No Coupon')],
                ['value' => 'specific', 'label' => $salesRule->__('Specific Coupon')],
                ['value' => 'auto', 'label' => $salesRule->__('Auto')],
            ],
            'simpleFreeShipping' => [
                ['value' => 0, 'label' => $salesRule->__('No')],
                ['value' => \Mage_SalesRule_Model_Rule::FREE_SHIPPING_ITEM, 'label' => $salesRule->__('For matching items only')],
                ['value' => \Mage_SalesRule_Model_Rule::FREE_SHIPPING_ADDRESS, 'label' => $salesRule->__('For shipment with matching items')],
            ],
            'couponFormats' => $formats,
            'couponDefaults' => [
                'length' => (int) $couponHelper->getDefaultLength(),
                'format' => $defaultFormat,
                'prefix' => (string) $couponHelper->getDefaultPrefix(),
                'suffix' => (string) $couponHelper->getDefaultSuffix(),
                'dash' => (int) $couponHelper->getDefaultDashInterval(),
            ],
        ];
    }

    /**
     * Return the websites and stores that the token can use, and all customer groups.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    protected function ruleScope(ApiUser $user): array
    {
        $allowedWebsiteIds = $this->allowedWebsiteIds($user);
        $allowedStoreIds = $user->getAllowedStoreIds();

        $websites = [];
        foreach (\Mage::app()->getWebsites() as $website) {
            $id = (int) $website->getId();
            if ($allowedWebsiteIds === null || in_array($id, $allowedWebsiteIds, true)) {
                $websites[] = ['id' => $id, 'code' => (string) $website->getCode(), 'name' => (string) $website->getName()];
            }
        }

        $stores = [];
        foreach (\Mage::app()->getStores() as $store) {
            $id = (int) $store->getId();
            if ($allowedStoreIds === null || in_array($id, $allowedStoreIds, true)) {
                $stores[] = [
                    'id' => $id,
                    'code' => (string) $store->getCode(),
                    'name' => (string) $store->getName(),
                    'websiteId' => (int) $store->getWebsiteId(),
                ];
            }
        }

        $groups = [];
        foreach (\Mage::getResourceModel('customer/group_collection') as $group) {
            $groups[] = ['id' => (int) $group->getId(), 'code' => (string) $group->getCode()];
        }

        return ['websites' => $websites, 'stores' => $stores, 'customerGroups' => $groups];
    }

    private function isLocaleCode(string $locale): bool
    {
        return (bool) preg_match('/^[a-z]{2,3}(_[A-Za-z0-9]{2,8})*$/', $locale);
    }
}

<?php

/**
 * The readable labels of the raw values of a report dimension, such as store names or payment method titles.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

use Maho\Report\Buckets;

class Mage_Reports_Model_Explorer_Labels
{
    /**
     * @param string $code the code of the label resolver
     * @param list<string|int> $values the raw values
     * @return array<string|int, string> raw value => label; a value without a label is absent
     */
    public function resolve(string $code, array $values): array
    {
        $values = array_values(array_unique(array_filter($values, static fn(mixed $v): bool => $v !== null && $v !== '')));
        if ($values === []) {
            return [];
        }

        return match ($code) {
            'store' => $this->stores($values),
            'website' => $this->websites($values),
            'customer_group' => $this->customerGroups(),
            'order_status' => Mage::getSingleton('sales/order_config')->getStatuses(),
            'order_state' => Mage::getSingleton('sales/order_config')->getStates(),
            'payment_method' => $this->configTitles('payment', $values),
            'shipping_carrier' => $this->configTitles('carriers', $values),
            'country' => $this->countries($values),
            'category' => $this->entityNames('catalog/category_collection', $values),
            'product' => $this->entityNames('catalog/product_collection', $values),
            'customer' => $this->customerNames($values),
            'attribute_option' => $this->attributeOptions($values),
            'weekday' => $this->weekdays($values),
            'week' => $this->weeks($values),
            default => [],
        };
    }

    /**
     * @param list<string|int> $values
     * @return array<string|int, string>
     */
    private function stores(array $values): array
    {
        $labels = [];
        foreach (Mage::app()->getStores(true) as $store) {
            $labels[(int) $store->getId()] = $store->getId() == 0
                ? Mage::helper('reports')->__('All Store Views')
                : $store->getWebsite()->getName() . ' / ' . $store->getName();
        }
        return array_intersect_key($labels, array_flip(array_map(intval(...), $values)));
    }

    /**
     * @param list<string|int> $values
     * @return array<string|int, string>
     */
    private function websites(array $values): array
    {
        $labels = [];
        foreach (Mage::app()->getWebsites(true) as $website) {
            $labels[(int) $website->getId()] = (string) $website->getName();
        }
        return array_intersect_key($labels, array_flip(array_map(intval(...), $values)));
    }

    /** @return array<int, string> */
    private function customerGroups(): array
    {
        $labels = [];
        foreach (Mage::getResourceModel('customer/group_collection') as $group) {
            $labels[(int) $group->getId()] = (string) $group->getCustomerGroupCode();
        }
        return $labels;
    }

    /**
     * The title of a payment method or of a shipping carrier, from the configuration. A shipping method code such
     * as "flatrate_flatrate" takes the title of its carrier.
     *
     * @param list<string|int> $values
     * @return array<string|int, string>
     */
    private function configTitles(string $section, array $values): array
    {
        $labels = [];
        foreach ($values as $value) {
            $code = (string) $value;
            $key = $section === 'carriers' ? explode('_', $code, 2)[0] : $code;
            $title = (string) Mage::getStoreConfig("{$section}/{$key}/title");
            if ($title !== '') {
                $labels[$code] = $title;
            }
        }
        return $labels;
    }

    /**
     * @param list<string|int> $values
     * @return array<string|int, string>
     */
    private function countries(array $values): array
    {
        $locale = Mage::app()->getLocale();
        $labels = [];
        foreach ($values as $value) {
            $name = $locale->getCountryTranslation((string) $value);
            if ($name !== false && $name !== '') {
                $labels[(string) $value] = $name;
            }
        }
        return $labels;
    }

    /**
     * @param list<string|int> $values
     * @return array<string|int, string>
     */
    private function entityNames(string $collection, array $values): array
    {
        $ids = array_values(array_filter(array_map(intval(...), $values)));
        if ($ids === []) {
            return [];
        }
        $items = Mage::getResourceModel($collection);
        if (!$items instanceof Mage_Eav_Model_Entity_Collection_Abstract) {
            return [];
        }
        $items->addAttributeToSelect('name')->addAttributeToFilter('entity_id', ['in' => $ids]);
        $labels = [];
        foreach ($items as $item) {
            $labels[(int) $item->getId()] = (string) $item->getName();
        }
        return $labels;
    }

    /**
     * @param list<string|int> $values
     * @return array<string|int, string>
     */
    private function customerNames(array $values): array
    {
        $ids = array_values(array_filter(array_map(intval(...), $values)));
        if ($ids === []) {
            return [];
        }
        $customers = Mage::getResourceModel('customer/customer_collection')
            ->addNameToSelect()
            ->addAttributeToFilter('entity_id', ['in' => $ids]);
        $labels = [];
        foreach ($customers as $customer) {
            $labels[(int) $customer->getId()] = trim((string) $customer->getName()) . ' <' . $customer->getEmail() . '>';
        }
        return $labels;
    }

    /**
     * @param list<string|int> $values
     * @return array<string|int, string>
     */
    private function attributeOptions(array $values): array
    {
        $ids = array_values(array_filter(array_map(intval(...), $values)));
        if ($ids === []) {
            return [];
        }
        $options = Mage::getResourceModel('eav/entity_attribute_option_collection')
            ->setIdFilter($ids)
            ->setStoreFilter(Mage_Core_Model_App::ADMIN_STORE_ID);
        $labels = [];
        foreach ($options as $option) {
            $labels[(int) $option->getId()] = (string) $option->getValue();
        }
        return $labels;
    }

    /**
     * The name of each weekday number, 0 for Sunday, in the locale of the admin.
     *
     * @param list<string|int> $values
     * @return array<int, string>
     */
    private function weeks(array $values): array
    {
        $reference = new DateTimeImmutable(Buckets::weekReference(Mage_Reports_Model_Explorer_Runner::firstDayOfWeek()), new DateTimeZone('UTC'));
        $labels = [];
        foreach ($values as $value) {
            if (is_numeric($value)) {
                $labels[$value] = $reference->modify('+' . (int) $value . ' days')->format('Y-m-d');
            }
        }
        return $labels;
    }

    /**
     * @param list<string|int> $values
     * @return array<string|int, string>
     */
    private function weekdays(array $values): array
    {
        $formatter = new IntlDateFormatter(
            Mage::app()->getLocale()->getLocaleCode(),
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'UTC',
            null,
            'EEEE',
        );
        $labels = [];
        foreach ($values as $value) {
            $day = (int) $value;
            $name = $formatter->format(new DateTimeImmutable('1970-01-04 12:00:00 UTC +' . $day . ' days'));
            $labels[$day] = is_string($name) ? mb_convert_case($name, MB_CASE_TITLE) : (string) $day;
        }
        return $labels;
    }
}

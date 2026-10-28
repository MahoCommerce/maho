<?php

/**
 * Base controller for the attribute pages of customers and customer addresses.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

abstract class Mage_Adminhtml_Controller_Customer_Attribute extends Mage_Adminhtml_Controller_Eav_Attribute
{
    #[\Override]
    protected function getMainTabBlock(): string
    {
        return 'adminhtml/customer_attribute_edit_tab_main';
    }

    #[\Override]
    protected function prepareSaveData(array $data, Mage_Eav_Model_Entity_Attribute $model): array
    {
        /** @var Mage_Customer_Model_Attribute $model */
        if ($model->getId() && $model->getIsSystem()) {
            unset($data['used_in_forms']);
        } else {
            $allowed = array_keys($model->getFormCodes());
            $data['used_in_forms'] = array_values(array_intersect((array) ($data['used_in_forms'] ?? []), $allowed));
        }
        if (!$model->getId()) {
            $data['is_system'] = 0;
        }

        $data['input_filter'] = in_array($data['input_filter'] ?? '', ['striptags', 'escapehtml'], true)
            ? $data['input_filter'] : null;

        $rules = [];
        foreach (['min_text_length', 'max_text_length'] as $key) {
            if (!empty($data[$key])) {
                $rules[$key] = (int) $data[$key];
            }
        }
        if (in_array($data['input_validation'] ?? '', ['alphanumeric', 'alpha', 'numeric', 'email', 'url'], true)) {
            $rules['input_validation'] = $data['input_validation'];
        }
        $data['validate_rules'] = $rules;
        unset($data['min_text_length'], $data['max_text_length'], $data['input_validation'], $data['website_values']);

        if (isset($data['multiline_count'])) {
            $data['multiline_count'] = max(1, (int) $data['multiline_count']);
        }
        return $data;
    }

    #[\Override]
    protected function afterSave(Mage_Eav_Model_Entity_Attribute $model, array $data): void
    {
        $values = $this->getRequest()->getPost('website_values');
        if (is_array($values)) {
            /** @var Mage_Customer_Model_Resource_Attribute $resource */
            $resource = $model->getResource();
            $websiteIds = array_map(intval(...), array_keys(Mage::app()->getWebsites()));
            $resource->saveWebsiteValues((int) $model->getId(), array_intersect_key($values, array_flip($websiteIds)));
        }
    }
}

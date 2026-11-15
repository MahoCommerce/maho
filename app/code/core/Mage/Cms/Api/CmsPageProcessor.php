<?php

/**
 * Give a CMS page write to the CMS page service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

namespace Mage\Cms\Api;

use Maho\ApiPlatform\CrudProcessor;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\Security\ApiUser;

final class CmsPageProcessor extends CrudProcessor
{
    #[\Override]
    protected function newModel(): \Mage_Cms_Model_Page
    {
        return $this->service()->newPage();
    }

    #[\Override]
    protected function loadModel(int $id): \Mage_Cms_Model_Page
    {
        return $this->service()->getById($id);
    }

    #[\Override]
    protected function beforeSave(object $model, CrudResource $data, ApiUser $user): void
    {
        if ($data instanceof CmsPage) {
            foreach (['customThemeFrom' => 'custom_theme_from', 'customThemeTo' => 'custom_theme_to'] as $property => $field) {
                if ($data->{$property} !== null) {
                    $model->setData($field, $this->normalizeDateInput($data->{$property}, $field));
                }
            }
            if ($data->metaRobots !== null) {
                $model->setData('meta_robots', strtoupper(str_replace(' ', '', $data->metaRobots)));
            }
        }
    }

    #[\Override]
    protected function saveModel(\Mage_Core_Model_Abstract $model): void
    {
        /** @var \Mage_Cms_Model_Page $model */
        try {
            $this->service()->save($model);
        } catch (\Mage_Core_Exception_Input $e) {
            throw $e->withFieldNames(['root_template' => 'pageLayout']);
        }
    }

    #[\Override]
    protected function deleteModel(\Mage_Core_Model_Abstract $model): void
    {
        /** @var \Mage_Cms_Model_Page $model */
        $this->service()->delete($model);
    }

    /**
     * An empty string clears the date.
     *
     * @throws \Mage_Core_Exception_Input
     */
    private function normalizeDateInput(string $value, string $field): ?string
    {
        try {
            return \Mage::app()->getLocale()->formatDateForDb($value, withTime: false);
        } catch (\Exception) {
            throw new \Mage_Core_Exception_Input()->addError($field, \Mage::helper('cms')->__('Invalid date, use the Y-m-d format.'));
        }
    }

    private function service(): \Mage_Cms_Service_Page
    {
        /** @var \Mage_Cms_Service_Page */
        return \Mage::getService('cms/page');
    }
}

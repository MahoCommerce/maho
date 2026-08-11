<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

namespace Mage\Cms\Api;

use Maho\ApiPlatform\CrudProcessor;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CmsPageProcessor extends CrudProcessor
{
    private const VALID_META_ROBOTS = [
        'INDEX,FOLLOW',
        'NOINDEX,FOLLOW',
        'INDEX,NOFOLLOW',
        'NOINDEX,NOFOLLOW',
    ];

    #[\Override]
    protected function beforeSave(object $model, CrudResource $data, ApiUser $user): void
    {
        // Validate only what the request actually sent, never what is already stored:
        // a legacy or imported value outside the allowed set would otherwise make the
        // page permanently un-updatable through the API.
        if ($data instanceof CmsPage) {
            if ($data->customThemeFrom !== null) {
                $model->setData('custom_theme_from', $this->normalizeDateInput($data->customThemeFrom, 'customThemeFrom'));
            }
            if ($data->customThemeTo !== null) {
                $model->setData('custom_theme_to', $this->normalizeDateInput($data->customThemeTo, 'customThemeTo'));
            }
            if ($data->metaRobots !== null) {
                $model->setData('meta_robots', $this->normalizeMetaRobots($data->metaRobots));
            }
            if ($data->layoutUpdateXml !== null) {
                $model->setData('layout_update_xml', $this->validateLayoutUpdate($data->layoutUpdateXml, 'layoutUpdateXml'));
            }
            if ($data->customLayoutUpdateXml !== null) {
                $model->setData('custom_layout_update_xml', $this->validateLayoutUpdate($data->customLayoutUpdateXml, 'customLayoutUpdateXml'));
            }
        }

        // On create, apply defaults for fields omitted from the request. They are nullable on
        // the DTO so that an omitted field on a partial update is a no-op (no silent reset);
        // create still needs sane defaults: enabled page assigned to all store views.
        if (!$model->getId()) {
            // A page needs a URL identifier; reject a create that omits it with a
            // 4xx rather than persisting a page with an empty identifier.
            if (trim((string) $model->getData('identifier')) === '') {
                throw new BadRequestHttpException('Identifier is required.');
            }
            if ($model->getData('is_active') === null) {
                $model->setData('is_active', 1);
            }
            if ($model->getData('stores') === null) {
                $model->setData('stores', [0]);
            }
        }

        // Content sanitization lives in Mage_Cms_Model_Resource_Page::_beforeSave(), so it covers
        // every save path. Filtering here as well would run over unresolved template directives
        // and mangle them ({{media url="..."}} becomes a broken %7B%7B… URL).
    }

    /**
     * Empty string clears the value (returns null).
     */
    private function normalizeDateInput(string $value, string $field): ?string
    {
        try {
            return \Mage::app()->getLocale()->formatDateForDb($value, withTime: false);
        } catch (\Exception) {
            throw new BadRequestHttpException("Invalid date for {$field}; use Y-m-d format.");
        }
    }

    /**
     * Layout updates are executed when the storefront renders the page, so the API
     * must apply the same validator the admin form does (blocked templates,
     * disallowed blocks, helper attributes) instead of storing arbitrary XML.
     */
    private function validateLayoutUpdate(string $xml, string $field): ?string
    {
        if (trim($xml) === '') {
            return null;
        }

        /** @var \Mage_Adminhtml_Model_LayoutUpdate_Validator $validator */
        $validator = \Mage::getModel('adminhtml/layoutUpdate_validator');
        try {
            $isValid = $validator->isValid($xml);
        } catch (\Throwable) {
            $isValid = false;
        }

        if (!$isValid) {
            $messages = implode(' ', $validator->getMessages());
            throw new BadRequestHttpException(trim("{$field} is not a valid layout update. " . $messages));
        }

        return $xml;
    }

    private function normalizeMetaRobots(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $normalized = strtoupper(str_replace(' ', '', $value));
        if (!in_array($normalized, self::VALID_META_ROBOTS, true)) {
            throw new BadRequestHttpException('metaRobots must be one of: ' . implode(', ', self::VALID_META_ROBOTS));
        }

        return $normalized;
    }
}

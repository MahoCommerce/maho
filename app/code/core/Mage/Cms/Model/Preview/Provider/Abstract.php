<?php

/**
 * Shared steps of the live preview providers.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

abstract class Mage_Cms_Model_Preview_Provider_Abstract implements Mage_Cms_Model_Preview_ProviderInterface
{
    /**
     * The scalar values of $fields in $post, as strings.
     *
     * @param array<string, mixed> $post
     * @param list<string> $fields
     * @return array<string, string>
     */
    protected function pick(array $post, array $fields): array
    {
        $data = [];
        foreach ($fields as $field) {
            if (isset($post[$field]) && is_scalar($post[$field])) {
                $data[$field] = (string) $post[$field];
            }
        }
        return $data;
    }

    /**
     * Run $fields of $data through the sanitizer of the save path.
     *
     * @param array<string, string> $data
     * @param list<string> $fields
     * @return array<string, string>
     */
    protected function sanitize(array $data, array $fields, object $renderer, bool $applyLinkFilter = false): array
    {
        $object = new Maho\DataObject($data);
        Mage::getSingleton('core/input_filter_maliciousCode')->sanitizeFields(
            $object,
            $fields,
            applyLinkFilter: $applyLinkFilter,
            renderer: $renderer,
        );
        foreach ($fields as $field) {
            if ($object->hasData($field)) {
                $data[$field] = (string) $object->getData($field);
            }
        }
        return $data;
    }

    /**
     * Keep a layout update that the save path accepts. Leave out any other one, and
     * return the reasons.
     *
     * @param array<string, string> $data
     * @param list<string> $fields
     * @param list<string> $warnings
     * @return array<string, string>
     */
    protected function validateLayoutUpdates(array $data, array $fields, array &$warnings): array
    {
        foreach ($fields as $field) {
            if (($data[$field] ?? '') === '') {
                continue;
            }
            /** @var Mage_Adminhtml_Model_LayoutUpdate_Validator $validator */
            $validator = Mage::getModel('adminhtml/layoutUpdate_validator');
            if (!$validator->isValid($data[$field])) {
                unset($data[$field]);
                foreach ($validator->getMessages() as $message) {
                    $warnings[] = (string) $message;
                }
            }
        }
        return $data;
    }

    #[\Override]
    public function getTargetHtml(Mage_Core_Block_Abstract $block, string $html, array $draft): string
    {
        return $html;
    }
}

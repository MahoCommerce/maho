<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Path derivation mirrors Mage_Adminhtml_Block_System_Config_Form::_initFields().
 *
 * @return array<int, array{path: string, code: string}>
 */
function emailTemplateSelectFields(): array
{
    $fields = [];
    foreach (Mage::getSingleton('adminhtml/config')->getSections()->children() as $section) {
        $groups = iterator_to_array($section->groups?->children() ?? [], false);
        while ($groups !== []) {
            $group = array_shift($groups);
            foreach ($group->fields?->children() ?? [] as $field) {
                if ((string) $field->getAttribute('type') === 'group') {
                    $groups[] = $field;
                    continue;
                }
                if (!str_contains((string) $field->source_model, 'source_email_template')) {
                    continue;
                }
                $path = (string) $field->config_path
                    ?: "{$section->getName()}/{$group->getName()}/{$field->getName()}";
                $fields[] = [
                    'path' => $path,
                    'code' => str_replace('/', '_', $path),
                ];
            }
        }
    }

    return $fields;
}

it('registers a template for the code every email template select offers', function () {
    $registered = Mage_Core_Model_Email_Template::getDefaultTemplates();
    $fields = emailTemplateSelectFields();

    expect($fields)->not->toBeEmpty();

    foreach ($fields as ['path' => $path, 'code' => $code]) {
        expect($registered)->toHaveKey($code, message: "no template registered as \"{$code}\" for config field {$path}");
    }
});

it('defaults every email template select to a resolvable template code', function () {
    $registered = Mage_Core_Model_Email_Template::getDefaultTemplates();

    foreach (emailTemplateSelectFields() as ['path' => $path]) {
        // A numeric value is the id of an admin-created template, loaded from the database.
        $default = (string) Mage::getStoreConfig($path);
        if ($default === '' || is_numeric($default)) {
            continue;
        }
        expect($registered)->toHaveKey($default, message: "config default {$path} points at unregistered template \"{$default}\"");
    }
});

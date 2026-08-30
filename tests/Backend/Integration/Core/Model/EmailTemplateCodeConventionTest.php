<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * adminhtml/system_config_source_email_template offers "<section>_<group>_<field>",
 * derived from the field's own config path. A template registered under any other
 * name can never be selected, and saving the section stores an unresolvable code.
 *
 * @return array<int, array{path: string, code: string}>
 */
function emailTemplateSelectFields(): array
{
    $fields = [];
    foreach (Mage::getSingleton('adminhtml/config')->getSections()->children() as $section) {
        foreach ($section->groups?->children() ?? [] as $group) {
            foreach ($group->fields?->children() ?? [] as $field) {
                if (!str_contains((string) $field->source_model, 'source_email_template')) {
                    continue;
                }
                $fields[] = [
                    'path' => "{$section->getName()}/{$group->getName()}/{$field->getName()}",
                    'code' => "{$section->getName()}_{$group->getName()}_{$field->getName()}",
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

    foreach (emailTemplateSelectFields() as ['path' => $path, 'code' => $code]) {
        $default = (string) Mage::getStoreConfig($path);
        if ($default === '') {
            continue;
        }
        expect($registered)->toHaveKey($default, message: "config default {$path} points at unregistered template \"{$default}\"");
    }
});

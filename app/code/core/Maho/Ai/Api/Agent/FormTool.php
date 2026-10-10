<?php

/**
 * Local tools that read and correct the text of the form the administrator has open.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

namespace Maho\Ai\Api\Agent;

use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

/**
 * The panel sends the text fields of the open form with each message, with the changes that
 * are not saved. The server keeps them for the turn and gives them to the model only when it
 * calls the read tool, so a turn that does not need them costs no tokens. The edit tool checks
 * each correction against these values, and the panel makes it in the form after the answer.
 */
final class FormTool
{
    public const READ_NAME = 'admin_read_form';
    public const EDIT_NAME = 'admin_edit_text';
    public const NAMES = [self::READ_NAME, self::EDIT_NAME];
    public const MAX_EDITS = 50;

    /** @var list<array{id: string, name: string, label: string, value: string, changed: bool}> */
    private array $fields = [];

    /**
     * @param list<array{id: string, name: string, label: string, value: string, changed: bool}> $fields
     */
    public function store(array $fields): void
    {
        $this->fields = $fields;
    }

    public function hasForm(): bool
    {
        return $this->fields !== [];
    }

    public static function title(string $name): string
    {
        return $name === self::EDIT_NAME ? 'Correct text in the form' : 'Read the form';
    }

    public function readTool(): Tool
    {
        return ToolDefinition::create(
            new ExecutionReference(self::class, 'read'),
            self::READ_NAME,
            'Read the text fields of the form that the administrator has open, with the changes that are not saved. A get tool gives the saved record, which can be older. Use it before you check or correct the text of this form.',
            [
                'type' => 'object',
                'properties' => [
                    'fields' => [
                        'type' => 'array',
                        'description' => 'The labels or names of the fields to read. Omit it to read every field that has text.',
                        'items' => ['type' => 'string'],
                    ],
                ],
                'additionalProperties' => false,
            ],
            ['title' => self::title(self::READ_NAME), 'read_only' => true, 'destructive' => false, 'local' => true],
        );
    }

    public function editTool(): Tool
    {
        return ToolDefinition::create(
            new ExecutionReference(self::class, 'edit'),
            self::EDIT_NAME,
            'Correct text in the form that the administrator has open, after you answer. Each edit replaces one short piece of the current value of a field, as admin_read_form gives it, HTML included. The find text must occur exactly once in the field: add the words around it when it does not. The panel makes the corrections and does not save: the administrator checks the form and saves it.',
            [
                'type' => 'object',
                'properties' => [
                    'edits' => [
                        'type' => 'array',
                        'description' => 'The corrections, in order.',
                        'minItems' => 1,
                        'maxItems' => self::MAX_EDITS,
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'field' => ['type' => 'string', 'description' => 'The label or the name of the field.'],
                                'find' => ['type' => 'string', 'description' => 'The text to replace, exactly as in the current value.'],
                                'replace' => ['type' => 'string', 'description' => 'The new text.'],
                            ],
                            'required' => ['field', 'find', 'replace'],
                        ],
                    ],
                ],
                'required' => ['edits'],
                'additionalProperties' => false,
            ],
            ['title' => self::title(self::EDIT_NAME), 'read_only' => true, 'destructive' => false, 'local' => true],
        );
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, text: string}
     */
    public function read(array $arguments): array
    {
        $names = is_array($arguments['fields'] ?? null) ? array_filter($arguments['fields'], is_string(...)) : [];
        $indexes = [];
        foreach ($names as $name) {
            $index = $this->find($name);
            if ($index === null) {
                return ['ok' => false, 'text' => sprintf('No field "%s" in the form. Fields: %s.', $name, $this->fieldList())];
            }
            $indexes[$index] = true;
        }
        if ($indexes === []) {
            $indexes = array_filter(array_map(static fn(array $field): bool => $field['value'] !== '', $this->fields));
        }

        $parts = [];
        foreach (array_keys($indexes) as $index) {
            $field = $this->fields[$index];
            $parts[] = sprintf(
                "%s (field %s%s):\n%s",
                $field['label'],
                $field['name'],
                $field['changed'] ? ', with changes that are not saved' : '',
                $field['value'],
            );
        }

        return ['ok' => true, 'text' => $parts === [] ? 'The form has no text.' : implode("\n\n", $parts)];
    }

    /**
     * Check every edit against the current values. One edit that does not apply refuses the call.
     *
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, text: string, edits?: list<array{id: string, find: string, replace: string}>}
     */
    public function edit(array $arguments): array
    {
        $raw = $arguments['edits'] ?? null;
        if (!is_array($raw) || $raw === [] || count($raw) > self::MAX_EDITS) {
            return ['ok' => false, 'text' => sprintf('Pass one to %d edits, each with a field, a find text and a replace text.', self::MAX_EDITS)];
        }

        $values = array_column($this->fields, 'value');
        $edits = [];
        $errors = [];
        foreach (array_values($raw) as $i => $edit) {
            $name = is_array($edit) && is_string($edit['field'] ?? null) ? $edit['field'] : '';
            $find = is_array($edit) && is_string($edit['find'] ?? null) ? $edit['find'] : '';
            $replace = is_array($edit) && is_string($edit['replace'] ?? null) ? $edit['replace'] : null;
            $index = $this->find($name);
            if ($index === null) {
                $errors[] = sprintf('Edit %d: no field "%s" in the form. Fields: %s.', $i + 1, $name, $this->fieldList());
                continue;
            }
            if ($find === '' || $replace === null || $find === $replace) {
                $errors[] = sprintf('Edit %d needs a find text and a different replace text.', $i + 1);
                continue;
            }
            $count = substr_count($values[$index], $find);
            if ($count !== 1) {
                $errors[] = $count === 0
                    ? sprintf('Edit %d: "%s" is not in the field %s. Read the form again and copy the text exactly.', $i + 1, $find, $this->fields[$index]['label'])
                    : sprintf('Edit %d: "%s" is %d times in %s. Add the words around it, so that it occurs once.', $i + 1, $find, $count, $this->fields[$index]['label']);
                continue;
            }
            $values[$index] = str_replace($find, $replace, $values[$index]);
            $edits[] = ['id' => $this->fields[$index]['id'], 'find' => $find, 'replace' => $replace];
        }
        if ($errors !== []) {
            return ['ok' => false, 'text' => 'No edit was made. ' . implode(' ', $errors)];
        }

        foreach ($values as $index => $value) {
            if ($value !== $this->fields[$index]['value']) {
                $this->fields[$index]['value'] = $value;
                $this->fields[$index]['changed'] = true;
            }
        }

        return [
            'ok' => true,
            'text' => sprintf('The panel makes these %d corrections in the form when you finish your answer, and does not save. Tell the administrator in one sentence to check the form and save it. Do not call an update tool for the same text.', count($edits)),
            'edits' => $edits,
        ];
    }

    /** The field whose label, name or id is $name, case and a required-field asterisk ignored. */
    private function find(string $name): ?int
    {
        $wanted = self::normalize($name);
        if ($wanted === '') {
            return null;
        }
        foreach (['label', 'name', 'id'] as $key) {
            foreach ($this->fields as $index => $field) {
                if (self::normalize($field[$key]) === $wanted) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function fieldList(): string
    {
        return implode(', ', array_map(static fn(array $field): string => sprintf('%s (%s)', $field['label'], $field['name']), $this->fields));
    }

    private static function normalize(string $text): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', rtrim(trim($text), ' *'))));
    }
}

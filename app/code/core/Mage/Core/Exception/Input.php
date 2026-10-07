<?php

/**
 * A client fault with one or more problems, each about one field of the record.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Exception_Input extends Mage_Core_Exception
{
    /** @var list<array{field: string, message: string}> */
    private array $errors = [];

    /**
     * Record a problem of $field, the name of the model field, for example website_ids.
     */
    public function addError(string $field, string $message): static
    {
        $this->errors[] = ['field' => $field, 'message' => $message];
        $this->message = implode("\n", array_column($this->errors, 'message'));
        return $this;
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Throw this exception when it holds a problem. Its message has one line for each problem.
     *
     * @throws static
     */
    public function throwIfErrors(): void
    {
        if ($this->errors !== []) {
            throw $this;
        }
    }
}

<?php

/**
 * SPDX-FileCopyrightText: 2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Security_HtmlEscapedString implements Stringable
{
    /**
     * @param string[]|null $allowedTags
     */
    public function __construct(protected string $originalValue, protected ?array $allowedTags = null) {}

    /**
     * Get escaped html entities
     */
    #[\Override]
    public function __toString(): string
    {
        return (string) Mage::helper('core')->escapeHtml(
            $this->originalValue,
            $this->allowedTags,
        );
    }

    /**
     * Get un-escaped html entities
     */
    public function getUnescapedValue(): string
    {
        return $this->originalValue;
    }
}

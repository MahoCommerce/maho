<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_Security_HtmlEscapedString implements Stringable
{
    protected string $originalValue;

    /**
     * @var string[]|null
     */
    protected ?array $allowedTags;

    /**
     * @param string[]|null $allowedTags
     */
    public function __construct(string $originalValue, ?array $allowedTags = null)
    {
        $this->originalValue = $originalValue;
        $this->allowedTags = $allowedTags;
    }

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

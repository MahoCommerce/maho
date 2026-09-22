<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Block_Html_Date extends Mage_Core_Block_Template
{
    /**
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        // Convert value to ISO format for native date input
        $isoValue = '';
        if ($this->getValue()) {
            try {
                // Parse the existing value and convert to ISO format
                $dateTime = new DateTime($this->getValue());
                if ($this->getTime()) {
                    $isoValue = $dateTime->format(Mage_Core_Model_Locale::HTML5_DATETIME_FORMAT);
                } else {
                    $isoValue = $dateTime->format(Mage_Core_Model_Locale::DATE_FORMAT);
                }
            } catch (Exception) {
                // If parsing fails, use the original value
                $isoValue = $this->getValue();
            }
        }

        // Determine input type based on whether time is needed
        $inputType = $this->getTime() ? 'datetime-local' : 'date';

        $html = '<input type="' . $inputType . '" name="' . $this->getName() . '" id="' . $this->getId() . '" ';
        $html .= 'value="' . $this->escapeHtml($isoValue) . '" class="' . $this->getClass() . '" ';

        // Add min/max attributes if year range is specified
        $calendarYearsRange = $this->getYearsRange();
        if ($calendarYearsRange) {
            // Parse range like [2020, 2030]
            if (preg_match('/\[(\d{4}),\s*(\d{4})\]/', $calendarYearsRange, $matches)) {
                $yearStart = $matches[1];
                $yearEnd = $matches[2];
                if (!$this->getTime()) {
                    $html .= 'min="' . $yearStart . '-01-01" ';
                    $html .= 'max="' . $yearEnd . '-12-31" ';
                } else {
                    $html .= 'min="' . $yearStart . '-01-01T00:00" ';
                    $html .= 'max="' . $yearEnd . '-12-31T23:59" ';
                }
            }
        }

        $html .= $this->getExtraParams() . '>';

        return $html;
    }

    /**
     * @param null $index deprecated
     * @return string
     */
    public function getEscapedValue($index = null)
    {
        if ($this->getFormat() && $this->getValue()) {
            return date($this->getFormat(), strtotime($this->getValue()));
        }

        return htmlspecialchars($this->getValue());
    }

    /**
     * @return string
     */
    public function getHtml()
    {
        return $this->toHtml();
    }

    public function getClass(): ?string
    {
        $value = $this->getData('class');
        return $value === null ? null : (string) $value;
    }

    public function getExtraParams(): ?string
    {
        $value = $this->getData('extra_params');
        return $value === null ? null : (string) $value;
    }

    public function setExtraParams(?string $value): static
    {
        return $this->setData('extra_params', $value);
    }

    public function getFormat(): ?string
    {
        $value = $this->getData('format');
        return $value === null ? null : (string) $value;
    }

    public function setFormat(?string $value): static
    {
        return $this->setData('format', $value);
    }

    public function getName(): ?string
    {
        $value = $this->getData('name');
        return $value === null ? null : (string) $value;
    }

    public function setName(?string $value): static
    {
        return $this->setData('name', $value);
    }

    public function getTime(): ?bool
    {
        $value = $this->getData('time');
        return $value === null ? null : (bool) $value;
    }

    public function setTime(?bool $value = true): static
    {
        return $this->setData('time', $value);
    }

    public function setTitle(?string $value): static
    {
        return $this->setData('title', $value);
    }

    public function getValue(): ?string
    {
        $value = $this->getData('value');
        return $value === null ? null : (string) $value;
    }

    public function setValue(?string $value): static
    {
        return $this->setData('value', $value);
    }

    public function getYearsRange(): ?string
    {
        $value = $this->getData('years_range');
        return $value === null ? null : (string) $value;
    }

    public function setYearsRange(?string $value): static
    {
        return $this->setData('years_range', $value);
    }
}

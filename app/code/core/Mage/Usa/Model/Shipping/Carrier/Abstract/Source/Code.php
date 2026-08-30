<?php

/**
 * Admin options built from one of a carrier's own getCode() lists.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

declare(strict_types=1);

abstract class Mage_Usa_Model_Shipping_Carrier_Abstract_Source_Code
{
    /** Alias of the carrier singleton that owns the list. */
    protected string $_carrierModel;

    /** Key passed to the carrier's getCode(). */
    protected string $_codeType;

    protected bool $_sortByLabel = false;

    /**
     * @return array<int, array{value: int|string, label: string}>
     */
    public function toOptionArray(): array
    {
        $codes = Mage::getSingleton($this->_carrierModel)->getCode($this->_codeType) ?: [];

        $options = [];
        foreach ($codes as $value => $label) {
            $options[] = ['value' => $value, 'label' => $this->getLabel($value, $label)];
        }

        if ($this->_sortByLabel) {
            usort($options, fn(array $a, array $b): int => strcmp($a['label'], $b['label']));
        }

        return $options;
    }

    /** A carrier list already holds translated labels, so the default needs no helper call. */
    protected function getLabel(int|string $value, mixed $label): string
    {
        return (string) $label;
    }
}

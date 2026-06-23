<?php

/**
 * Base block for JSON-LD structured data output.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_StructuredData
 */

declare(strict_types=1);

abstract class Maho_StructuredData_Block_Jsonld_Abstract extends Mage_Core_Block_Template
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('structureddata/jsonld.phtml');
    }

    #[\Override]
    protected function _toHtml(): string
    {
        $helper = Mage::helper('structureddata');
        if (!$helper->isEnabled() || !$this->isTypeEnabled()) {
            return '';
        }

        $data = $this->getStructuredData();
        if (empty($data)) {
            return '';
        }

        $this->setStructuredDataArray($data);
        return parent::_toHtml();
    }

    /**
     * Build the schema.org node(s) for this block. Return an empty array to render nothing.
     *
     * @return array<string, mixed>
     */
    abstract protected function getStructuredData(): array;

    /**
     * Whether this specific schema type is enabled in configuration.
     */
    abstract protected function isTypeEnabled(): bool;
}

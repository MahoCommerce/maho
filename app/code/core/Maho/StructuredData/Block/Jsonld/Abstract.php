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
    /** Event suffix: dispatches maho_structureddata_<object>_data. Empty string disables the event. */
    protected string $_eventObject = '';

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
        if (!$helper->isEnabled()) {
            return '';
        }

        $data = $this->getStructuredData();
        if (empty($data)) {
            return '';
        }

        $data = $this->_dispatchDataEvent($data);
        if (empty($data)) {
            return '';
        }

        $this->setStructuredDataArray($data);
        return parent::_toHtml();
    }

    /**
     * Let other modules enrich or alter the graph via maho_structureddata_<object>_data.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function _dispatchDataEvent(array $data): array
    {
        if ($this->_eventObject === '') {
            return $data;
        }

        $transport = new Maho\DataObject(['structured_data' => $data]);
        Mage::dispatchEvent("maho_structureddata_{$this->_eventObject}_data", $this->_getEventData() + [
            'block' => $this,
            'transport' => $transport,
        ]);

        return (array) $transport->getStructuredData();
    }

    /**
     * Extra payload passed to the data event (e.g. ['product' => $product]).
     *
     * @return array<string, mixed>
     */
    protected function _getEventData(): array
    {
        return [];
    }

    /**
     * Build the schema.org node(s) for this block. Return an empty array to render nothing.
     *
     * @return array<string, mixed>
     */
    abstract protected function getStructuredData(): array;
}

<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

abstract class Mage_Adminhtml_Block_Dashboard_Abstract extends Mage_Adminhtml_Block_Widget
{
    protected ?Mage_Adminhtml_Helper_Dashboard_Abstract $_dataHelper = null;

    public function getCollection(): \Maho\Data\Collection
    {
        return $this->getDataHelper()->getCollection();
    }

    public function getCount(): int|bool
    {
        return $this->getDataHelper()->getCount();
    }

    public function getDataHelper(): ?Mage_Adminhtml_Helper_Dashboard_Abstract
    {
        return $this->_dataHelper;
    }

    public function setDataHelper(Mage_Adminhtml_Helper_Dashboard_Abstract $dataHelper): static
    {
        $this->_dataHelper = $dataHelper;
        return $this;
    }

    protected function _prepareData(): void {}

    #[\Override]
    protected function _prepareLayout()
    {
        $this->_prepareData();
        return parent::_prepareLayout();
    }
}

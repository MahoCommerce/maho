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
    protected $_dataHelperName = null;

    public function getCollection()
    {
        return $this->getDataHelper()->getCollection();
    }

    public function getCount()
    {
        return $this->getDataHelper()->getCount();
    }

    public function getDataHelper()
    {
        return $this->helper($this->getDataHelperName());
    }

    public function getDataHelperName()
    {
        return $this->_dataHelperName;
    }

    public function setDataHelperName($dataHelperName)
    {
        $this->_dataHelperName = $dataHelperName;
        return $this;
    }

    protected function _prepareData()
    {
        return $this;
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->_prepareData();
        return parent::_prepareLayout();
    }
}

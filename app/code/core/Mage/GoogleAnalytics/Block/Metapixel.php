<?php

class Mage_GoogleAnalytics_Block_Metapixel extends Mage_Core_Block_Template
{
    protected function _isAvailable(): bool
    {
        return Mage::helper('googleanalytics')->isMetaPixelEnabled();
    }

    #[\Override]
    protected function _toHtml(): string
    {
        if (!$this->_isAvailable()) {
            return '';
        }
        return parent::_toHtml();
    }
}

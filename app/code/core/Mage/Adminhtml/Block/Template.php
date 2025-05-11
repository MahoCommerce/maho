<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Template extends Mage_Core_Block_Template
{
    /**
     * @return string
     */
    #[\Override]
    protected function _getUrlModelClass()
    {
        return 'adminhtml/url';
    }

    /**
     * Retrieve Session Form Key
     *
     * @return string
     */
    #[\Override]
    public function getFormKey()
    {
        return Mage::getSingleton('core/session')->getFormKey();
    }

    /**
     * @param string $moduleName Full module name
     * @return bool
     * @deprecated
     * @see Mage_Core_Block_Template::isModuleOutputEnabled()
     */
    public function isOutputEnabled($moduleName = null)
    {
        return $this->isModuleOutputEnabled($moduleName);
    }

    /**
     * Prepare html output
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        Mage::dispatchEvent('adminhtml_block_html_before', ['block' => $this]);
        return parent::_toHtml();
    }

    /**
     * Create button and return its html
     *
     * @param string $label
     * @param string $onclick
     * @param string $class
     * @param string $id
     * @param string $icon
     * @return string
     */
    public function getButtonHtml($label, $onclick = '', $class = '', $id = null, $icon = null)
    {
        return $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData([
                'label'     => $label,
                'onclick'   => $onclick,
                'class'     => $class,
                'type'      => 'button',
                'id'        => $id,
                'icon'      => $icon,
            ])
            ->toHtml();
    }

    /**
     * Deleting script tags from string
     *
     * @param string $html
     * @return string
     */
    public function maliciousCodeFilter($html)
    {
        return Mage::getSingleton('core/input_filter_maliciousCode')->filter($html);
    }
}

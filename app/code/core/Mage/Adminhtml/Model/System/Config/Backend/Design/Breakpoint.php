<?php

/**
 * Checks the width of a breakpoint preset before it is saved.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Model_System_Config_Backend_Design_Breakpoint extends Mage_Core_Model_Config_Data
{
    /**
     * A <picture> uses its first <source> that matches, and the editor writes the sources from the
     * narrowest preset to the widest. So each preset must stay wider than the preset before it.
     *
     * @throws Mage_Core_Exception
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        $helper = Mage::helper('adminhtml');
        $width = Mage_Core_Model_Design_Breakpoints::toWidth($this->getValue());
        if ($width === null) {
            Mage::throwException($helper->__(
                'Enter a whole number between %s and %s.',
                Mage_Core_Model_Design_Breakpoints::MIN_WIDTH,
                Mage_Core_Model_Design_Breakpoints::MAX_WIDTH,
            ));
        }
        $this->setValue((string) $width);

        $previous = null;
        foreach (array_keys(Mage_Core_Model_Design_Breakpoints::PRESETS) as $code) {
            $current = $code === $this->getField() ? $width : $this->widthAfterSave($code);
            if ($current === null) {
                continue;
            }
            if ($previous !== null && $current <= $previous) {
                Mage::throwException($helper->__('Each breakpoint must be wider than the breakpoint above it.'));
            }
            $previous = $current;
        }

        return parent::_beforeSave();
    }

    /**
     * The width that preset $code has in this scope after the save: the posted value, or the value
     * of the parent scope when the field uses it.
     */
    private function widthAfterSave(string $code): ?int
    {
        $posted = $this->getFieldsetDataValue($code);
        if ($posted !== null) {
            return Mage_Core_Model_Design_Breakpoints::toWidth($posted);
        }

        $path = Mage_Core_Model_Design_Breakpoints::CONFIG_PATH . '/' . $code;
        $inherited = $this->getScope() === 'stores'
            ? Mage::app()->getStore((int) $this->getScopeId())->getWebsite()->getConfig($path)
            : (string) Mage::getConfig()->getNode('default/' . $path);

        return Mage_Core_Model_Design_Breakpoints::toWidth($inherited);
    }
}

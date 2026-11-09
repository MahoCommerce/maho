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
        $width = Mage_Core_Model_Design_Breakpoints::toWidth($this->getValue());
        if ($width === null) {
            Mage::throwException(Mage::helper('adminhtml')->__(
                'Enter a whole number between %s and %s.',
                Mage_Core_Model_Design_Breakpoints::MIN_WIDTH,
                Mage_Core_Model_Design_Breakpoints::MAX_WIDTH,
            ));
        }
        $this->setValue((string) $width);
        $this->checkOrder($width);

        return parent::_beforeSave();
    }

    /**
     * The save deletes the value of a field that goes back to the value of the parent scope. The
     * presets must stay in order after that too.
     *
     * @throws Mage_Core_Exception
     * @return $this
     */
    #[\Override]
    protected function _beforeDelete()
    {
        $this->checkOrder($this->inheritedWidth((string) $this->getField()));

        return parent::_beforeDelete();
    }

    /**
     * Check the order of the presets in this scope, and in each website and store view below it that
     * takes a value from it, when this field gets the width $width.
     *
     * @throws Mage_Core_Exception
     */
    private function checkOrder(?int $width): void
    {
        $widths = [];
        foreach (array_keys(Mage_Core_Model_Design_Breakpoints::PRESETS) as $code) {
            $widths[$code] = $code === $this->getField() ? $width : $this->widthAfterSave($code);
        }

        $helper = Mage::helper('adminhtml');
        if (!self::isInOrder($widths)) {
            Mage::throwException($helper->__('Each breakpoint must be wider than the breakpoint above it.'));
        }
        foreach ($this->getChildScopeWidths($widths) as $name => $childWidths) {
            if (!self::isInOrder($childWidths)) {
                Mage::throwException($helper->__(
                    'After this change, the breakpoints of "%s" are not in order. Change the breakpoints of "%s" first.',
                    $name,
                    $name,
                ));
            }
        }
    }

    /**
     * @param array<string, int|null> $widths
     */
    private static function isInOrder(array $widths): bool
    {
        $previous = null;
        foreach ($widths as $width) {
            if ($width === null) {
                continue;
            }
            if ($previous !== null && $width <= $previous) {
                return false;
            }
            $previous = $width;
        }
        return true;
    }

    /**
     * The width that preset $code has in this scope after the save: the value of the parent scope when
     * the field goes back to it, else the posted value, else the value that the scope has now.
     */
    private function widthAfterSave(string $code): ?int
    {
        $groups = $this->getData('groups');
        if (!empty($groups[$this->getGroupId()]['fields'][$code]['inherit'])) {
            return $this->inheritedWidth($code);
        }

        $posted = $this->getFieldsetDataValue($code);
        if ($posted !== null) {
            return Mage_Core_Model_Design_Breakpoints::toWidth($posted);
        }

        $path = Mage_Core_Model_Design_Breakpoints::CONFIG_PATH . '/' . $code;
        $current = match ($this->getScope()) {
            'stores' => Mage::getStoreConfig($path, (int) $this->getScopeId()),
            'websites' => Mage::app()->getWebsite((int) $this->getScopeId())->getConfig($path),
            default => (string) Mage::getConfig()->getNode('default/' . $path),
        };
        return Mage_Core_Model_Design_Breakpoints::toWidth($current);
    }

    /**
     * The width of preset $code in the parent scope of this scope.
     */
    private function inheritedWidth(string $code): ?int
    {
        $path = Mage_Core_Model_Design_Breakpoints::CONFIG_PATH . '/' . $code;
        $inherited = $this->getScope() === 'stores'
            ? Mage::app()->getStore((int) $this->getScopeId())->getWebsite()->getConfig($path)
            : (string) Mage::getConfig()->getNode('default/' . $path);

        return Mage_Core_Model_Design_Breakpoints::toWidth($inherited);
    }

    /**
     * The widths of the presets in each website and store view below this scope, by its name, when this
     * scope has the widths $widths. A website or a store view keeps the values that it sets itself.
     *
     * @param array<string, int|null> $widths
     * @return array<string, array<string, int|null>>
     */
    private function getChildScopeWidths(array $widths): array
    {
        $scope = $this->getScope();
        if ($scope !== 'default' && $scope !== 'websites') {
            return [];
        }

        $overrides = [];
        $rows = Mage::getResourceModel('core/config_data_collection')
            ->addFieldToFilter('path', ['like' => Mage_Core_Model_Design_Breakpoints::CONFIG_PATH . '/%'])
            ->addFieldToFilter('scope', ['in' => ['websites', 'stores']]);
        foreach ($rows as $row) {
            $code = substr((string) $row->getPath(), strlen(Mage_Core_Model_Design_Breakpoints::CONFIG_PATH) + 1);
            if (isset(Mage_Core_Model_Design_Breakpoints::PRESETS[$code])) {
                $overrides[$row->getScope()][(int) $row->getScopeId()][$code]
                    = Mage_Core_Model_Design_Breakpoints::toWidth($row->getValue());
            }
        }

        $websites = $scope === 'default'
            ? Mage::app()->getWebsites()
            : [Mage::app()->getWebsite((int) $this->getScopeId())];

        $result = [];
        foreach ($websites as $website) {
            $websiteWidths = $widths;
            if ($scope === 'default') {
                $websiteWidths = array_replace($widths, $overrides['websites'][(int) $website->getId()] ?? []);
                $result[(string) $website->getName()] = $websiteWidths;
            }
            foreach ($website->getStores() as $store) {
                $result[$website->getName() . ' / ' . $store->getName()] = array_replace($websiteWidths, $overrides['stores'][(int) $store->getId()] ?? []);
            }
        }
        return $result;
    }
}

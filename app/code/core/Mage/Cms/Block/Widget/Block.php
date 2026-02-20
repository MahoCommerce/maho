<?php

/**
 * Maho
 *
 * @package    Mage_Cms
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method int getBlockId()
 * @method $this setText(string $value)
 */
class Mage_Cms_Block_Widget_Block extends Mage_Core_Block_Template implements Mage_Widget_Block_Interface
{
    /**
     * Initialize cache
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        /*
        * setting cache to save the cms block
        */
        $this->setCacheTags([Mage_Cms_Model_Block::CACHE_TAG]);
        $this->setCacheLifetime(false);
    }

    /**
     * Storage for used widgets
     *
     * @var array
     */
    protected static $_widgetUsageMap = [];

    /**
     * Prepare block text and determine whether block output enabled or not
     * Prevent blocks recursion if needed
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeToHtml()
    {
        parent::_beforeToHtml();
        $blockId = $this->getData('block_id');
        $blockHash = static::class . $blockId;

        if (isset(self::$_widgetUsageMap[$blockHash])) {
            return $this;
        }
        self::$_widgetUsageMap[$blockHash] = true;

        if ($blockId) {
            $block = Mage::getModel('cms/block')
                ->setStoreId(Mage::app()->getStore()->getId())
                ->load($blockId);
            if ($block->getIsActive()) {
                $helper = Mage::helper('cms');
                $processor = $helper->getBlockTemplateProcessor();
                if ($this->isRequestFromAdminArea()) {
                    $this->setText($processor->filter(
                        Mage::getSingleton('core/input_filter_maliciousCode')->filter($block->getContent()),
                    ));
                } else {
                    $this->setText($processor->filter($block->getContent()));
                }
                $this->addModelTags($block);
            }
        }

        unset(self::$_widgetUsageMap[$blockHash]);
        return $this;
    }

    /**
     * Retrieve values of properties that unambiguously identify unique content
     *
     * @return array
     */
    #[\Override]
    public function getCacheKeyInfo()
    {
        $result = parent::getCacheKeyInfo();
        $blockId = $this->getBlockId();
        if ($blockId) {
            $result[] = $blockId;
        }
        return $result;
    }

    /**
     * Check is request goes from admin area
     *
     * @return bool
     */
    public function isRequestFromAdminArea()
    {
        return $this->getRequest()->getRouteName() === Mage_Core_Model_App_Area::AREA_ADMINHTML;
    }
}

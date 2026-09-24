<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

class Mage_Cms_Block_Block extends Mage_Core_Block_Abstract
{
    /**
     * Initialize cache
     */
    #[\Override]
    protected function _construct()
    {
        /*
        * setting cache to save the cms block
        */
        $this->setCacheTags([Mage_Cms_Model_Block::CACHE_TAG]);
        $this->setCacheLifetime(false);
    }

    /**
     * Prepare Content HTML
     *
     * @return string
     * @throws Mage_Core_Model_Store_Exception
     * @throws Exception
     */
    #[\Override]
    protected function _toHtml()
    {
        $blockId = $this->getBlockId();
        $html = '';
        if ($blockId) {
            $block = Mage::getModel('cms/block')
                ->setStoreId(Mage::app()->getStore()->getId())
                ->load($blockId);
            if ($block->getIsActive()) {
                /** @var Mage_Cms_Helper_Data $helper */
                $helper = Mage::helper('cms');
                $processor = $helper->getBlockTemplateProcessor();
                $html = $processor->filter($block->getContent());
                $this->addModelTags($block);
            }
        }
        return $html;
    }

    /**
     * Retrieve values of properties that unambiguously identify unique content
     *
     * @return array
     * @throws Mage_Core_Model_Store_Exception
     */
    #[\Override]
    public function getCacheKeyInfo()
    {
        $blockId = $this->getBlockId();
        if ($blockId) {
            $result = [
                'CMS_BLOCK',
                $blockId,
                Mage::app()->getStore()->getCode(),
            ];
        } else {
            $result = parent::getCacheKeyInfo();
        }
        return $result;
    }

    public function getBlockId(): int|string|null
    {
        return $this->getData('block_id');
    }

    public function setBlockId(int|string|null $value): static
    {
        return $this->setData('block_id', $value);
    }
}

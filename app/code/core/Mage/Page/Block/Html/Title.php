<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Page_Block_Html_Title extends Mage_Core_Block_Template
{
    protected ?string $title = null;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('page/html/title.phtml');
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getLinksBlock(): Mage_Page_Block_Template_Links
    {
        /** @var Mage_Page_Block_Template_Links */
        $block = $this->getLayout()->getBlock('title.links');
        if ($block === false) {
            $block = $this->getLayout()->createBlock('page/template_links', 'title.links');
            $this->setChild('title_links', $block);
        }
        return $block;
    }
}

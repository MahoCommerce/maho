<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Widget_Accordion_Item extends Mage_Adminhtml_Block_Widget
{
    protected ?Mage_Adminhtml_Block_Widget_Accordion $_accordion = null;

    public function setAccordion(Mage_Adminhtml_Block_Widget_Accordion $accordion): static
    {
        $this->_accordion = $accordion;
        return $this;
    }

    public function getTarget(): string
    {
        return ($this->getAjax()) ? 'ajax' : '';
    }

    public function getTitle(): string
    {
        $title  = $this->getData('title');
        $url    = $this->getContentUrl() ?: '#';
        $title  = '<a href="' . $url . '" class="' . $this->getTarget() . '">' . $title . '</a>';

        return $title;
    }

    public function getContent(): ?string
    {
        $content = $this->getData('content');
        if (is_string($content)) {
            return $content;
        }
        if ($content instanceof Mage_Core_Block_Abstract) {
            return $content->toHtml();
        }
        return null;
    }

    public function getClass(): ?string
    {
        $class = $this->getData('class');
        if ($this->getOpen()) {
            $class .= ' open';
        }
        return $class;
    }

    #[\Override]
    protected function _toHtml()
    {
        $content = $this->getContent();
        $html = '<dt id="dt-' . $this->getHtmlId() . '" class="' . $this->getClass() . '">';
        $html .= $this->getTitle();
        $html .= '</dt>';
        $html .= '<dd id="dd-' . $this->getHtmlId() . '" class="' . $this->getClass() . '">';
        $html .= $content;
        $html .= '</dd>';
        return $html;
    }
}

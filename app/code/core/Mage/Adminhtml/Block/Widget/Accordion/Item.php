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

class Mage_Adminhtml_Block_Widget_Accordion_Item extends Mage_Adminhtml_Block_Widget
{
    protected $_accordion;

    public function setAccordion($accordion)
    {
        $this->_accordion = $accordion;
        return $this;
    }

    public function getTarget()
    {
        return $this->getAjax() ? 'ajax' : '';
    }

    public function getContent()
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

    #[\Override]
    protected function _toHtml()
    {
        $attrs = new Varien_Object([
            'id' => $this->getHtmlId(),
        ]);
        if ($this->getContentUrl()) {
            $attrs['data-url'] = $this->getContentUrl();
        }
        if ($this->getTarget()) {
            $attrs['data-target'] = $this->getTarget();
        }
        if ($this->getOpen()) {
            $attrs['open'] = '';
        }

        return <<<HTML
            <details {$attrs->serialize()}>
                <summary id="dt-{$this->getHtmlId()}" class="{$this->getClass()}">
                    <h4>{$this->getTitle()}</h4>
                </summary>
                <div id="dd-{$this->getHtmlId()}" class="{$this->getClass()}">
                    {$this->getContent()}
                </div>
            </details>
        HTML;
    }
}

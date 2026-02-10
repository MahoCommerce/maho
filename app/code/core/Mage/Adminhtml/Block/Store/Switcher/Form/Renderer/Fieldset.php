<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Adminhtml_Block_Store_Switcher_Form_Renderer_Fieldset extends Mage_Adminhtml_Block_Template implements \Maho\Data\Form\Element\Renderer\RendererInterface
{
    /**
     * Form element which re-rendering
     *
     * @var \Maho\Data\Form\Element\Fieldset
     */
    protected $_element;

    #[\Override]
    protected function _construct()
    {
        $this->setTemplate('store/switcher/form/renderer/fieldset.phtml');
    }

    /**
     * Retrieve an element
     *
     * @return \Maho\Data\Form\Element\Fieldset
     */
    public function getElement()
    {
        return $this->_element;
    }

    /**
     * Render element
     *
     * @return string
     */
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $this->_element = $element;
        return $this->toHtml();
    }
}

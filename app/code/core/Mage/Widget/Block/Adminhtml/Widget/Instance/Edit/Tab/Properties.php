<?php

/**
 * Maho
 *
 * @package    Mage_Widget
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Widget Instance Properties tab block
 *
 * @method $this setWidgetType(string $value)
 * @method $this setWidgetValues(array $value)
 */
class Mage_Widget_Block_Adminhtml_Widget_Instance_Edit_Tab_Properties extends Mage_Widget_Block_Adminhtml_Widget_Options implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Prepare label for tab
     *
     * @return string
     */
    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('widget')->__('Widget Options');
    }

    /**
     * Prepare title for tab
     *
     * @return string
     */
    #[\Override]
    public function getTabTitle()
    {
        return Mage::helper('widget')->__('Widget Options');
    }

    /**
     * Returns status flag about this tab can be showen or not
     *
     * @return bool
     */
    #[\Override]
    public function canShowTab()
    {
        return $this->getWidgetInstance()->isCompleteToCreate();
    }

    /**
     * Returns status flag about this tab hidden or not
     *
     * @return false
     */
    #[\Override]
    public function isHidden()
    {
        return false;
    }

    /**
     * Getter
     *
     * @return Mage_Widget_Model_Widget_Instance
     */
    public function getWidgetInstance()
    {
        return Mage::registry('current_widget_instance');
    }

    /**
     * Prepare block children and data.
     * Set widget type and widget parameters if available
     */
    #[\Override]
    protected function _prepareLayout()
    {
        $this->setWidgetType($this->getWidgetInstance()->getType())
            ->setWidgetValues($this->getWidgetInstance()->getWidgetParameters());
        return parent::_prepareLayout();
    }

    /**
     * Add field to Options form based on option configuration
     *
     * @param \Maho\DataObject $parameter
     * @return \Maho\Data\Form\Element\AbstractElement|false
     */
    #[\Override]
    protected function _addField($parameter)
    {
        if ($parameter->getKey() != 'template') {
            return parent::_addField($parameter);
        }
        return false;
    }
}

<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Review_Add extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_controller = 'review';
        $this->_mode = 'add';

        $this->_updateButton('save', 'label', Mage::helper('review')->__('Save Review'));
        $this->_updateButton('save', 'id', 'save_button');

        $this->_updateButton('reset', 'id', 'reset_button');

        $this->_formInitScripts[] = <<<JS
            const review = new ReviewEditForm({
                ratingItemsUrl: '{$this->getUrl('*/*/ratingItems')}',
                productEditUrl: '{$this->getUrl('*/catalog_product/edit')}',
            });
        JS;
        $this->_formScripts[] = <<<JS
            review.hideForm();
        JS;
    }

    /**
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        return Mage::helper('review')->__('Add New Review');
    }
}

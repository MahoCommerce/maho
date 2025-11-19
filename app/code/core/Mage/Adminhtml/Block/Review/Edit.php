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

class Mage_Adminhtml_Block_Review_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_objectId = 'id';
        $this->_controller = 'review';

        $this->_updateButton('save', 'label', Mage::helper('review')->__('Save Review'));
        $this->_updateButton('save', 'id', 'save_button');
        $this->_updateButton('delete', 'label', Mage::helper('review')->__('Delete Review'));

        if ($this->getRequest()->getParam('productId', false)) {
            $this->_updateButton(
                'back',
                'onclick',
                Mage::helper('core/js')->getSetLocationJs(
                    $this->getUrl('*/catalog_product/edit', [
                        'id' => $this->getRequest()->getParam('productId', false),
                    ]),
                ),
            );
        }

        if ($this->getRequest()->getParam('customerId', false)) {
            $this->_updateButton(
                'back',
                'onclick',
                Mage::helper('core/js')->getSetLocationJs(
                    $this->getUrl('*/customer/edit', [
                        'id' => $this->getRequest()->getParam('customerId', false),
                    ]),
                ),
            );
        }

        if ($this->getRequest()->getParam('ret', false) == 'pending') {
            $this->_updateButton(
                'back',
                'onclick',
                Mage::helper('core/js')->getSetLocationJs($this->getUrl('*/*/pending')),
            );
            $this->_updateButton(
                'delete',
                'onclick',
                Mage::helper('core/js')->getDeleteConfirmJs(
                    $this->getUrl('*/*/delete', [
                        $this->_objectId => $this->getRequest()->getParam($this->_objectId),
                        'ret' => 'pending',
                    ]),
                ),
            );
            Mage::register('ret', 'pending');
        }

        if ($this->getRequest()->getParam($this->_objectId)) {
            $reviewData = Mage::getModel('review/review')
                ->load($this->getRequest()->getParam($this->_objectId));
            Mage::register('review_data', $reviewData);
        }

        $this->_formInitScripts[] = <<<JS
            const review = new ReviewEditForm({
                ratingItemsUrl: '{$this->getUrl('*/*/ratingItems', ['_current' => true])}',
            });
        JS;
    }

    /**
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        if (Mage::registry('review_data') && Mage::registry('review_data')->getId()) {
            return Mage::helper('review')->__("Edit Review '%s'", $this->escapeHtml(Mage::registry('review_data')->getTitle()));
        }
        return Mage::helper('review')->__('New Review');
    }
}

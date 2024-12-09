<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml tags page content block
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Tag extends Mage_Adminhtml_Block_Template
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('tag/index.phtml');
    }

    #[\Override]
    public function _beforeToHtml()
    {
        $this->assign('createUrl', $this->getUrl('*/tag/new'));
        $this->setChild('tag_frame', $this->getLayout()->createBlock('adminhtml/tag_tab_all', 'tag.frame'));
        return parent::_beforeToHtml();
    }
}

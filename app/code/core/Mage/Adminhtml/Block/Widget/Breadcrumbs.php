<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Widget_Breadcrumbs extends Mage_Adminhtml_Block_Template
{
    /**
     * breadcrumbs links
     *
     * @var array
     */
    protected $_links = [];

    /**
     * Mage_Adminhtml_Block_Widget_Breadcrumbs constructor.
     */
    public function __construct()
    {
        $this->setTemplate('widget/breadcrumbs.phtml');
        $this->addLink(Mage::helper('adminhtml')->__('Home'), Mage::helper('adminhtml')->__('Home'), $this->getUrl('*'));
    }

    /**
     * @param string $label
     * @param string|null $title
     * @param string|null $url
     * @return $this
     */
    public function addLink($label, $title = null, $url = null)
    {
        if (empty($title)) {
            $title = $label;
        }
        $this->_links[] = [
            'label' => $label,
            'title' => $title,
            'url'   => $url,
        ];
        return $this;
    }

    #[\Override]
    protected function _beforeToHtml()
    {
        // TODO - Moved to Beta 2, no breadcrumbs displaying in Beta 1
        // $this->assign('links', $this->_links);
        return parent::_beforeToHtml();
    }
}

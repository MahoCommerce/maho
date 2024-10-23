<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Website switcher block
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Website_Switcher extends Mage_Adminhtml_Block_Template
{
    /**
     * Name of website variable
     *
     * @var string
     */
    protected $_websiteVarName = 'website';

    /**
     * @var bool
     */
    protected $_hasDefaultOption = true;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('website/switcher.phtml');
        $this->setUseConfirm(true);
        $this->setUseAjax(true);
        $this->setLabel($this->__('Choose Website:'));
        $this->setDefaultWebsiteName($this->__('All Websites'));
    }

    /**
     * @return Mage_Core_Model_Resource_Website_Collection
     * @throws Mage_Core_Exception
     * @deprecated
     */
    public function getWebsiteCollection()
    {
        $collection = Mage::getModel('core/website')->getResourceCollection();

        $websiteIds = $this->getWebsiteIds();
        if (!is_null($websiteIds)) {
            $collection->addIdFilter($this->getWebsiteIds());
        }

        return $collection->load();
    }

    /**
     * Get websites
     *
     * @return array
     */
    public function getWebsites()
    {
        $websites = Mage::app()->getWebsites();
        if ($websiteIds = $this->getWebsiteIds()) {
            foreach (array_keys($websites) as $websiteId) {
                if (!in_array($websiteId, $websiteIds)) {
                    unset($websites[$websiteId]);
                }
            }
        }
        return $websites;
    }

    /**
     * @return string
     */
    public function getSwitchUrl()
    {
        if ($url = $this->getData('switch_url')) {
            return $url;
        }
        return $this->getUrl('*/*/*', ['_current' => true, $this->_websiteVarName => null]);
    }

    /**
     * @param string $varName
     * @return $this
     */
    public function setWebsiteVarName($varName)
    {
        $this->_websiteVarName = $varName;
        return $this;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getWebsiteId()
    {
        return $this->getRequest()->getParam($this->_websiteVarName);
    }

    /**
     * @return bool
     */
    public function isShow()
    {
        return !Mage::app()->isSingleStoreMode();
    }

    /**
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if (!Mage::app()->isSingleStoreMode()) {
            return parent::_toHtml();
        }
        return '';
    }

    /**
     * Set/Get whether the switcher should show default option
     *
     * @param bool $hasDefaultOption
     * @return bool
     */
    public function hasDefaultOption($hasDefaultOption = null)
    {
        if ($hasDefaultOption !== null) {
            $this->_hasDefaultOption = $hasDefaultOption;
        }
        return $this->_hasDefaultOption;
    }
}

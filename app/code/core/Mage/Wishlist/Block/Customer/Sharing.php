<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Wishlist
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Wishlist customer sharing block
 *
 * @category   Mage
 * @package    Mage_Wishlist
 */
class Mage_Wishlist_Block_Customer_Sharing extends Mage_Core_Block_Template
{
    /**
     * Entered Data cache
     *
     * @var array|null
     */
    protected $_enteredData = null;

    /**
     * Prepare Global Layout
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareLayout()
    {
        $headBlock = $this->getLayout()->getBlock('head');
        if ($headBlock) {
            $headBlock->setTitle($this->__('Wishlist Sharing'));
        }
        return $this;
    }

    /**
     * Retrieve Send Form Action URL
     *
     * @return string
     */
    public function getSendUrl()
    {
        return $this->getUrl('*/*/send');
    }

    /**
     * Retrieve Entered Data by key
     *
     * @param string $key
     * @return mixed
     */
    public function getEnteredData($key)
    {
        if ($this->_enteredData === null) {
            $this->_enteredData = Mage::getSingleton('wishlist/session')
                ->getData('sharing_form', true);
        }

        if (!$this->_enteredData || !isset($this->_enteredData[$key])) {
            return null;
        } else {
            return $this->escapeHtml($this->_enteredData[$key]);
        }
    }

    /**
     * Retrieve back button url
     *
     * @return string
     */
    public function getBackUrl()
    {
        return $this->getUrl('*/*/index');
    }
}

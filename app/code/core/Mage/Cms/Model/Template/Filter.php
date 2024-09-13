<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Cms
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Cms Template Filter Model
 *
 * @category   Mage
 * @package    Mage_Cms
 */
class Mage_Cms_Model_Template_Filter extends Mage_Core_Model_Email_Template_Filter
{
    /**
     * Whether to allow SID in store directive: AUTO
     *
     * @var bool
     */
    protected $_useSessionInUrl = null;

    /**
     * Setter whether SID is allowed in store directive
     *
     * @param bool $flag
     * @return $this
     */
    #[\Override]
    public function setUseSessionInUrl($flag)
    {
        $this->_useSessionInUrl = (bool)$flag;
        return $this;
    }
}

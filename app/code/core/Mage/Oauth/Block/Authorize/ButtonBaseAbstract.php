<?php

/**
 * Maho
 *
 * @package    Mage_Oauth
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Oauth_Block_Authorize_ButtonBaseAbstract extends Mage_Oauth_Block_Authorize_Abstract
{
    /**
     * Get confirm url path
     *
     * @return string
     */
    abstract public function getConfirmUrlPath();

    /**
     * Get reject url path
     *
     * @return string
     */
    abstract public function getRejectUrlPath();

    /**
     * Retrieve reject authorization url
     *
     * @return string
     */
    public function getConfirmUrl()
    {
        return $this->getUrl($this->getConfirmUrlPath() . ($this->getIsSimple() ? 'Simple' : ''));
    }

    /**
     * Retrieve reject authorization url
     *
     * @return string
     */
    public function getRejectUrl()
    {
        return $this->getUrl($this->getRejectUrlPath() . ($this->getIsSimple() ? 'Simple' : ''));
    }
}

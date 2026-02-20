<?php

/**
 * Maho
 *
 * @package    Mage_Admin
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Admin_Model_Redirectpolicy
{
    /**
     * @var Mage_Adminhtml_Model_Url
     */
    protected $_urlModel;

    /**
     * @param array $parameters ['urlModel' => object]
     */
    public function __construct($parameters = [])
    {
        $this->_urlModel = (empty($parameters['urlModel'])) ?
            Mage::getModel('adminhtml/url') : $parameters['urlModel'];
    }

    /**
     * Redirect to startup page after logging in if request contains any params (except security key)
     *
     * @param string|null $alternativeUrl
     * @return null|string
     */
    public function getRedirectUrl(
        Mage_Admin_Model_User $user,
        ?Mage_Core_Controller_Request_Http $request = null,
        $alternativeUrl = null,
    ) {
        if (empty($request)) {
            return null;
        }
        $countRequiredParams = ($this->_urlModel->useSecretKey()
            && $request->getParam(Mage_Adminhtml_Model_Url::SECRET_KEY_PARAM_NAME)) ? 1 : 0;
        $countGetParams = count($request->getUserParams()) + count($request->getQuery());

        return ($countGetParams > $countRequiredParams) ?
            $this->_urlModel->getUrl($user->getStartupPageUrl()) : $alternativeUrl;
    }
}

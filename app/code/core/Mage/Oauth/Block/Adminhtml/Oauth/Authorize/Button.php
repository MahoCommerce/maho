<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Oauth
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * OAuth authorization block with auth buttons
 *
 * @category   Mage
 * @package    Mage_Oauth
 */
class Mage_Oauth_Block_Adminhtml_Oauth_Authorize_Button extends Mage_Oauth_Block_Authorize_ButtonBaseAbstract
{
    /**
     * Retrieve confirm authorization url path
     *
     * @return string
     */
    #[\Override]
    public function getConfirmUrlPath()
    {
        return 'adminhtml/oauth_authorize/confirm';
    }

    /**
     * Retrieve reject authorization url path
     *
     * @return string
     */
    #[\Override]
    public function getRejectUrlPath()
    {
        return 'adminhtml/oauth_authorize/reject';
    }
}

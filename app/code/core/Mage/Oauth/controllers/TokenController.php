<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Oauth
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Oauth_TokenController extends Mage_Core_Controller_Front_Action
{
    /**
     * Dispatch event before action
     */
    #[\Override]
    public function preDispatch()
    {
        $this->setFlag('', self::FLAG_NO_START_SESSION, 1);
        $this->setFlag('', self::FLAG_NO_CHECK_INSTALLATION, 1);
        $this->setFlag('', self::FLAG_NO_PRE_DISPATCH, 1);
        $this->setFlag('', self::FLAG_NO_POST_DISPATCH, 1);

        return parent::preDispatch();
    }

    /**
     * Index action. Process request and response permanent token
     */
    public function indexAction(): void
    {
        /** @var Mage_Oauth_Model_Server $server */
        $server = Mage::getModel('oauth/server');

        $server->accessToken();
    }
}

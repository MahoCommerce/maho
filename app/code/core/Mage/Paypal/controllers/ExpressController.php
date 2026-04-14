<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\Config\Route;

class Mage_Paypal_ExpressController extends Mage_Paypal_Controller_Express_Abstract
{
    protected $_configType = 'paypal/config';
    protected $_configMethod = Mage_Paypal_Model_Config::METHOD_WPP_EXPRESS;
    protected $_checkoutType = 'paypal/express_checkout';

    #[Route('/paypal/express/start')]
    #[\Override]
    public function startAction(): void
    {
        parent::startAction();
    }

    #[Route('/paypal/express/shippingOptionsCallback')]
    #[\Override]
    public function shippingOptionsCallbackAction(): void
    {
        parent::shippingOptionsCallbackAction();
    }

    #[Route('/paypal/express/cancel')]
    #[\Override]
    public function cancelAction(): void
    {
        parent::cancelAction();
    }

    #[Route('/paypal/express/return')]
    #[\Override]
    public function returnAction(): void
    {
        parent::returnAction();
    }

    #[Route('/paypal/express/review')]
    #[\Override]
    public function reviewAction(): void
    {
        parent::reviewAction();
    }

    #[Route('/paypal/express/edit')]
    #[\Override]
    public function editAction(): void
    {
        parent::editAction();
    }

    #[Route('/paypal/express/saveShippingMethod', methods: ['POST'])]
    #[Route('/paypal/express/saveShippingMethod', name: 'mage.paypal.express.save_shipping_method.get', methods: ['GET'])]
    #[\Override]
    public function saveShippingMethodAction(): void
    {
        parent::saveShippingMethodAction();
    }

    #[Route('/paypal/express/updateShippingMethods')]
    #[\Override]
    public function updateShippingMethodsAction(): void
    {
        parent::updateShippingMethodsAction();
    }

    #[Route('/paypal/express/placeOrder', methods: ['POST'])]
    #[Route('/paypal/express/placeOrder', name: 'mage.paypal.express.place_order.get', methods: ['GET'])]
    #[\Override]
    public function placeOrderAction(): void
    {
        parent::placeOrderAction();
    }
}

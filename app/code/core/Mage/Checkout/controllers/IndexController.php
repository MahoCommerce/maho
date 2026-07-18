<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

class Mage_Checkout_IndexController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/checkout', name: 'checkout.index', methods: ['GET'])]
    public function indexAction(): void
    {
        $this->_redirect('checkout/onepage', ['_secure' => true]);
    }
}

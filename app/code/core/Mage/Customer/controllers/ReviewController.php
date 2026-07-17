<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

class Mage_Customer_ReviewController extends Mage_Core_Controller_Front_Action
{
    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();
        if (!Mage::getStoreConfigFlag('customer/account/enabled_in_frontend')) {
            $this->norouteAction();
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            return $this;
        }
        return $this;
    }

    #[Maho\Config\Route('/customer/review', name: 'customer.review.index', methods: ['GET'])]
    public function indexAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    #[Maho\Config\Route('/customer/review/view', name: 'customer.review.view', methods: ['GET'])]
    public function viewAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }
}

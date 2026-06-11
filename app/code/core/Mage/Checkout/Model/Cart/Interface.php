<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

interface Mage_Checkout_Model_Cart_Interface
{
    /**
     * Add product to shopping cart (quote)
     *
     * @param   int|Mage_Catalog_Model_Product $productInfo
     * @param   mixed                          $requestInfo
     * @return  Mage_Checkout_Model_Cart_Interface
     */
    public function addProduct($productInfo, $requestInfo = null);

    /**
     * Save cart
     *
     * @abstract
     * @return Mage_Checkout_Model_Cart_Interface
     */
    public function saveQuote();

    /**
     * Associate quote with the cart
     *
     * @abstract
     * @return Mage_Checkout_Model_Cart_Interface
     */
    public function setQuote(Mage_Sales_Model_Quote $quote);

    /**
     * Get quote object associated with cart
     * @abstract
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote();
}

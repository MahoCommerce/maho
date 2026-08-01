<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/**
 * Per-customer session namespace used by the My Account balance lookup
 * page to hand the most recent check result from the POST handler to the
 * GET renderer, and to surface user-facing error messages.
 */
class Maho_Giftcard_Model_Session extends Mage_Core_Model_Session_Abstract
{
    public function __construct()
    {
        $this->init('giftcard');
    }
}

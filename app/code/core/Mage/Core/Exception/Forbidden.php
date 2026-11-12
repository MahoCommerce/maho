<?php

/**
 * A client fault: the store does not allow this client to do the action, for example a guest when only customers can subscribe.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Exception_Forbidden extends Mage_Core_Exception {}

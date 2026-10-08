<?php

/**
 * A client fault: the current state of the record does not allow the action, for example an order that is already
 * canceled, a value that another record already uses, or another request that holds the record.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Exception_Conflict extends Mage_Core_Exception {}

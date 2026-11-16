<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

/** @var Mage_Eav_Model_Entity_Setup $this */
$installer = $this;

$installer->purgeOrphanedAttributeRows();

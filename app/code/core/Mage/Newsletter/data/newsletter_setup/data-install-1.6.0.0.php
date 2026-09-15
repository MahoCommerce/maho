<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

declare(strict_types=1);

/**
 * A new store confirms every newsletter subscription. The click is the only proof that the
 * address owner agreed, and bulk senders need that proof to keep complaint rates low. The
 * value is written as a row instead of a config.xml default, so an existing store keeps the
 * single opt-in behavior it runs today until an operator changes it.
 *
 * @var Mage_Core_Model_Resource_Setup $this
 */
$installer = $this;
$installer->startSetup();

$installer->setConfigData(Mage_Newsletter_Model_Subscriber::XML_PATH_CONFIRMATION_FLAG, '1');

$installer->endSetup();

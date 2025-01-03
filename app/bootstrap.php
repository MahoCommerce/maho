<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Apply workaround for the libxml PHP bugs:
 * @link https://bugs.php.net/bug.php?id=62577
 * @link https://bugs.php.net/bug.php?id=64938
 */
if ((LIBXML_VERSION < 20900) && function_exists('libxml_disable_entity_loader')) {
    libxml_disable_entity_loader(false);
}

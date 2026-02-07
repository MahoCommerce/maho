<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Install_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const AVAILABLE_LANGUAGE_PACKS = [
        'de_DE', 'el_GR', 'es_ES', 'fr_FR', 'it_IT', 'nl_NL', 'pt_BR', 'pt_PT', 'ro_RO',
    ];

    protected $_moduleName = 'Mage_Install';
}

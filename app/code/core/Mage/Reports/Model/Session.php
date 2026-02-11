<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method $this unsData(string $value)
 */

class Mage_Reports_Model_Session extends Mage_Core_Model_Session_Abstract
{
    /**
     * Initialize session name space
     */
    public function __construct()
    {
        $this->init('reports');
    }
}

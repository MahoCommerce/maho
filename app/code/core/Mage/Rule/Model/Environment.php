<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Rule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/**
 * @method $this setNow(int $value)
 */

class Mage_Rule_Model_Environment extends \Maho\DataObject
{
    /**
     * Collect application environment for rules filtering
     *
     * @todo make it not dependent on checkout module
     * @return $this
     */
    public function collect()
    {
        $this->setNow(time());

        Mage::dispatchEvent('rule_environment_collect', ['env' => $this]);

        return $this;
    }
}

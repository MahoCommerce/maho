<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Dataflow
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Convert SOAP adapter
 *
 * @category   Mage
 * @package    Mage_Dataflow
 */
class Mage_Dataflow_Model_Convert_Adapter_Soap extends Mage_Dataflow_Model_Convert_Adapter_Abstract
{
    #[\Override]
    public function load()
    {
        return $this;
    }

    #[\Override]
    public function save()
    {
        return $this;
    }
}

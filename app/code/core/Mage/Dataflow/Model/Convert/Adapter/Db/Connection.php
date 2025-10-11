<?php

/**
 * Maho
 *
 * @package    Mage_Dataflow
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Dataflow_Model_Convert_Adapter_Db_Connection extends Mage_Dataflow_Model_Convert_Adapter_Abstract
{
    #[\Override]
    public function getResource()
    {
        if (!$this->_resource) {
            // Create Varien database adapter with provided configuration
            $config = $this->getVars();
            $config['type'] = $this->getVar('adapter', 'Pdo_Mysql');
            $this->_resource = new Varien_Db_Adapter_Pdo_Mysql($config);
        }
        return $this->_resource;
    }

    #[\Override]
    public function load(): self
    {
        return $this;
    }

    #[\Override]
    public function save(): self
    {
        return $this;
    }
}

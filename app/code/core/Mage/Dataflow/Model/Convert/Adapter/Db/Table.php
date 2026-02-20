<?php

/**
 * Maho
 *
 * @package    Mage_Dataflow
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Dataflow_Model_Convert_Adapter_Db_Table extends Mage_Dataflow_Model_Convert_Adapter_Abstract
{
    #[\Override]
    public function getResource()
    {
        if (!$this->_resource) {
            // Create Varien database adapter with provided configuration
            $config = $this->getVars();
            $config['type'] = $this->getVar('type', 'Pdo_Mysql');
            $this->_resource = new Maho\Db\Adapter\Pdo\Mysql($config);
        }
        return $this->_resource;
    }

    #[\Override]
    public function load() {}

    #[\Override]
    public function save() {}
}

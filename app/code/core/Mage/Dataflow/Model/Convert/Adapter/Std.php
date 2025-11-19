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

class Mage_Dataflow_Model_Convert_Adapter_Std extends Mage_Dataflow_Model_Convert_Adapter_Abstract
{
    #[\Override]
    public function load()
    {
        $data = '';
        $stdin = fopen('php://STDIN', 'r');
        while ($text = fread($stdin, 1024)) {
            $data .= $text;
        }
        $this->setData($data);
        return $this;
    }

    #[\Override]
    public function save()
    {
        echo $this->getData();
        return $this;
    }
}

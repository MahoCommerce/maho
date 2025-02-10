<?php

/**
 * Maho
 *
 * @package    Mage_Dataflow
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Convert parser interface
 *
 * @package    Mage_Dataflow
 */
interface Mage_Dataflow_Model_Convert_Parser_Interface
{
    public function parse();

    public function unparse();
}

<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Dataflow
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Dataflow_Model_Convert_Iterator_File_Csv extends Mage_Dataflow_Model_Convert_Parser_Abstract
{
    #[\Override]
    public function parse()
    {
        return $this;
    }

    #[\Override]
    public function unparse()
    {
        return $this;
    }
}

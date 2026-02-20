<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_Resource_Email_Template_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Template table name
     *
     * @var string
     */
    protected $_templateTable;

    /**
     * Define resource table
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('core/email_template');
        $this->_templateTable = $this->getMainTable();
    }

    #[\Override]
    public function toOptionArray(): array
    {
        return $this->_toOptionArray('template_id', 'template_code');
    }
}

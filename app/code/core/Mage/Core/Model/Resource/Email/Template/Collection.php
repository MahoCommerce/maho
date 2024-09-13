<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Templates collection
 *
 * @category   Mage
 * @package    Mage_Core
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
     *
     */
    #[\Override]
    public function _construct()
    {
        $this->_init('core/email_template');
        $this->_templateTable = $this->getMainTable();
    }

    /**
     * Convert collection items to select options array
     *
     * @return array
     */
    #[\Override]
    public function toOptionArray()
    {
        return $this->_toOptionArray('template_id', 'template_code');
    }
}

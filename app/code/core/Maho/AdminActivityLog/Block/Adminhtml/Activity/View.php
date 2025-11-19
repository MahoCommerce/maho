<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_AdminActivityLog_Block_Adminhtml_Activity_View extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'activity_id';
        $this->_controller = 'adminhtml_activity';
        $this->_blockGroup = 'adminactivitylog';
        $this->_mode = 'view';

        parent::__construct();

        $this->_removeButton('save');
        $this->_removeButton('delete');
        $this->_removeButton('reset');

        $this->_updateButton('back', 'label', Mage::helper('adminactivitylog')->__('Back'));
        $this->_updateButton('back', 'onclick', 'setLocation(\'' . $this->getUrl('*/*/index') . '\')');
    }

    #[\Override]
    public function getHeaderText()
    {
        $activity = Mage::registry('current_activity');
        if ($activity && $activity->getId()) {
            return Mage::helper('adminactivitylog')->__('View Activity Log Entry #%s', $activity->getId());
        }
        return '';
    }

}

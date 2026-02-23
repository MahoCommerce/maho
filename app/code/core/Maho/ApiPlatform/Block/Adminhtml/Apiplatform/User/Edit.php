<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_User_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'user_id';
        $this->_blockGroup = 'maho_apiplatform';
        $this->_controller = 'adminhtml_apiplatform_user';

        parent::__construct();

        $this->_updateButton('save', 'label', $this->__('Save API User'));
        $this->_updateButton('delete', 'label', $this->__('Delete API User'));
        $this->_addButton('saveandcontinue', [
            'label'   => $this->__('Save and Continue Edit'),
            'onclick' => 'saveAndContinueEdit()',
            'class'   => 'save',
        ], -100);

        $this->_formScripts[] = "function saveAndContinueEdit() { editForm.submit(document.getElementById('edit_form').action + 'back/edit/') }";
    }

    #[\Override]
    public function getHeaderText(): string
    {
        $model = Mage::registry('api_user');
        if ($model && $model->getId()) {
            return $this->__("Edit API User '%s'", $this->escapeHtml($model->getUsername()));
        }
        return $this->__('New API User');
    }
}

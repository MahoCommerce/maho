<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_User_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm(): static
    {
        $form = new Maho\Data\Form([
            'id'      => 'edit_form',
            'action'  => $this->getUrl('*/*/save', ['user_id' => $this->getRequest()->getParam('user_id')]),
            'method'  => 'post',
            'enctype' => 'multipart/form-data',
        ]);
        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}

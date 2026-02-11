<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Widget
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Widget_Block_Adminhtml_Widget_Instance_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $form = new \Maho\Data\Form(['id' => 'edit_form', 'action' => $this->getData('action'), 'method' => 'post']);
        $form->setUseContainer(true);
        $this->setForm($form);
        return parent::_prepareForm();
    }
}

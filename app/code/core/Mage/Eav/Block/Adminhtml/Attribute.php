<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

/**
 * The attribute grid page of the entity type in the registry key current_entity_type.
 */
class Mage_Eav_Block_Adminhtml_Attribute extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    use Mage_Eav_Block_Adminhtml_EntityTypeUrlTrait;

    public function __construct()
    {
        $this->_blockGroup = 'eav';
        $this->_controller = 'adminhtml_attribute';
        $this->_headerText = Mage::helper('eav')->__('Manage Attributes');
        parent::__construct();
    }

    #[\Override]
    public function getHeaderText()
    {
        return $this->getData('header_text') ?: parent::getHeaderText();
    }
}

<?php

/**
 * Adds the fields of the custom attributes of CMS pages to a form of the page edit page.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

/**
 * @phpstan-require-extends Mage_Adminhtml_Block_Widget_Form
 */
trait Mage_Adminhtml_Block_Cms_Page_Edit_AttributeFieldsTrait
{
    /**
     * Add one field for each attribute to $fieldset. A new $page takes the default value of each
     * attribute. An admin who cannot save pages sees the fields disabled.
     *
     * @param list<Mage_Eav_Model_Entity_Attribute_Abstract> $attributes
     */
    protected function addAttributeFields(\Maho\Data\Form\Element\Fieldset $fieldset, array $attributes, Mage_Cms_Model_Page $page): void
    {
        if (!$attributes) {
            return;
        }
        $this->setData('attribute_dependence_values', $page->getData());
        $this->_setFieldset($attributes, $fieldset);
        $disabled = !Mage::getSingleton('admin/session')->isAllowed('cms/page/save');
        foreach ($attributes as $attribute) {
            $code = (string) $attribute->getAttributeCode();
            if ($disabled) {
                $fieldset->getForm()->getElement($code)?->setDisabled(true);
            }
            if (!$page->getId() && $page->getData($code) === null) {
                $page->setData($code, $attribute->getDefaultValue());
            }
        }
    }
}

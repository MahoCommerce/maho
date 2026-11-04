<?php

/**
 * Adds the fields of user-defined attributes to a form of the edit page of a blog post or blog category.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

/**
 * @phpstan-require-extends Mage_Adminhtml_Block_Widget_Form
 */
trait Maho_Blog_Block_Adminhtml_Edit_AttributeFieldsTrait
{
    /**
     * Add one field for each attribute to $fieldset. A new $entity takes the default value of each attribute.
     *
     * @param list<Maho_Blog_Model_Resource_Eav_Attribute> $attributes
     */
    protected function addAttributeFields(
        \Maho\Data\Form\Element\Fieldset $fieldset,
        array $attributes,
        Maho_Blog_Model_Post|Maho_Blog_Model_Category $entity,
    ): void {
        $editors = [];
        $others = [];
        foreach ($attributes as $attribute) {
            if ($attribute->getFrontendInput() === 'textarea' && $attribute->getData('is_wysiwyg_enabled')) {
                $editors[] = $attribute;
            } else {
                $others[] = $attribute;
            }
            if (!$entity->getId() && $entity->getData($attribute->getAttributeCode()) === null) {
                $entity->setData($attribute->getAttributeCode(), $attribute->getDefaultValue());
            }
        }
        $this->_setFieldset($others, $fieldset);
        foreach ($editors as $attribute) {
            $fieldset->addField((string) $attribute->getAttributeCode(), 'editor', [
                'name' => $attribute->getAttributeCode(),
                'label' => $attribute->getFrontend()->getLabel(),
                'required' => (bool) $attribute->getIsRequired(),
                'style' => 'height:20em;',
                'config' => Mage::getSingleton('cms/wysiwyg_config')->getConfig(),
            ]);
        }
    }
}

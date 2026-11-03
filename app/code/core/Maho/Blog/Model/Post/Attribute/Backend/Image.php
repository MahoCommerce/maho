<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Model_Post_Attribute_Backend_Image extends Mage_Eav_Model_Entity_Attribute_Backend_Image
{
    /** Directory of post images on the media mount. */
    public const STORAGE_PATH = 'blog';

    #[\Override]
    public function getImageEntity(): string
    {
        return Maho_Blog_Model_Post_Image::ENTITY;
    }

    /**
     * The image is a path under media/blog, so a path that leaves that folder or names a scheme is refused.
     *
     * @throws Mage_Core_Exception_Input
     */
    #[\Override]
    public function beforeSave($object)
    {
        $value = $object->getData($this->getAttribute()->getName());
        if (is_string($value) && $value !== '' && (
            preg_match('#^(/|[a-z][a-z0-9+.-]*:)#i', str_replace('\\', '/', $value))
            || in_array('..', explode('/', str_replace('\\', '/', $value)), true)
        )) {
            throw new Mage_Core_Exception_Input()->addError('image', Mage::helper('blog')->__(
                'The image must be a path relative to media/blog, with no leading "/", no ".." segment and no scheme.',
            ));
        }
        return parent::beforeSave($object);
    }

    #[\Override]
    protected function saveValue(\Maho\DataObject $object, string $value): void
    {
        $attribute = $this->getAttribute();
        $object->setData($attribute->getName(), $value);
        $attribute->getEntity()->getWriteConnection()->insertOnDuplicate($this->getTable(), [
            'entity_type_id' => $attribute->getEntityTypeId(),
            'attribute_id' => $attribute->getId(),
            // The store_id attribute is the store where the post was created, but the values are global
            'store_id' => Mage_Core_Model_App::ADMIN_STORE_ID,
            $attribute->getEntity()->getEntityIdField() => $object->getId(),
            'value' => $value,
        ], ['value']);
    }
}

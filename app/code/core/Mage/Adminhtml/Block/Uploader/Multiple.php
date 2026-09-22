<?php

/**
 * An uploader that takes several files.
 *
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Uploader_Multiple extends Mage_Adminhtml_Block_Uploader_Abstract
{
    public const DEFAULT_UPLOAD_BUTTON_ID_SUFFIX = 'upload';

    #[\Override]
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $this->setChild(
            'upload_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->addData([
                    'id'      => $this->getElementId(self::DEFAULT_UPLOAD_BUTTON_ID_SUFFIX),
                    'label'   => Mage::helper('adminhtml')->__('Upload Files'),
                    'type'    => 'button',
                ]),
        );

        $this->_addElementIdsMapping([
            'upload' => $this->_prepareElementsIds([self::DEFAULT_UPLOAD_BUTTON_ID_SUFFIX]),
        ]);

        return $this;
    }

    public function getUploadButtonHtml(): string
    {
        return $this->getChildHtml('upload_button');
    }
}

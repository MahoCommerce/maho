<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Install
 */

declare(strict_types=1);

use Maho\DataObject;

class Mage_Install_Block_Configuration extends Mage_Install_Block_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('page/configuration.phtml');
    }

    public function getPostUrl(): string
    {
        return $this->getUrl('*/*/configurationPost');
    }

    public function getFormData(): DataObject
    {
        $data = $this->getData('form_data');
        if ($data === null) {
            $data = Mage::getSingleton('install/session')->getConfigData(true);
            if (empty($data)) {
                $data = Mage::getModel('install/installer_config')->getFormData();
            } else {
                $data = new DataObject($data);
            }
            $this->setFormData($data);
        }
        return $data;
    }

    public function getSessionSaveOptions(): array
    {
        return [
            'files' => $this->helper('install')->__('File System'),
            'db' => $this->helper('install')->__('Database'),
        ];
    }

    public function getSessionSaveSelect(): string
    {
        return $this->getLayout()->createBlock('core/html_select')
            ->setName('config[session_save]')
            ->setId('session_save')
            ->setTitle($this->helper('install')->__('Save Session Files In'))
            ->setExtraParams('required')
            ->setOptions($this->getSessionSaveOptions())
            ->getHtml();
    }
}

<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Maho\DataObject;

class Mage_Install_Block_Configuration_Database_Type extends Mage_Core_Block_Template
{
    protected ?string $title = null;

    public function getTitle(): ?string
    {
        return $this->title;
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
}

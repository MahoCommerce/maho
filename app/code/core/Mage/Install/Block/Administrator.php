<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Maho\DataObject;

class Mage_Install_Block_Administrator extends Mage_Install_Block_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('page/administrator.phtml');
    }

    public function getPostUrl(): string
    {
        return $this->getUrl('*/*/administratorPost');
    }

    public function getFormData(): DataObject
    {
        $data = $this->getData('form_data');
        if ($data === null) {
            $data = new DataObject(Mage::getSingleton('install/session')->getAdminData(true));
            $this->setData('form_data', $data);
        }
        return $data;
    }

    public function getMinAdminPasswordLength(): int
    {
        return Mage::getModel('admin/user')->getMinAdminPasswordLength();
    }
}

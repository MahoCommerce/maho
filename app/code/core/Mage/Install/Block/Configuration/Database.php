<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Maho\DataObject;

class Mage_Install_Block_Configuration_Database extends Mage_Core_Block_Template
{
    /**
     * Array of Database blocks keyed by name
     */
    protected array $databases = [];

    /**
     * Adding customized database block template for database model type
     */
    public function addDatabaseBlock(string $type, string $block, string $template): self
    {
        $this->databases[$type] = [
            'block' => $block,
            'template' => $template,
            'instance' => null,
        ];

        return $this;
    }

    public function getDatabaseBlock(string $type): false|Mage_Install_Block_Configuration_Database_Type
    {
        $block = false;
        if (isset($this->databases[$type])) {
            if ($this->databases[$type]['instance']) {
                $block = $this->databases[$type]['instance'];
            } else {
                $block = $this->getLayout()->createBlock($this->databases[$type]['block'])
                    ->setTemplate($this->databases[$type]['template'])
                    ->setIdPrefix($type);
                $this->databases[$type]['instance'] = $block;
            }
        }
        return $block;
    }

    public function getDatabaseBlocks(): array
    {
        $databases = [];
        foreach (array_keys($this->databases) as $type) {
            $databases[] = $this->getDatabaseBlock($type);
        }
        return $databases;
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

<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Installation ending block
 *
 * @category   Mage
 * @package    Mage_Install
 */
class Mage_Install_Block_End extends Mage_Install_Block_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('install/end.phtml');
    }

    public function getEncryptionKey()
    {
        $key = $this->getData('encryption_key');
        if (is_null($key)) {
            $key = (string) Mage::getConfig()->getNode('global/crypt/key');
            $this->setData('encryption_key', $key);
        }
        return $key;
    }
}

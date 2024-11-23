<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Core
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Config model for loading jstranslate.xml files
 *
 * @category   Mage
 * @package    Mage_Core
 */
class Mage_Core_Model_Translate_Config extends Varien_Simplexml_Config
{
    /**
     * Extends current node with xml from $source, appending nodes without merging
     *
     * @param boolean $overwrite Argument has no effect, included for PHP 7.2 method signature compatibility
     * @return $this
     */
    public function extend(Varien_Simplexml_Config $config, $overwrite = true)
    {
        foreach ($config->getNode()->children() as $child) {
            $this->getNode()->appendChild($child);
        }
        return $this;
    }
}

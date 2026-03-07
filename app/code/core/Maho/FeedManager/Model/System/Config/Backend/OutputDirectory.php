<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_System_Config_Backend_OutputDirectory extends Mage_Core_Model_Config_Data
{
    #[\Override]
    protected function _beforeSave(): self
    {
        $value = trim((string) $this->getValue());

        if ($value !== '') {
            $value = rtrim(str_replace('\\', '/', $value), '/');
            $mediaDir = Mage::getBaseDir('media');
            $candidatePath = $mediaDir . DS . $value;

            if (!\Maho\Io::allowedPath($candidatePath, $mediaDir)) {
                Mage::throwException(Mage::helper('feedmanager')->__('Output directory must be a relative path within the media folder.'));
            }

            $this->setValue($value);
        }

        return parent::_beforeSave();
    }
}

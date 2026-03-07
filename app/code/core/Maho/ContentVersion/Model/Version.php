<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ContentVersion
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_ContentVersion_Model_Version extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('contentversion/version');
    }

    public function getContentDataDecoded(): array
    {
        $data = $this->getData('content_data');
        if (is_string($data)) {
            return Mage::helper('core')->jsonDecode($data);
        }
        return [];
    }

    public function setContentDataEncoded(array $data): self
    {
        $this->setData('content_data', Mage::helper('core')->jsonEncode($data));
        return $this;
    }
}

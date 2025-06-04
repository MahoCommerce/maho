<?php

/**
 * Maho
 *
 * @package     Mage_Core
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Block_Page_Title extends Mage_Core_Block_Template
{
    protected ?string $_title = null;

    public function setTitle(string $title): self
    {
        $this->_title = $title;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->_title;
    }
}

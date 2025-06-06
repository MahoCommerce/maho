<?php

/**
 * Maho
 *
 * @package     Mage_Core
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Page_Block_Html_Title extends Mage_Core_Block_Template
{
    protected ?string $title = null;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('page/html/title.phtml');
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }
}

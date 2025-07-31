<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Block_Pdf extends Mage_Core_Block_Template
{
    use Mage_Core_Model_Pdf_Trait;

    public function __construct()
    {
        parent::__construct();
        $this->initDompdf();
    }

    public function renderPdf(): string
    {
        $html = $this->toHtml();
        return $this->generatePdf($html);
    }

    public function getStore(): Mage_Core_Model_Store
    {
        return Mage::app()->getStore();
    }

    #[\Override]
    protected function _toHtml(): string
    {
        $html = parent::_toHtml();
        return $this->wrapHtmlDocument($html);
    }
}

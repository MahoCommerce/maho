<?php

/**
 * Maho
 *
 * @package    Maho_Revocation
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * Persistent storefront revocation button. The directive requires it to be visible
 * across the storefront throughout the withdrawal period, hence the default-handle
 * footer placement. Rendered as a plain link: it must work without JavaScript.
 */
class Maho_Revocation_Block_Footer extends Mage_Core_Block_Template
{
    public function getButtonLabel(): string
    {
        return Mage::helper('revocation')->getButtonLabel();
    }

    public function getRevocationUrl(): string
    {
        return Mage::getUrl('revocation/index/index');
    }

    #[\Override]
    protected function _toHtml(): string
    {
        if (!Mage::helper('revocation')->showFooterButton()) {
            return '';
        }
        return parent::_toHtml();
    }
}

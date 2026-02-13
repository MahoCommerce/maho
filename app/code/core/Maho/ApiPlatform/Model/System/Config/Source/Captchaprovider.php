<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * CAPTCHA provider source model for contact form
 */
class Maho_ApiPlatform_Model_System_Config_Source_Captchaprovider
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'none', 'label' => Mage::helper('maho_apiplatform')->__('None (rely on infrastructure-level protection)')],
            ['value' => 'turnstile', 'label' => Mage::helper('maho_apiplatform')->__('Cloudflare Turnstile (free, invisible)')],
            ['value' => 'recaptcha_v3', 'label' => Mage::helper('maho_apiplatform')->__('Google reCAPTCHA v3 (invisible)')],
        ];
    }
}

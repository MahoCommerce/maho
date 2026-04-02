<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Contacts
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Contacts\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Service\StoreContext;

class ContactFormProvider extends \Maho\ApiPlatform\Provider
{
    private const CONFIG_ENABLED = 'maho_apiplatform/contact/enabled';
    private const CONFIG_CAPTCHA_PROVIDER = 'maho_apiplatform/contact/captcha_provider';
    private const CONFIG_CAPTCHA_SITE_KEY = 'maho_apiplatform/contact/captcha_site_key';
    private const CONFIG_HONEYPOT = 'maho_apiplatform/contact/honeypot_enabled';

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ContactForm
    {
        StoreContext::ensureStore();
        $storeId = StoreContext::getStoreId();

        $provider = \Mage::getStoreConfig(self::CONFIG_CAPTCHA_PROVIDER, $storeId) ?: 'none';

        $dto = new ContactForm();
        $dto->enabled = (bool) \Mage::getStoreConfigFlag(self::CONFIG_ENABLED, $storeId);
        $dto->captchaProvider = $provider;
        $dto->captchaSiteKey = $provider !== 'none'
            ? \Mage::getStoreConfig(self::CONFIG_CAPTCHA_SITE_KEY, $storeId)
            : null;
        $dto->honeypotField = \Mage::getStoreConfigFlag(self::CONFIG_HONEYPOT, $storeId)
            ? 'company'
            : null;

        return $dto;
    }
}

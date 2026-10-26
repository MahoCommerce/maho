---
description: Provider-agnostic CAPTCHA in the Maho v2 API - advertise settings and verify tokens through events, with built-in Altcha support and third-party hooks.
---

# CAPTCHA <span class="version-badge">v26.7+</span>

CAPTCHA support in the API is provider-agnostic: it exposes two events that any captcha module can observe. The built-in `Maho_Captcha` module (Altcha) supports them out of the box, and third-party providers hook in the same way.

### How it works

**Configuration**, endpoints that need to advertise CAPTCHA settings to a frontend (e.g. `GET /contact/config`) read from store config and/or the `api_captcha_config` event. The exact fields exposed depend on the endpoint; for `/contact/config` they are flat: `captchaProvider`, `captchaSiteKey`, `enabled`. Other endpoints (or third-party modules calling `Mage::helper('apiplatform')->getCaptchaConfig()`) get the open key/value bag populated by the event.

The frontend uses this to load the right widget (Turnstile, reCAPTCHA, etc.) and obtain a token.

**Verification**, on form submission, include the solved token as `captchaToken` in the request body. The API dispatches `api_verify_captcha` and the active module verifies it.

### Events

| Event | Purpose | Observer parameters |
|-------|---------|---------------------|
| `api_captcha_config` | Describe the active provider to the frontend | `config` (DataObject, set `enabled`, `provider`, and any provider-specific fields like `challengeUrl` or `siteKey`) |
| `api_verify_captcha` | Verify a submitted token | `result` (DataObject, set `verified` to `false` and `error` to a message string to reject), `data` (array, the full request body, token is in `captchaToken`) |

### Helper methods

Any API controller or processor can verify captcha tokens via the ApiPlatform helper:

```php
/** @var Maho_ApiPlatform_Helper_Data $helper */
$helper = Mage::helper('apiplatform');

// Get config for the frontend
$captchaConfig = $helper->getCaptchaConfig();

// Verify a token, returns null on success, error message on failure
$error = $helper->verifyCaptcha($requestData);
if ($error !== null) {
    // reject the request
}
```

### Built-in: Altcha (Maho_Captcha)

The native `Maho_Captcha` module observes both events out of the box using [Altcha](https://altcha.org/), a self-hosted, privacy-friendly proof-of-work challenge that requires no third-party API calls.

### Third-party providers

A Turnstile or reCAPTCHA module just needs to observe the same two events with `#[Maho\Config\Observer]` attributes (run `composer dump-autoload` after adding them). This is exactly how the built-in `Maho_Captcha` module registers itself:

```php
class My_Turnstile_Model_Observer
{
    #[Maho\Config\Observer('api_captcha_config', area: 'api')]
    public function getCaptchaConfig(\Maho\Event\Observer $observer): void
    {
        $config = $observer->getEvent()->getConfig();
        $config->setEnabled(true);
        $config->setProvider('turnstile');
        $config->setSiteKey(Mage::getStoreConfig('my_turnstile/general/site_key'));
    }

    #[Maho\Config\Observer('api_verify_captcha', area: 'api')]
    public function verifyCaptcha(\Maho\Event\Observer $observer): void
    {
        $data = $observer->getEvent()->getData('data');
        $token = $data['captchaToken'] ?? '';
        $result = $observer->getEvent()->getResult();

        // Call Turnstile verify API...
        if (!$this->verifyToken($token)) {
            $result->setVerified(false);
            $result->setError('CAPTCHA verification failed.');
        }
    }
}
```

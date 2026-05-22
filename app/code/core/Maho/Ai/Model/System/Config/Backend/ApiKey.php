<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Encrypted backend for AI provider API keys. On save, when the plaintext
 * value actually changes (i.e. admin pasted a new key, not just resubmitted
 * the obscured form value), the matching provider's model list is fetched
 * and cached under maho_ai/models_cache/{provider}. Removes the need for a
 * manual "Update Models" button — the model dropdown auto-populates on the
 * next page render.
 *
 * The change-detection runs in _beforeSave because that's where we still
 * have the plaintext form value to compare against. _afterSave then performs
 * the fetch after the new key has been persisted (so the fetcher reads the
 * just-saved key, not the old one).
 */
class Maho_Ai_Model_System_Config_Backend_ApiKey extends Mage_Adminhtml_Model_System_Config_Backend_Encrypted
{
    private bool $shouldFetchModels = false;

    #[\Override]
    protected function _beforeSave()
    {
        $newValue = (string) $this->getValue();
        $oldValue = (string) $this->getOldValue();

        // The obscure frontend type submits "*****" when the admin didn't
        // touch the field. Parent::_beforeSave swaps that for the old
        // encrypted value, so no actual change occurs — don't trigger.
        $isObscured = (bool) preg_match('/^\*+$/', $newValue);

        if (!$isObscured && $newValue !== '' && $newValue !== $oldValue) {
            $this->shouldFetchModels = true;
        }

        parent::_beforeSave();
        return $this;
    }

    #[\Override]
    protected function _afterSave()
    {
        parent::_afterSave();

        if (!$this->shouldFetchModels) {
            return $this;
        }

        if (preg_match('#^maho_ai/general/([a-z]+)_api_key$#', (string) $this->getPath(), $m)) {
            Mage::getModel('ai/platform_modelFetcher')->refreshCache($m[1]);
        }

        return $this;
    }
}

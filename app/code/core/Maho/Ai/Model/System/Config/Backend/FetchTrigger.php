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
 * Sibling to Backend\ApiKey for non-encrypted credential fields (e.g.
 * ollama_base_url). Triggers a model-list refresh whenever the value
 * changes so the corresponding dropdown auto-populates on the next render.
 */
class Maho_Ai_Model_System_Config_Backend_FetchTrigger extends Mage_Core_Model_Config_Data
{
    private bool $shouldFetchModels = false;

    #[\Override]
    protected function _beforeSave()
    {
        if ((string) $this->getValue() !== '' && $this->isValueChanged()) {
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

        if (preg_match('#^maho_ai/general/([a-z]+)_base_url$#', (string) $this->getPath(), $m)) {
            Mage::getModel('ai/platform_modelFetcher')->refreshCache($m[1]);
        }

        return $this;
    }
}

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
 * Renders an AI model dropdown with a "Refresh Models" button next to it.
 * Click hits AiController::fetchModelsAction, which re-fetches the provider's
 * model list, writes it to maho_ai/models_cache/{provider}, and returns it as
 * JSON so the JS can repopulate the dropdown in place.
 *
 * Model lists also refresh automatically on API-key save (see Backend\ApiKey
 * + Backend\FetchTrigger). The button is for the common case where the key is
 * already saved and the user just wants to pick up newly released models — at
 * providers like OpenRouter that's a near-weekly occurrence.
 */
class Maho_Ai_Block_Adminhtml_System_Config_Form_Field_ModelSelect extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    #[\Override]
    protected function _getElementHtml(\Maho\Data\Form\Element\AbstractElement $element): string
    {
        $html = parent::_getElementHtml($element);

        // Element IDs: {section}_{general|image|embed|video}_{provider}_model
        // Section is intentionally loose so community modules that ship their own
        // system-config section (e.g. AiContent's NanoGPT fields) can reuse this renderer.
        $elementId = $element->getHtmlId();
        if (!preg_match('/^.+_(general|image|embed|video)_(.+)_model$/', $elementId, $matches)) {
            return $html;
        }

        $group    = $matches[1];
        $provider = $matches[2];

        // Generic OpenAI-compatible has no fixed /models endpoint shape —
        // same exclusion as ModelFetcher::refreshCache().
        if ($provider === 'generic') {
            return $html;
        }
        if (Maho_Ai_Model_Platform::getProviderConfig($provider) === null) {
            return $html;
        }

        $capability = $group === 'general' ? 'chat' : $group;

        $url      = $this->getUrl('*/ai/fetchModels', ['provider' => $provider, 'capability' => $capability]);
        $label    = Mage::helper('ai')->__('Refresh Models');
        $fetching = Mage::helper('ai')->__('Fetching...');
        $errorPfx = Mage::helper('ai')->__('Error: ');
        $btnId    = 'maho-ai-fetch-' . $elementId;

        $btn = sprintf(
            '<button type="button" id="%s" style="margin-left:8px;white-space:nowrap"><span>%s</span></button>',
            $this->escapeHtml($btnId),
            $this->escapeHtml($label),
        );

        $btnIdJs    = json_encode($btnId);
        $urlJs      = json_encode($url);
        $targetJs   = json_encode($elementId);
        $fetchingJs = json_encode($fetching);
        $errorPfxJs = json_encode($errorPfx);

        $script = <<<HTML
<script>
mahoOnReady(function () {
    const btn = document.getElementById({$btnIdJs});
    if (!btn) return;

    btn.addEventListener('click', async function () {
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = {$fetchingJs};

        try {
            const data = await mahoFetch({$urlJs}, { method: 'POST', loaderArea: false });
            if (data.error) {
                alert({$errorPfxJs} + data.error);
                return;
            }

            let el = document.getElementById({$targetJs});
            if (!el) return;
            const current = el.value;

            if (el.tagName === 'INPUT') {
                const sel = document.createElement('select');
                sel.id = el.id;
                sel.name = el.name;
                sel.className = el.className;
                sel.style.cssText = el.style.cssText;
                el.parentNode.replaceChild(sel, el);
                el = sel;
            }

            el.innerHTML = '';
            (data.models || []).forEach(function (m) {
                const opt = document.createElement('option');
                opt.value = m.value;
                opt.text = m.label;
                if (m.value === current) opt.selected = true;
                el.appendChild(opt);
            });
            if (current && !el.querySelector('option[selected]')) {
                el.value = current;
            }
        } catch (err) {
            alert({$errorPfxJs} + (err && err.message ? err.message : err));
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    });
});
</script>
HTML;

        return '<div style="display:flex;align-items:center">' . $html . $btn . $script . '</div>';
    }
}

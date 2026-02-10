/**
 * Maho FeedManager - Mapping Tab JavaScript
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Field editor state
 */
const feedEditorState = {
    currentLineIndex: -1,
    mode: 'insert'
};

/**
 * Number format presets
 */
const FeedMappingPresets = {
    english: { decimal_point: '.', thousands_sep: ',' },
    european: { decimal_point: ',', thousands_sep: '.' },
    swiss: { decimal_point: '.', thousands_sep: "'" },
    indian: { decimal_point: '.', thousands_sep: ',' }
};

/**
 * Initialize mapping tab functionality
 */
function initFeedMappingTab() {
    initFormatPresets();
    initFormatSwitching();
    initCodeEditors();
    initTransformerModalOverrides();
}

/**
 * Initialize number format preset switching
 */
function initFormatPresets() {
    const presetSelect = document.getElementById('mapping_format_preset');
    const decimalPointField = document.getElementById('mapping_price_decimal_point');
    const thousandsSepField = document.getElementById('mapping_price_thousands_sep');

    if (presetSelect) {
        presetSelect.addEventListener('change', function() {
            const preset = FeedMappingPresets[this.value];
            if (preset) {
                if (decimalPointField) decimalPointField.value = preset.decimal_point;
                if (thousandsSepField) thousandsSepField.value = preset.thousands_sep;
            }
        });
    }
}

/**
 * Toggle fieldset visibility along with its collapsible header
 */
function toggleFieldsetWithHeader(fieldset, visible) {
    if (!fieldset) return;
    fieldset.style.display = visible ? 'block' : 'none';
    const header = fieldset.previousElementSibling;
    if (header && header.classList.contains('entry-edit-head')) {
        header.style.display = visible ? '' : 'none';
    }
}

/**
 * Initialize format switching (XML/CSV/JSON builders)
 */
function initFormatSwitching() {
    const fileFormatSelect = document.getElementById('feed_file_format');
    if (!fileFormatSelect) return;

    function updateContentMode() {
        const format = fileFormatSelect.value;
        const xmlFieldset = document.getElementById('mapping_xml_builder_fieldset');
        const csvFieldset = document.getElementById('mapping_csv_builder_fieldset');
        const jsonFieldset = document.getElementById('mapping_json_builder_fieldset');
        const mappingFieldset = document.getElementById('mapping_mapping_fieldset');

        toggleFieldsetWithHeader(xmlFieldset, format === 'xml');
        toggleFieldsetWithHeader(csvFieldset, format === 'csv');
        toggleFieldsetWithHeader(jsonFieldset, format === 'json' || format === 'jsonl');
        toggleFieldsetWithHeader(mappingFieldset, false);
    }

    updateContentMode();
    fileFormatSelect.addEventListener('change', updateContentMode);
}

/**
 * Initialize code editors with syntax highlighting
 */
function initCodeEditors() {
    const textareaIds = ['mapping_xml_header', 'mapping_xml_footer'];

    textareaIds.forEach(function(id) {
        const textarea = document.getElementById(id);
        if (!textarea) return;

        // Wrap in code editor container
        const wrapper = document.createElement('div');
        wrapper.className = 'code-editor-wrapper';
        textarea.parentNode.insertBefore(wrapper, textarea);
        wrapper.appendChild(textarea);

        // Create syntax-highlighted backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'code-highlight-backdrop';
        backdrop.id = id + '_backdrop';
        wrapper.insertBefore(backdrop, textarea);

        // Update backdrop on input
        textarea.addEventListener('input', function() {
            updateSyntaxHighlight(textarea, backdrop);
        });

        // Sync scroll position
        textarea.addEventListener('scroll', function() {
            backdrop.scrollTop = textarea.scrollTop;
            backdrop.scrollLeft = textarea.scrollLeft;
        });

        // Initial highlight
        updateSyntaxHighlight(textarea, backdrop);

        // Handle resize - sync backdrop to textarea
        if (typeof ResizeObserver !== 'undefined') {
            const resizeObserver = new ResizeObserver(function() {
                backdrop.style.height = textarea.offsetHeight + 'px';
            });
            resizeObserver.observe(textarea);
        }
    });
}

/**
 * Update syntax highlighting in backdrop
 */
function updateSyntaxHighlight(textarea, backdrop) {
    backdrop.innerHTML = highlightXML(textarea.value);
}

/**
 * Highlight XML code with syntax colors
 */
function highlightXML(code) {
    // Escape HTML entities
    let escaped = code
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    // Highlight field configurations {type="..." value="..." ...}
    escaped = escaped.replace(
        /\{([^}]+)\}/g,
        function(match, inner) {
            const highlighted = inner.replace(
                /(\w+)(=&quot;)([^&]*?)(&quot;)/g,
                '<span class="field-key">$1</span>$2<span class="field-value">$3</span>$4'
            );
            return '<span class="field-brace">{</span>' + highlighted + '<span class="field-brace">}</span>';
        }
    );

    // Highlight template variables {{...}}
    escaped = escaped.replace(
        /\{\{([^}]+)\}\}/g,
        '<span class="template-var">{{$1}}</span>'
    );

    // Highlight XML comments
    escaped = escaped.replace(
        /&lt;!--([\s\S]*?)--&gt;/g,
        '<span class="xml-comment">&lt;!--$1--&gt;</span>'
    );

    // Highlight CDATA sections
    escaped = escaped.replace(
        /&lt;!\[CDATA\[/g,
        '<span class="xml-cdata">&lt;![CDATA[</span>'
    );
    escaped = escaped.replace(
        /\]\]&gt;/g,
        '<span class="xml-cdata">]]&gt;</span>'
    );

    // Highlight XML declaration
    escaped = escaped.replace(
        /&lt;\?(xml[^?]*)\?&gt;/gi,
        '&lt;?<span class="xml-tag">$1</span>?&gt;'
    );

    // Highlight XML tags and attributes
    escaped = escaped.replace(
        /&lt;(\/?)([\w:-]+)((?:\s+[\w:-]+(?:=&quot;[^&]*&quot;)?)*)\s*(\/?)\s*&gt;/g,
        function(match, leadingSlash, tagName, attrs, trailingSlash) {
            const highlightedAttrs = attrs.replace(
                /([\w:-]+)(=&quot;)([^&]*)(&quot;)/g,
                ' <span class="xml-attr-name">$1</span>$2<span class="xml-attr-value">$3</span>$4'
            );
            return '&lt;' + leadingSlash + '<span class="xml-tag">' + tagName + '</span>' +
                   highlightedAttrs + trailingSlash + '&gt;';
        }
    );

    return escaped;
}

/**
 * Override TransformerModal.apply for builder contexts
 */
function initTransformerModalOverrides() {
    if (typeof TransformerModal === 'undefined') return;

    const originalApply = TransformerModal.apply;
    TransformerModal.apply = function() {
        const chainStr = TransformerModal.buildChainString();

        // CSV Builder context
        if (typeof CsvBuilder !== 'undefined' && typeof CsvBuilder.currentColumnIndex !== 'undefined') {
            CsvBuilder.columns[CsvBuilder.currentColumnIndex].transformers = chainStr;
            CsvBuilder.render();
            delete CsvBuilder.currentColumnIndex;
            return;
        }

        // JSON Builder context
        if (typeof JsonBuilder !== 'undefined' && typeof JsonBuilder.currentNodePath !== 'undefined') {
            const node = JsonBuilder.getNodeByPath(JsonBuilder.currentNodePath);
            if (node) {
                node.transformers = chainStr;
            }
            JsonBuilder.render();
            JsonBuilder.showProperties(JsonBuilder.currentNodePath);
            delete JsonBuilder.currentNodePath;
            return;
        }

        // XML Builder context
        if (typeof XmlBuilder !== 'undefined' && typeof XmlBuilder.currentNodePath !== 'undefined') {
            const node = XmlBuilder.getNodeByPath(XmlBuilder.currentNodePath);
            if (node) {
                node.transformers = chainStr;
            }
            XmlBuilder.render();
            XmlBuilder.showProperties(XmlBuilder.currentNodePath);
            delete XmlBuilder.currentNodePath;
            return;
        }

        // Fall back to original behavior
        originalApply.call(TransformerModal);
    };
}

/**
 * Transformer Modal
 */
const TransformerModal = {
    chain: [],
    dialog: null,

    open: function() {
        const self = this;

        // Parse current transformers from hidden field
        const transformersStr = document.getElementById('editor_transformers').value;
        this.chain = this.parseChain(transformersStr);

        // Show modal using Dialog.confirm
        this.dialog = Dialog.confirm(this.getContent(), {
            id: 'transformer-modal',
            title: TransformerData.translations.configure_transformers,
            width: 600,
            okLabel: TransformerData.translations.save,
            onOk: function() {
                self.apply();
            },
            onOpen: function() {
                self.renderChainList();
                self.renderDropdown();
            },
            onClose: function() {
                self.hideDropdown();
                self.dialog = null;
            }
        });
    },

    close: function() {
        Dialog.close();
    },

    apply: function() {
        const chainStr = this.buildChainString();
        document.getElementById('editor_transformers').value = chainStr;
        if (typeof updateTransformersButtonLabel === 'function') {
            updateTransformersButtonLabel(chainStr);
        }
    },

    getContent: function() {
        return '<div id="transformer-chain-list" class="transformer-chain-list">' +
            '<p class="no-transformers">' + TransformerData.translations.no_transformers + '</p>' +
        '</div>' +
        '<div class="transformer-add-section">' +
            '<div class="transformer-dropdown-wrapper">' +
                '<button type="button" id="add-transformer-btn" class="scalable add" onclick="TransformerModal.toggleDropdown()">' +
                    '<span>' + TransformerData.translations.add_transformer + '</span>' +
                '</button>' +
                '<div id="transformer-dropdown" class="transformer-dropdown"></div>' +
            '</div>' +
        '</div>';
    },

    parseChain: function(str) {
        if (!str) return [];
        const chain = [];
        const parts = str.split('|');
        for (let i = 0; i < parts.length; i++) {
            const part = parts[i].trim();
            if (!part) continue;
            const colonIdx = part.indexOf(':');
            const code = colonIdx > -1 ? part.substring(0, colonIdx) : part;
            const options = {};
            if (colonIdx > -1) {
                const optStr = part.substring(colonIdx + 1);
                const optPairs = optStr.split(',');
                for (let j = 0; j < optPairs.length; j++) {
                    const kv = optPairs[j].split('=');
                    if (kv.length === 2) {
                        options[kv[0].trim()] = kv[1].trim();
                    }
                }
            }
            chain.push({ code: code, options: options });
        }
        return chain;
    },

    buildChainString: function() {
        const parts = [];
        for (let i = 0; i < this.chain.length; i++) {
            const item = this.chain[i];
            let str = item.code;
            const optParts = [];
            for (const key in item.options) {
                if (item.options[key]) {
                    optParts.push(key + '=' + item.options[key]);
                }
            }
            if (optParts.length > 0) {
                str += ':' + optParts.join(',');
            }
            parts.push(str);
        }
        return parts.join('|');
    },

    renderChainList: function() {
        const container = document.getElementById('transformer-chain-list');
        if (this.chain.length === 0) {
            container.innerHTML = '<p class="no-transformers">' + TransformerData.translations.no_transformers + '</p>';
            return;
        }

        let html = '';
        for (let i = 0; i < this.chain.length; i++) {
            const item = this.chain[i];
            const def = TransformerData.definitions[item.code];
            if (!def) continue;

            html += '<div class="transformer-item expanded" data-index="' + i + '">';
            html += '<div class="transformer-item-header" onclick="TransformerModal.toggleItem(' + i + ')">';
            html += '<span class="transformer-item-number">' + (i + 1) + '</span>';
            html += '<span class="transformer-item-name">' + def.name + '</span>';
            html += '<div class="transformer-item-actions">';
            if (i > 0) {
                html += '<button type="button" onclick="TransformerModal.moveUp(' + i + '); event.stopPropagation();" title="Move Up">' + TransformerData.icons.arrow_up + '</button>';
            }
            if (i < this.chain.length - 1) {
                html += '<button type="button" onclick="TransformerModal.moveDown(' + i + '); event.stopPropagation();" title="Move Down">' + TransformerData.icons.arrow_down + '</button>';
            }
            html += '<button type="button" class="remove-btn" onclick="TransformerModal.remove(' + i + '); event.stopPropagation();" title="Remove">' + TransformerData.icons.x + '</button>';
            html += '</div></div>';

            // Options
            html += '<div class="transformer-item-options">';
            if (def.options && Object.keys(def.options).length > 0) {
                for (const optKey in def.options) {
                    const opt = def.options[optKey];
                    const currentVal = item.options[optKey] || '';
                    html += '<div class="transformer-option">';
                    html += '<label>' + opt.label + (opt.required ? ' <span class="required">*</span>' : '') + '</label>';

                    if (opt.type === 'select' && opt.options) {
                        html += '<select onchange="TransformerModal.updateOption(' + i + ', \'' + optKey + '\', this.value)">';
                        if (Array.isArray(opt.options)) {
                            for (let oi = 0; oi < opt.options.length; oi++) {
                                const optItem = opt.options[oi];
                                const optValue = typeof optItem === 'object' ? optItem.value : oi;
                                const optLabel = typeof optItem === 'object' ? optItem.label : optItem;
                                const selected = currentVal === String(optValue) ? ' selected' : '';
                                html += '<option value="' + optValue + '"' + selected + '>' + optLabel + '</option>';
                            }
                        } else {
                            for (const optVal in opt.options) {
                                const selected = currentVal === optVal ? ' selected' : '';
                                html += '<option value="' + optVal + '"' + selected + '>' + opt.options[optVal] + '</option>';
                            }
                        }
                        html += '</select>';
                    } else if (opt.type === 'textarea') {
                        html += '<textarea onchange="TransformerModal.updateOption(' + i + ', \'' + optKey + '\', this.value)">' + escapeHtml(currentVal) + '</textarea>';
                    } else {
                        html += '<input type="text" value="' + escapeHtml(currentVal) + '" onchange="TransformerModal.updateOption(' + i + ', \'' + optKey + '\', this.value)">';
                    }

                    if (opt.note) {
                        html += '<div class="note">' + escapeHtml(opt.note) + '</div>';
                    }
                    html += '</div>';
                }
            } else {
                html += '<p class="fm-no-options">No options for this transformer.</p>';
            }
            html += '</div></div>';
        }

        container.innerHTML = html;
    },

    renderDropdown: function() {
        const dropdown = document.getElementById('transformer-dropdown');
        let html = '';

        for (const category in TransformerData.categories) {
            html += '<div class="transformer-dropdown-category">' + category + '</div>';
            for (const code in TransformerData.categories[category]) {
                const t = TransformerData.categories[category][code];
                html += '<div class="transformer-dropdown-item" onclick="TransformerModal.add(\'' + code + '\')">';
                html += '<div class="transformer-dropdown-item-name">' + t.name + '</div>';
                html += '<div class="transformer-dropdown-item-desc">' + t.description + '</div>';
                html += '</div>';
            }
        }

        dropdown.innerHTML = html;
    },

    toggleDropdown: function() {
        const dropdown = document.getElementById('transformer-dropdown');
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    },

    hideDropdown: function() {
        const dropdown = document.getElementById('transformer-dropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
        }
    },

    add: function(code) {
        this.chain.push({ code: code, options: {} });
        this.renderChainList();
        this.hideDropdown();
    },

    remove: function(index) {
        this.chain.splice(index, 1);
        this.renderChainList();
    },

    moveUp: function(index) {
        if (index > 0) {
            const temp = this.chain[index];
            this.chain[index] = this.chain[index - 1];
            this.chain[index - 1] = temp;
            this.renderChainList();
        }
    },

    moveDown: function(index) {
        if (index < this.chain.length - 1) {
            const temp = this.chain[index];
            this.chain[index] = this.chain[index + 1];
            this.chain[index + 1] = temp;
            this.renderChainList();
        }
    },

    toggleItem: function(index) {
        const items = document.querySelectorAll('.transformer-item');
        if (items[index]) {
            items[index].classList.toggle('expanded');
        }
    },

    updateOption: function(index, key, value) {
        if (this.chain[index]) {
            this.chain[index].options[key] = value;
        }
    }
};

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.transformer-dropdown-wrapper')) {
        TransformerModal.hideDropdown();
    }
});

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', initFeedMappingTab);

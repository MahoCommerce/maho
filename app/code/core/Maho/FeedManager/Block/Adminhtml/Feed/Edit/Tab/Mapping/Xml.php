<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * XML Builder block for feed mapping configuration
 *
 * Provides a tree-based interface for defining XML structure with element properties,
 * attribute/value mapping, CDATA options, transformer configuration, and live preview.
 */
class Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Mapping_Xml extends Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Mapping_AbstractBuilder
{
    #[\Override]
    protected function _getBuilderFormat(): string
    {
        return 'xml';
    }

    #[\Override]
    public function getBuilderHtml(): string
    {
        $feed = $this->_getFeed();
        $structure = $feed->getXmlStructure();
        $structureData = $structure ? Mage::helper('core')->jsonDecode($structure) : $this->_getDefaultXmlStructure();
        $sourceTypes = Maho_FeedManager_Model_Mapper::getSourceTypeOptions();
        $attributeOptions = $this->_getProductAttributeOptionsForEditor();
        $ruleOptions = $this->_getDynamicRuleOptionsArray();
        $platformOptions = $this->_getPlatformPresetOptions();
        $taxonomyPlatforms = Maho_FeedManager_Model_Mapper::getTaxonomyPlatformOptions();

        return '
        <div id="xml-builder-container">
            <div class="fm-toolbar">
                <select id="xml-preset-select" onchange="XmlBuilder.loadPreset(this.value)" class="fm-input-lg">
                    <option value="">' . $this->__('Load Preset...') . '</option>
                    ' . $platformOptions . '
                </select>
                <span id="xml-platform-badge" class="fm-platform-badge"' . ($feed->getPlatform() && $feed->getPlatform() !== 'custom' ? '' : ' style="display:none"') . '>' . ucfirst($feed->getPlatform() ?: '') . '</span>
                <button type="button" class="scalable" onclick="XmlBuilder.showImportModal()">
                    <span>' . $this->__('Import XML') . '</span>
                </button>
                <div class="fm-toolbar-spacer"></div>
                <button type="button" class="scalable" onclick="XmlBuilder.togglePreview()">
                    <span id="xml-preview-toggle-label">' . $this->__('Show Preview') . '</span>
                </button>
            </div>

            <div id="xml-builder-panels" class="fm-panels-container">
                <!-- Tree Panel -->
                <div id="xml-tree-panel" class="fm-panel fm-panel-main">
                    <div class="fm-panel-header">' . $this->__('Structure') . '</div>
                    <div id="xml-tree" class="fm-tree"></div>
                    <div class="fm-panel-footer">
                        <button type="button" class="scalable add" onclick="XmlBuilder.addElement()">
                            <span>' . $this->__('+ Element') . '</span>
                        </button>
                        <button type="button" class="scalable" onclick="XmlBuilder.addGroup()">
                            <span>' . $this->__('+ Group') . '</span>
                        </button>
                    </div>
                </div>

                <!-- Properties Panel -->
                <div id="xml-properties-panel" class="fm-panel fm-panel-sidebar">
                    <div class="fm-panel-header fm-panel-header-sticky">' . $this->__('Properties') . '</div>
                    <div id="xml-properties-content" class="fm-panel-content">
                        <p class="fm-status-muted a-center">' . $this->__('Select an element to edit its properties') . '</p>
                    </div>
                </div>
            </div>

            <div id="xml-preview-panel" class="fm-preview-panel" style="display:none">
                <div class="fm-preview-header">
                    <span class="fm-preview-title">' . $this->__('Preview') . '</span>
                    <button type="button" class="scalable" onclick="XmlBuilder.refreshPreview()"><span>' . $this->__('Refresh') . '</span></button>
                    <button type="button" class="scalable" onclick="XmlBuilder.copyPreview()"><span>' . $this->__('Copy') . '</span></button>
                    <button type="button" class="scalable" onclick="XmlBuilder.validateXml()"><span>' . $this->__('Validate') . '</span></button>
                    <span id="xml-validation-status"></span>
                    <span class="fm-preview-options">
                        <label class="fm-preview-checkbox">
                            <input type="checkbox" id="xml-full-preview" onchange="XmlBuilder.toggleFullPreview(this.checked)" />
                            ' . $this->__('Full Document') . '
                        </label>
                    </span>
                </div>
                <pre id="xml-preview-content" class="fm-preview-content"></pre>
            </div>
        </div>

        <div id="xml-import-modal" class="fm-modal-overlay" style="display:none">
            <div class="fm-modal">
                <h3 class="fm-modal-title">' . $this->__('Import XML Structure') . '</h3>
                <p>' . $this->__('Paste a sample XML item:') . '</p>
                <textarea id="xml-import-input" class="fm-modal-textarea" placeholder=\'<item><g:id>SKU123</g:id><title>Product Name</title></item>\'></textarea>
                <div class="fm-modal-footer">
                    <button type="button" class="scalable" onclick="XmlBuilder.hideImportModal()"><span>' . $this->__('Cancel') . '</span></button>
                    <button type="button" class="scalable save" onclick="XmlBuilder.importStructure()"><span>' . $this->__('Import') . '</span></button>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        var XmlBuilder = {
            structure: ' . Mage::helper('core')->jsonEncode($structureData) . ',
            sourceTypes: ' . Mage::helper('core')->jsonEncode($sourceTypes) . ',
            attributeOptionsHtml: ' . Mage::helper('core')->jsonEncode($attributeOptions) . ',
            ruleOptions: ' . Mage::helper('core')->jsonEncode($ruleOptions) . ',
            taxonomyPlatforms: ' . Mage::helper('core')->jsonEncode($taxonomyPlatforms) . ',
            selectedPath: null,
            previewUrl: "' . $this->getUrl('*/*/xmlPreview') . '",
            presetUrl: "' . $this->getUrl('*/*/platformPreset') . '",
            feedId: ' . (int) $feed->getId() . ',
            fullPreview: false,

            init: function() {
                if (!this.structure || !Array.isArray(this.structure) || this.structure.length === 0) {
                    this.structure = [];
                }
                this.render();
            },

            render: function() {
                var tree = document.getElementById("xml-tree");
                if (!tree) return;
                tree.innerHTML = this.renderNodes(this.structure, "", 0);
                this.updateHiddenField();
            },

            renderNodes: function(nodes, pathPrefix, depth) {
                var html = "";
                var indent = depth * 20;

                for (var i = 0; i < nodes.length; i++) {
                    var node = nodes[i];
                    var itemPath = pathPrefix ? pathPrefix + "." + i : String(i);
                    var isSelected = this.selectedPath === itemPath;
                    var nodeClass = "xml-node" + (isSelected ? " selected" : "");

                    if (node.children && node.children.length > 0) {
                        html += "<div class=\"" + nodeClass + "\" style=\"padding-left: " + indent + "px;\" onclick=\"XmlBuilder.selectNode(\'" + itemPath + "\')\" data-path=\"" + itemPath + "\">";
                        html += "<span class=\"xml-toggle\" onclick=\"XmlBuilder.toggleNode(event, \'" + itemPath + "\')\">&blacktriangledown;</span> ";
                        html += "<span class=\"xml-tag\">&lt;" + escapeHtml(node.tag) + "&gt;</span>";
                        if (node.cdata) html += " <span class=\"xml-badge\">CDATA</span>";
                        html += "</div>";
                        html += "<div class=\"xml-children\" id=\"xml-children-" + itemPath.replace(/\./g, "-") + "\">";
                        html += this.renderNodes(node.children, itemPath + ".children", depth + 1);
                        html += "</div>";
                    } else {
                        html += "<div class=\"" + nodeClass + "\" style=\"padding-left: " + indent + "px;\" onclick=\"XmlBuilder.selectNode(\'" + itemPath + "\')\" data-path=\"" + itemPath + "\">";
                        html += "<span class=\"xml-tag\">&lt;" + escapeHtml(node.tag) + "&gt;</span>";
                        if (node.cdata) html += " <span class=\"xml-badge\">CDATA</span>";
                        if (node.optional) html += " <span class=\"xml-badge optional\">optional</span>";
                        html += "</div>";
                    }
                }

                return html || "<p style=\"color: #666; padding: 10px;\">' . addslashes($this->__('Empty. Add elements using the buttons below.')) . '</p>";
            },

            selectNode: function(path) {
                this.selectedPath = path;
                this.render();
                this.showProperties(path);
            },

            showProperties: function(path) {
                var node = this.getNodeByPath(path);
                if (!node) return;

                var panel = document.getElementById("xml-properties-content");

                var html = "<div style=\"margin-bottom: 15px;\">" +
                    "<label style=\"font-weight: 600; display: block; margin-bottom: 5px;\">' . $this->__('Tag Name') . '</label>" +
                    "<input type=\"text\" class=\"input-text\" value=\"" + escapeHtml(node.tag, true) + "\" onchange=\"XmlBuilder.updateNodeProp(\'" + path + "\', \'tag\', this.value)\" style=\"width: 100%;\" placeholder=\"g:id\">" +
                    "</div>";

                // Show element properties if no children array, group properties if children array exists (even if empty)
                if (!Array.isArray(node.children)) {
                    html += "<div style=\"margin-bottom: 15px;\">" +
                        "<label style=\"font-weight: 600; display: block; margin-bottom: 5px;\">' . $this->__('Source Type') . '</label>" +
                        "<select onchange=\"XmlBuilder.updateNodeProp(\'" + path + "\', \'source_type\', this.value); XmlBuilder.showProperties(\'" + path + "\');\" style=\"width: 100%;\">" +
                        this.buildSourceTypeOptions(node.source_type) +
                        "</select>" +
                        "</div>" +
                        "<div style=\"margin-bottom: 15px;\">" +
                        "<label style=\"font-weight: 600; display: block; margin-bottom: 5px;\">' . $this->__('Source Value') . '</label>";

                    if (node.source_type === "attribute" || !node.source_type) {
                        var selectHtml = this.attributeOptionsHtml.replace(new RegExp("value=\"" + escapeHtml(node.source_value || "", true) + "\""), "value=\"" + escapeHtml(node.source_value || "", true) + "\" selected");
                        html += "<select onchange=\"XmlBuilder.updateNodeProp(\'" + path + "\', \'source_value\', this.value)\" style=\"width: 100%;\">" + selectHtml + "</select>";
                    } else if (node.source_type === "rule") {
                        html += "<select onchange=\"XmlBuilder.updateNodeProp(\'" + path + "\', \'source_value\', this.value)\" style=\"width: 100%;\">";
                        html += "<option value=\"\">' . $this->__('-- Select Rule --') . '</option>";
                        for (var ruleCode in this.ruleOptions) {
                            var selected = (node.source_value === ruleCode) ? " selected" : "";
                            html += "<option value=\"" + escapeHtml(ruleCode, true) + "\"" + selected + ">" + escapeHtml(this.ruleOptions[ruleCode]) + "</option>";
                        }
                        html += "</select>";
                    } else if (node.source_type === "taxonomy") {
                        html += "<select onchange=\"XmlBuilder.updateNodeProp(\'" + path + "\', \'source_value\', this.value)\" style=\"width: 100%;\">";
                        for (var platform in this.taxonomyPlatforms) {
                            var selected = (node.source_value === platform) ? " selected" : "";
                            html += "<option value=\"" + escapeHtml(platform, true) + "\"" + selected + ">" + escapeHtml(this.taxonomyPlatforms[platform]) + "</option>";
                        }
                        html += "</select>";
                    } else {
                        var placeholder = node.source_type === "combined" ? "{{name}} - {{sku}}" : "";
                        html += "<input type=\"text\" class=\"input-text\" value=\"" + escapeHtml(node.source_value || "", true) + "\" onchange=\"XmlBuilder.updateNodeProp(\'" + path + "\', \'source_value\', this.value)\" placeholder=\"" + placeholder + "\" style=\"width: 100%;\">";
                    }

                    html += "</div>" +
                        "<div style=\"margin-bottom: 15px;\">" +
                        "<label style=\"font-weight: 600; display: block; margin-bottom: 5px;\">' . $this->__('Use Parent') . '</label>" +
                        "<select onchange=\"XmlBuilder.updateNodeProp(\'" + path + "\', \'use_parent\', this.value)\" style=\"width: 100%;\">" +
                        "<option value=\"\"" + (!node.use_parent ? " selected" : "") + ">—</option>" +
                        "<option value=\"if_empty\"" + (node.use_parent === "if_empty" ? " selected" : "") + ">' . $this->__('If empty') . '</option>" +
                        "<option value=\"always\"" + (node.use_parent === "always" ? " selected" : "") + ">' . $this->__('Always') . '</option>" +
                        "</select>" +
                        "<p style=\"margin: 4px 0 0; font-size: 11px; color: #888;\">' . $this->__('For simple products, use parent (configurable) value') . '</p>" +
                        "</div>" +
                        "<div style=\"margin-bottom: 15px;\">" +
                        "<label style=\"font-weight: 600; display: block; margin-bottom: 5px;\">' . $this->__('Transformers') . '</label>" +
                        "<button type=\"button\" class=\"scalable\" onclick=\"XmlBuilder.openTransformers(\'" + path + "\')\"><span>" + (node.transformers ? node.transformers.split("|").length + " transforms" : "+ Add") + "</span></button>" +
                        "</div>" +
                        "<div style=\"display: flex; gap: 15px; margin-bottom: 12px;\">" +
                        "<div style=\"flex: 1;\">" +
                        "<label style=\"font-weight: 600; display: block; margin-bottom: 5px;\">' . $this->__('CDATA') . '</label>" +
                        "<select onchange=\"XmlBuilder.updateNodeProp(\'" + path + "\', \'cdata\', this.value === \'1\')\" style=\"width: 100%;\">" +
                        "<option value=\"0\"" + (!node.cdata ? " selected" : "") + ">' . $this->__('No') . '</option>" +
                        "<option value=\"1\"" + (node.cdata ? " selected" : "") + ">' . $this->__('Yes') . '</option>" +
                        "</select>" +
                        "</div>" +
                        "<div style=\"flex: 1;\">" +
                        "<label style=\"font-weight: 600; display: block; margin-bottom: 5px;\">' . $this->__('Optional') . '</label>" +
                        "<select onchange=\"XmlBuilder.updateNodeProp(\'" + path + "\', \'optional\', this.value === \'1\')\" style=\"width: 100%;\">" +
                        "<option value=\"0\"" + (!node.optional ? " selected" : "") + ">' . $this->__('No') . '</option>" +
                        "<option value=\"1\"" + (node.optional ? " selected" : "") + ">' . $this->__('Yes') . '</option>" +
                        "</select>" +
                        "</div>" +
                        "</div>" +
                        "<p style=\"margin: 0 0 15px; font-size: 11px; color: #888;\">' . $this->__('CDATA wraps special characters. Optional skips element if value is empty.') . '</p>";
                } else {
                    html += "<p style=\"color: #666; font-size: 11px;\">' . $this->__('This is a group element. Add child elements by selecting it and clicking + Element.') . '</p>" +
                        "<div style=\"margin-top: 15px;\">" +
                        "<button type=\"button\" class=\"scalable add\" onclick=\"XmlBuilder.addChildElement(\'" + path + "\')\"><span>' . $this->__('+ Child Element') . '</span></button>" +
                        "</div>";
                }

                html += "<div style=\"border-top: 1px solid #ddd; padding-top: 15px; margin-top: 15px; display: flex; gap: 10px;\">" +
                    "<button type=\"button\" class=\"scalable\" onclick=\"XmlBuilder.duplicateNode(\'" + path + "\')\"><span>' . $this->__('Duplicate') . '</span></button>" +
                    "<button type=\"button\" class=\"scalable delete\" onclick=\"XmlBuilder.deleteNode(\'" + path + "\')\"><span>' . $this->__('Delete') . '</span></button>" +
                    "</div>";

                panel.innerHTML = html;
            },

            buildSourceTypeOptions: function(selected) {
                var html = "";
                for (var key in this.sourceTypes) {
                    html += "<option value=\"" + key + "\"" + (selected === key ? " selected" : "") + ">" + this.sourceTypes[key] + "</option>";
                }
                return html;
            },

            getNodeByPath: function(path) {
                var parts = path.split(".");
                var current = this.structure;
                for (var i = 0; i < parts.length; i++) {
                    var part = parts[i];
                    if (part === "children") {
                        current = current.children;
                    } else {
                        current = current[parseInt(part)];
                    }
                    if (!current) return null;
                }
                return current;
            },

            getParentAndIndex: function(path) {
                var parts = path.split(".");
                var index = parseInt(parts.pop());
                if (parts.length === 0) {
                    return { parent: this.structure, index: index };
                }
                var parentPath = parts.join(".");
                var parent = this.getNodeByPath(parentPath);
                return { parent: parent, index: index };
            },

            updateNodeProp: function(path, prop, value) {
                var node = this.getNodeByPath(path);
                if (node) {
                    node[prop] = value;
                    this.render();
                    this.showProperties(path);
                }
            },

            addElement: function() {
                this.structure.push({
                    tag: "element_" + this.structure.length,
                    source_type: "attribute",
                    source_value: "",
                    cdata: false,
                    optional: false
                });
                var newPath = String(this.structure.length - 1);
                this.render();
                this.selectNode(newPath);
            },

            addGroup: function() {
                this.structure.push({
                    tag: "group_" + this.structure.length,
                    children: []
                });
                var newPath = String(this.structure.length - 1);
                this.render();
                this.selectNode(newPath);
            },

            addChildElement: function(parentPath) {
                var parent = this.getNodeByPath(parentPath);
                if (!parent || !parent.children) return;
                parent.children.push({
                    tag: "element_" + parent.children.length,
                    source_type: "attribute",
                    source_value: "",
                    cdata: false,
                    optional: false
                });
                var newPath = parentPath + ".children." + (parent.children.length - 1);
                this.render();
                this.selectNode(newPath);
            },

            deleteNode: function(path) {
                var info = this.getParentAndIndex(path);
                if (info.parent && Array.isArray(info.parent)) {
                    info.parent.splice(info.index, 1);
                    this.selectedPath = null;
                    this.render();
                    document.getElementById("xml-properties-content").innerHTML = "<p style=\"color: #666; text-align: center;\">' . addslashes($this->__('Select an element to edit its properties')) . '</p>";
                }
            },

            duplicateNode: function(path) {
                var info = this.getParentAndIndex(path);
                if (info.parent && Array.isArray(info.parent)) {
                    var original = info.parent[info.index];
                    var copy = JSON.parse(JSON.stringify(original));
                    copy.tag = original.tag + "_copy";
                    info.parent.splice(info.index + 1, 0, copy);
                    var newPath = path.replace(/\d+$/, String(info.index + 1));
                    this.render();
                    this.selectNode(newPath);
                }
            },

            toggleNode: function(e, path) {
                e.stopPropagation();
                var children = document.getElementById("xml-children-" + path.replace(/\./g, "-"));
                var toggle = e.target;
                if (children) {
                    if (children.style.display === "none") {
                        children.style.display = "block";
                        toggle.innerHTML = "&blacktriangledown;";
                    } else {
                        children.style.display = "none";
                        toggle.innerHTML = "&blacktriangleright;";
                    }
                }
            },

            updateHiddenField: function() {
                var field = document.getElementById("mapping_xml_structure");
                if (field) {
                    field.value = JSON.stringify(this.structure);
                }
            },

            openTransformers: function(path) {
                XmlBuilder.currentNodePath = path;
                var node = this.getNodeByPath(path);
                document.getElementById("editor_transformers").value = node.transformers || "";
                TransformerModal.open();
            },

            loadPreset: function(platform) {
                if (!platform) return;

                // Confirm before overwriting existing structure
                if (this.structure && this.structure.length > 0) {
                    if (!confirm("Loading a preset will replace your current XML structure. Continue?")) {
                        document.getElementById("xml-preset-select").value = "";
                        return;
                    }
                }

                var self = this;

                mahoFetch(this.presetUrl, {
                    method: "POST",
                    body: new URLSearchParams({
                        platform: platform,
                        format: "xml",
                        form_key: FORM_KEY
                    }),
                    loaderArea: false
                })
                .then(function(data) {
                    if (data.error) {
                        alert("Error: " + data.message);
                    } else if (data.structure) {
                        self.structure = data.structure;
                        self.selectedPath = null;
                        self.render();
                        document.getElementById("xml-properties-content").innerHTML = "<p style=\"color: #666; text-align: center;\">' . addslashes($this->__('Select an element to edit its properties')) . '</p>";
                        // Update platform field in General tab
                        self.updatePlatform(data.platform);
                    }
                })
                .catch(function(err) {
                    alert("Error loading preset: " + err.message);
                });

                document.getElementById("xml-preset-select").value = "";
            },

            updatePlatform: function(platform) {
                var platformField = document.getElementById("platform") || document.querySelector("input[name=\'platform\']");
                if (platformField) {
                    platformField.value = platform;
                }
                var badge = document.getElementById("xml-platform-badge");
                if (badge) {
                    badge.textContent = platform ? platform.charAt(0).toUpperCase() + platform.slice(1) : "";
                    badge.style.display = platform ? "inline-block" : "none";
                }
            },

            showImportModal: function() {
                document.getElementById("xml-import-modal").style.display = "block";
            },

            hideImportModal: function() {
                document.getElementById("xml-import-modal").style.display = "none";
                document.getElementById("xml-import-input").value = "";
            },

            importStructure: function() {
                try {
                    var input = document.getElementById("xml-import-input").value.trim();
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(input, "text/xml");
                    var errorNode = doc.querySelector("parsererror");
                    if (errorNode) {
                        throw new Error("Invalid XML");
                    }
                    this.structure = this.convertXmlToStructure(doc.documentElement);
                    this.render();
                    this.hideImportModal();
                } catch (e) {
                    alert("Invalid XML: " + e.message);
                }
            },

            convertXmlToStructure: function(element) {
                var result = [];
                var children = element.children;
                for (var i = 0; i < children.length; i++) {
                    var child = children[i];
                    var node = {
                        tag: child.tagName,
                        source_type: "attribute",
                        source_value: ""
                    };
                    if (child.children.length > 0) {
                        node.children = this.convertXmlToStructure(child);
                    }
                    result.push(node);
                }
                return result;
            },

            togglePreview: function() {
                var panel = document.getElementById("xml-preview-panel");
                var label = document.getElementById("xml-preview-toggle-label");
                if (panel.style.display === "none") {
                    panel.style.display = "block";
                    label.textContent = "' . $this->__('Hide Preview') . '";
                    this.refreshPreview();
                } else {
                    panel.style.display = "none";
                    label.textContent = "' . $this->__('Show Preview') . '";
                }
            },

            refreshPreview: function() {
                var content = document.getElementById("xml-preview-content");
                content.textContent = "' . $this->__('Loading...') . '";

                mahoFetch(this.previewUrl, {
                    method: "POST",
                    body: new URLSearchParams({
                        id: this.feedId,
                        structure: JSON.stringify(this.structure),
                        full_preview: this.fullPreview ? 1 : 0,
                        form_key: FORM_KEY
                    }),
                    loaderArea: false
                })
                .then(function(data) {
                    if (data.error) {
                        content.textContent = "Error: " + data.message;
                    } else {
                        content.textContent = data.preview;
                    }
                })
                .catch(function(err) {
                    content.textContent = "Error loading preview";
                });
            },

            toggleFullPreview: function(checked) {
                this.fullPreview = checked;
                this.refreshPreview();
            },

            copyPreview: function() {
                navigator.clipboard.writeText(document.getElementById("xml-preview-content").textContent);
            },

            validateXml: function() {
                var content = document.getElementById("xml-preview-content").textContent;
                var status = document.getElementById("xml-validation-status");

                if (!content || content.trim() === "") {
                    status.innerHTML = \'<span style="color: #666;">' . $this->__('No content to validate') . '</span>\';
                    return;
                }

                // Wrap in root element if not full preview (items only)
                var xmlToValidate = content;
                if (!this.fullPreview) {
                    xmlToValidate = "<root>" + content + "</root>";
                }

                var parser = new DOMParser();
                var doc = parser.parseFromString(xmlToValidate, "application/xml");
                var parseError = doc.querySelector("parsererror");

                if (parseError) {
                    var errorText = parseError.textContent || "' . $this->__('Invalid XML') . '";
                    // Extract just the error message, not the full verbose output
                    var match = errorText.match(/error[^:]*:\s*(.+?)(?:\n|$)/i);
                    var shortError = match ? match[1].trim() : "' . $this->__('Invalid XML structure') . '";
                    status.innerHTML = \'<span style="color: #c62828;">&#10008; \' + escapeHtml(shortError) + \'</span>\';
                } else {
                    status.innerHTML = \'<span style="color: #2e7d32;">&#10004; ' . $this->__('Valid XML') . '</span>\';
                }
            }
        };

        document.addEventListener("DOMContentLoaded", function() {
            XmlBuilder.init();
        });
        </script>

        <style>
        .xml-node { padding: 5px 10px; cursor: pointer; border-radius: 3px; margin: 2px 0; }
        .xml-node:hover { background: #f5f5f5; }
        .xml-node.selected { background: #e8f5e9; }
        .xml-tag { font-weight: 600; color: #2e7d32; }
        .xml-badge { font-size: 10px; padding: 2px 5px; background: #e0e0e0; border-radius: 3px; color: #666; }
        .xml-badge.optional { background: #fff3e0; color: #e65100; }
        .xml-toggle { cursor: pointer; color: #666; }
        .xml-children { margin-left: 10px; }
        </style>
        ';
    }

    /**
     * Get default XML structure for new feeds
     */
    protected function _getDefaultXmlStructure(): array
    {
        return [
            ['tag' => 'g:id', 'source_type' => 'attribute', 'source_value' => 'sku', 'cdata' => false, 'optional' => false],
            ['tag' => 'g:title', 'source_type' => 'attribute', 'source_value' => 'name', 'cdata' => true, 'optional' => false],
            ['tag' => 'g:description', 'source_type' => 'attribute', 'source_value' => 'description', 'cdata' => true, 'optional' => true, 'use_parent' => 'if_empty'],
            ['tag' => 'g:link', 'source_type' => 'attribute', 'source_value' => 'url', 'cdata' => false, 'optional' => false, 'use_parent' => 'if_empty'],
            ['tag' => 'g:image_link', 'source_type' => 'attribute', 'source_value' => 'image', 'cdata' => false, 'optional' => true, 'use_parent' => 'if_empty'],
            ['tag' => 'g:availability', 'source_type' => 'attribute', 'source_value' => 'is_in_stock', 'cdata' => false, 'optional' => false],
            ['tag' => 'g:price', 'source_type' => 'attribute', 'source_value' => 'price', 'cdata' => false, 'optional' => false],
            ['tag' => 'g:brand', 'source_type' => 'attribute', 'source_value' => 'brand', 'cdata' => true, 'optional' => true],
            ['tag' => 'g:condition', 'source_type' => 'static', 'source_value' => 'new', 'cdata' => false, 'optional' => true],
        ];
    }
}

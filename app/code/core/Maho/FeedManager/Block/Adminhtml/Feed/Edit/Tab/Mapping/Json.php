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
 * JSON Builder block for feed mapping configuration
 *
 * Provides a tree-based interface for defining JSON structure with property editing,
 * attribute/value mapping, transformer configuration, and live preview.
 */
class Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Mapping_Json extends Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Mapping_AbstractBuilder
{
    #[\Override]
    protected function _getBuilderFormat(): string
    {
        return 'json';
    }

    #[\Override]
    public function getBuilderHtml(): string
    {
        $feed = $this->_getFeed();
        $structure = $feed->getJsonStructure();
        $structureData = $structure ? Mage::helper('core')->jsonDecode($structure) : new stdClass();
        $sourceTypes = Maho_FeedManager_Model_Mapper::getSourceTypeOptions();
        $attributeOptions = $this->_getProductAttributeOptionsForEditor();
        $ruleOptions = $this->_getDynamicRuleOptionsArray();
        $platformOptions = $this->_getPlatformPresetOptions();
        $taxonomyPlatforms = Maho_FeedManager_Model_Mapper::getTaxonomyPlatformOptions();

        return '
        <div id="json-builder-container">
            <div class="fm-toolbar">
                <select id="json-preset-select" onchange="JsonBuilder.loadPreset(this.value)" class="fm-input-lg">
                    <option value="">' . $this->__('Load Preset...') . '</option>
                    ' . $platformOptions . '
                </select>
                <span id="json-platform-badge" class="fm-platform-badge"' . ($feed->getPlatform() && $feed->getPlatform() !== 'custom' ? '' : ' style="display:none"') . '>' . ucfirst($feed->getPlatform() ?: '') . '</span>
                <button type="button" class="scalable" onclick="JsonBuilder.showImportModal()">
                    <span>' . $this->__('Import JSON') . '</span>
                </button>
                <div class="fm-toolbar-spacer"></div>
                <button type="button" class="scalable" onclick="JsonBuilder.togglePreview()">
                    <span id="json-preview-toggle-label">' . $this->__('Show Preview') . '</span>
                </button>
            </div>

            <div id="json-builder-panels" class="fm-panels-container">
                <!-- Tree Panel -->
                <div id="json-tree-panel" class="fm-panel fm-panel-main">
                    <div class="fm-panel-header">' . $this->__('Structure') . '</div>
                    <div id="json-tree" class="fm-tree"></div>
                    <div class="fm-panel-footer">
                        <button type="button" class="scalable add" onclick="JsonBuilder.addField()">
                            <span>' . $this->__('+ Field') . '</span>
                        </button>
                        <button type="button" class="scalable" onclick="JsonBuilder.addObject()">
                            <span>' . $this->__('+ Object') . '</span>
                        </button>
                        <button type="button" class="scalable" onclick="JsonBuilder.addArray()">
                            <span>' . $this->__('+ Array') . '</span>
                        </button>
                    </div>
                </div>

                <!-- Properties Panel -->
                <div id="json-properties-panel" class="fm-panel fm-panel-sidebar">
                    <div class="fm-panel-header fm-panel-header-sticky">' . $this->__('Properties') . '</div>
                    <div id="json-properties-content" class="fm-panel-content">
                        <p class="fm-status-muted a-center">' . $this->__('Select a node to edit its properties') . '</p>
                    </div>
                </div>
            </div>

            <div id="json-preview-panel" class="fm-preview-panel" style="display:none">
                <div class="fm-preview-header">
                    <span class="fm-preview-title">' . $this->__('Preview') . '</span>
                    <button type="button" class="scalable" onclick="JsonBuilder.refreshPreview()"><span>' . $this->__('Refresh') . '</span></button>
                    <button type="button" class="scalable" onclick="JsonBuilder.copyPreview()"><span>' . $this->__('Copy') . '</span></button>
                    <button type="button" class="scalable" onclick="JsonBuilder.validateJson()"><span>' . $this->__('Validate') . '</span></button>
                    <span id="json-validation-status"></span>
                </div>
                <pre id="json-preview-content" class="fm-preview-content"></pre>
            </div>
        </div>

        <div id="json-import-modal" class="fm-modal-overlay" style="display:none">
            <div class="fm-modal">
                <h3 class="fm-modal-title">' . $this->__('Import JSON Structure') . '</h3>
                <p>' . $this->__('Paste a sample JSON object:') . '</p>
                <textarea id="json-import-input" class="fm-modal-textarea" placeholder=\'{"id": "SKU123", "title": "Product Name"}\'></textarea>
                <div class="fm-modal-footer">
                    <button type="button" class="scalable" onclick="JsonBuilder.hideImportModal()"><span>' . $this->__('Cancel') . '</span></button>
                    <button type="button" class="scalable save" onclick="JsonBuilder.importStructure()"><span>' . $this->__('Import') . '</span></button>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        var JsonBuilder = {
            structure: ' . Mage::helper('core')->jsonEncode($structureData) . ',
            sourceTypes: ' . Mage::helper('core')->jsonEncode($sourceTypes) . ',
            attributeOptionsHtml: ' . Mage::helper('core')->jsonEncode($attributeOptions) . ',
            ruleOptions: ' . Mage::helper('core')->jsonEncode($ruleOptions) . ',
            taxonomyPlatforms: ' . Mage::helper('core')->jsonEncode($taxonomyPlatforms) . ',
            selectedPath: null,
            previewUrl: "' . $this->getUrl('*/*/jsonPreview') . '",
            presetUrl: "' . $this->getUrl('*/*/platformPreset') . '",
            feedId: ' . (int) $feed->getId() . ',

            init: function() {
                if (!this.structure || Object.keys(this.structure).length === 0) {
                    this.structure = {};
                }
                this.render();
            },

            render: function() {
                var tree = document.getElementById("json-tree");
                if (!tree) return;
                tree.innerHTML = this.renderNode(this.structure, "", 0);
                this.updateHiddenField();
            },

            renderNode: function(node, path, depth) {
                var html = "";
                var indent = depth * 20;

                for (var key in node) {
                    if (!node.hasOwnProperty(key)) continue;
                    var item = node[key];
                    var itemPath = path ? path + "." + key : key;
                    var isSelected = this.selectedPath === itemPath;
                    var nodeClass = "json-node" + (isSelected ? " selected" : "");

                    if (item.type === "object" && item.properties) {
                        html += "<div class=\"" + nodeClass + "\" style=\"padding-left: " + indent + "px;\" onclick=\"JsonBuilder.selectNode(\'" + itemPath + "\')\" data-path=\"" + itemPath + "\">";
                        html += "<span class=\"json-toggle\" onclick=\"JsonBuilder.toggleNode(event, \'" + itemPath + "\')\">&blacktriangledown;</span> ";
                        html += "<span class=\"json-key\">" + escapeHtml(key) + "</span> <span class=\"json-type\">(object)</span>";
                        html += "</div>";
                        html += "<div class=\"json-children\" id=\"json-children-" + itemPath.replace(/\./g, "-") + "\">";
                        html += this.renderNode(item.properties, itemPath + ".properties", depth + 1);
                        html += "</div>";
                    } else if (item.type === "array") {
                        html += "<div class=\"" + nodeClass + "\" style=\"padding-left: " + indent + "px;\" onclick=\"JsonBuilder.selectNode(\'" + itemPath + "\')\" data-path=\"" + itemPath + "\">";
                        html += "<span class=\"json-toggle\" onclick=\"JsonBuilder.toggleNode(event, \'" + itemPath + "\')\">&blacktriangledown;</span> ";
                        html += "<span class=\"json-key\">" + escapeHtml(key) + "</span> <span class=\"json-type\">(array)</span>";
                        html += "</div>";
                        if (item.items) {
                            html += "<div class=\"json-children\" id=\"json-children-" + itemPath.replace(/\./g, "-") + "\">";
                            html += "<div class=\"json-node\" style=\"padding-left: " + (indent + 20) + "px; color: #666;\">";
                            html += "└─ " + (item.items.type || "string");
                            html += "</div>";
                            html += "</div>";
                        }
                    } else {
                        html += "<div class=\"" + nodeClass + "\" style=\"padding-left: " + indent + "px;\" onclick=\"JsonBuilder.selectNode(\'" + itemPath + "\')\" data-path=\"" + itemPath + "\">";
                        html += "<span class=\"json-key\">" + escapeHtml(key) + "</span> <span class=\"json-type\">(" + (item.type || "string") + ")</span>";
                        html += "</div>";
                    }
                }

                return html || "<p style=\"color: #666; padding: 10px;\">' . addslashes($this->__('Empty. Add fields using the buttons below.')) . '</p>";
            },

            selectNode: function(path) {
                this.selectedPath = path;
                this.render();
                this.showProperties(path);
            },

            showProperties: function(path) {
                var node = this.getNodeByPath(path);
                if (!node) return;

                var panel = document.getElementById("json-properties-content");
                var keyName = path.split(".").pop();

                var html = "<div style=\"margin-bottom: 15px;\">" +
                    "<label style=\"font-weight: 600; display: block; margin-bottom: 5px;\">' . $this->__('Key') . '</label>" +
                    "<input type=\"text\" class=\"input-text\" value=\"" + escapeHtml(keyName, true) + "\" onchange=\"JsonBuilder.updateKey(\'" + path + "\', this.value)\" style=\"width: 100%;\">" +
                    "</div>" +
                    "<div style=\"margin-bottom: 15px;\">" +
                    "<label style=\"font-weight: 600; display: block; margin-bottom: 5px;\">' . $this->__('Type') . '</label>" +
                    "<select onchange=\"JsonBuilder.updateNodeProp(\'" + path + "\', \'type\', this.value)\" style=\"width: 100%;\">" +
                    "<option value=\"string\"" + (node.type === "string" || !node.type ? " selected" : "") + ">' . $this->__('String') . '</option>" +
                    "<option value=\"number\"" + (node.type === "number" ? " selected" : "") + ">' . $this->__('Number') . '</option>" +
                    "<option value=\"boolean\"" + (node.type === "boolean" ? " selected" : "") + ">' . $this->__('Boolean') . '</option>" +
                    "<option value=\"object\"" + (node.type === "object" ? " selected" : "") + ">' . $this->__('Object') . '</option>" +
                    "<option value=\"array\"" + (node.type === "array" ? " selected" : "") + ">' . $this->__('Array') . '</option>" +
                    "</select>" +
                    "</div>";

                if (node.type !== "object" && node.type !== "array") {
                    html += "<div style=\"margin-bottom: 15px;\">" +
                        "<label style=\"font-weight: 600; display: block; margin-bottom: 5px;\">' . $this->__('Source Type') . '</label>" +
                        "<select onchange=\"JsonBuilder.updateNodeProp(\'" + path + "\', \'source_type\', this.value); JsonBuilder.showProperties(\'" + path + "\');\" style=\"width: 100%;\">" +
                        this.buildSourceTypeOptions(node.source_type) +
                        "</select>" +
                        "</div>" +
                        "<div style=\"margin-bottom: 15px;\">" +
                        "<label style=\"font-weight: 600; display: block; margin-bottom: 5px;\">' . $this->__('Source Value') . '</label>";

                    // Show attribute dropdown, rule dropdown, taxonomy dropdown, or text input based on source type
                    if (node.source_type === "attribute") {
                        var selectHtml = this.attributeOptionsHtml.replace(new RegExp("value=\"" + escapeHtml(node.source_value || "", true) + "\""), "value=\"" + escapeHtml(node.source_value || "", true) + "\" selected");
                        html += "<select onchange=\"JsonBuilder.updateNodeProp(\'" + path + "\', \'source_value\', this.value)\" style=\"width: 100%;\">" + selectHtml + "</select>";
                    } else if (node.source_type === "rule") {
                        html += "<select onchange=\"JsonBuilder.updateNodeProp(\'" + path + "\', \'source_value\', this.value)\" style=\"width: 100%;\">";
                        html += "<option value=\"\">' . $this->__('-- Select Rule --') . '</option>";
                        for (var ruleCode in this.ruleOptions) {
                            var selected = (node.source_value === ruleCode) ? " selected" : "";
                            html += "<option value=\"" + escapeHtml(ruleCode, true) + "\"" + selected + ">" + escapeHtml(this.ruleOptions[ruleCode]) + "</option>";
                        }
                        html += "</select>";
                    } else if (node.source_type === "taxonomy") {
                        html += "<select onchange=\"JsonBuilder.updateNodeProp(\'" + path + "\', \'source_value\', this.value)\" style=\"width: 100%;\">";
                        for (var platform in this.taxonomyPlatforms) {
                            var selected = (node.source_value === platform) ? " selected" : "";
                            html += "<option value=\"" + escapeHtml(platform, true) + "\"" + selected + ">" + escapeHtml(this.taxonomyPlatforms[platform]) + "</option>";
                        }
                        html += "</select>";
                    } else {
                        var placeholder = node.source_type === "combined" ? "{{name}} - {{sku}}" : "";
                        html += "<input type=\"text\" class=\"input-text\" value=\"" + escapeHtml(node.source_value || "", true) + "\" onchange=\"JsonBuilder.updateNodeProp(\'" + path + "\', \'source_value\', this.value)\" placeholder=\"" + placeholder + "\" style=\"width: 100%;\">";
                    }

                    html += "</div>" +
                        "<div style=\"margin-bottom: 15px;\">" +
                        "<label style=\"font-weight: 600; display: block; margin-bottom: 5px;\">' . $this->__('Use Parent') . '</label>" +
                        "<select onchange=\"JsonBuilder.updateNodeProp(\'" + path + "\', \'use_parent\', this.value)\" style=\"width: 100%;\">" +
                        "<option value=\"\"" + (!node.use_parent ? " selected" : "") + ">—</option>" +
                        "<option value=\"if_empty\"" + (node.use_parent === "if_empty" ? " selected" : "") + ">' . $this->__('If empty') . '</option>" +
                        "<option value=\"always\"" + (node.use_parent === "always" ? " selected" : "") + ">' . $this->__('Always') . '</option>" +
                        "</select>" +
                        "</div>" +
                        "<div style=\"margin-bottom: 15px;\">" +
                        "<label style=\"font-weight: 600; display: block; margin-bottom: 5px;\">' . $this->__('Transformers') . '</label>" +
                        "<button type=\"button\" class=\"scalable\" onclick=\"JsonBuilder.openTransformers(\'" + path + "\')\"><span>" + (node.transformers ? node.transformers.split("|").length + " transforms" : "+ Add") + "</span></button>" +
                        "</div>";
                }

                html += "<div style=\"border-top: 1px solid #ddd; padding-top: 15px; margin-top: 15px; display: flex; gap: 10px;\">" +
                    "<button type=\"button\" class=\"scalable\" onclick=\"JsonBuilder.duplicateNode(\'" + path + "\')\"><span>' . $this->__('Duplicate') . '</span></button>" +
                    "<button type=\"button\" class=\"scalable delete\" onclick=\"JsonBuilder.deleteNode(\'" + path + "\')\"><span>' . $this->__('Delete') . '</span></button>" +
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
                    if (!current[parts[i]]) return null;
                    current = current[parts[i]];
                }
                return current;
            },

            setNodeByPath: function(path, value) {
                var parts = path.split(".");
                var current = this.structure;
                for (var i = 0; i < parts.length - 1; i++) {
                    current = current[parts[i]];
                }
                current[parts[parts.length - 1]] = value;
            },

            updateNodeProp: function(path, prop, value) {
                var node = this.getNodeByPath(path);
                if (node) {
                    node[prop] = value;
                    this.render();
                    this.showProperties(path);
                }
            },

            updateKey: function(path, newKey) {
                var parts = path.split(".");
                var oldKey = parts.pop();
                var parent = parts.length > 0 ? this.getNodeByPath(parts.join(".")) : this.structure;

                if (parent && parent[oldKey]) {
                    parent[newKey] = parent[oldKey];
                    delete parent[oldKey];
                    this.selectedPath = parts.length > 0 ? parts.join(".") + "." + newKey : newKey;
                    this.render();
                    this.showProperties(this.selectedPath);
                }
            },

            addField: function() {
                var name = "field_" + Object.keys(this.structure).length;
                this.structure[name] = {type: "string", source_type: "attribute", source_value: ""};
                this.render();
                this.selectNode(name);
            },

            addObject: function() {
                var name = "object_" + Object.keys(this.structure).length;
                this.structure[name] = {type: "object", properties: {}};
                this.render();
                this.selectNode(name);
            },

            addArray: function() {
                var name = "array_" + Object.keys(this.structure).length;
                this.structure[name] = {type: "array", items: {type: "string", source_type: "attribute", source_value: ""}};
                this.render();
                this.selectNode(name);
            },

            deleteNode: function(path) {
                var parts = path.split(".");
                var key = parts.pop();
                var parent = parts.length > 0 ? this.getNodeByPath(parts.join(".")) : this.structure;
                if (parent) {
                    delete parent[key];
                    this.selectedPath = null;
                    this.render();
                    document.getElementById("json-properties-content").innerHTML = "<p style=\"color: #666; text-align: center;\">' . addslashes($this->__('Select a node to edit its properties')) . '</p>";
                }
            },

            duplicateNode: function(path) {
                var parts = path.split(".");
                var key = parts.pop();
                var parent = parts.length > 0 ? this.getNodeByPath(parts.join(".")) : this.structure;
                if (parent && parent[key]) {
                    var copy = JSON.parse(JSON.stringify(parent[key]));
                    var newKey = key + "_copy";
                    var counter = 1;
                    while (parent[newKey]) {
                        newKey = key + "_copy" + counter;
                        counter++;
                    }
                    parent[newKey] = copy;
                    var newPath = parts.length > 0 ? parts.join(".") + "." + newKey : newKey;
                    this.render();
                    this.selectNode(newPath);
                }
            },

            toggleNode: function(e, path) {
                e.stopPropagation();
                var children = document.getElementById("json-children-" + path.replace(/\./g, "-"));
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
                var field = document.getElementById("mapping_json_structure");
                if (field) {
                    field.value = JSON.stringify(this.structure);
                }
            },

            openTransformers: function(path) {
                JsonBuilder.currentNodePath = path;
                var node = this.getNodeByPath(path);
                document.getElementById("editor_transformers").value = node.transformers || "";
                TransformerModal.open();
            },

            loadPreset: function(platform) {
                if (!platform) return;

                // Confirm before overwriting existing structure
                var hasContent = this.structure && (
                    (Array.isArray(this.structure) && this.structure.length > 0) ||
                    (typeof this.structure === "object" && Object.keys(this.structure).length > 0)
                );
                if (hasContent) {
                    if (!confirm("Loading a preset will replace your current JSON structure. Continue?")) {
                        document.getElementById("json-preset-select").value = "";
                        return;
                    }
                }

                var self = this;

                mahoFetch(this.presetUrl, {
                    method: "POST",
                    body: new URLSearchParams({
                        platform: platform,
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
                        document.getElementById("json-properties-content").innerHTML = "<p style=\"color: #666; text-align: center;\">' . addslashes($this->__('Select a node to edit its properties')) . '</p>";
                        // Update platform field in General tab
                        self.updatePlatform(data.platform);
                    }
                })
                .catch(function(err) {
                    alert("Error loading preset: " + err.message);
                });

                document.getElementById("json-preset-select").value = "";
            },

            updatePlatform: function(platform) {
                var platformField = document.getElementById("platform") || document.querySelector("input[name=\'platform\']");
                if (platformField) {
                    platformField.value = platform;
                }
                var badge = document.getElementById("json-platform-badge");
                if (badge) {
                    badge.textContent = platform ? platform.charAt(0).toUpperCase() + platform.slice(1) : "";
                    badge.style.display = platform ? "inline-block" : "none";
                }
            },

            showImportModal: function() {
                document.getElementById("json-import-modal").style.display = "block";
            },

            hideImportModal: function() {
                document.getElementById("json-import-modal").style.display = "none";
                document.getElementById("json-import-input").value = "";
            },

            importStructure: function() {
                try {
                    var input = document.getElementById("json-import-input").value.trim();
                    var parsed = JSON.parse(input);
                    this.structure = this.convertToBuilderFormat(parsed);
                    this.render();
                    this.hideImportModal();
                } catch (e) {
                    alert("Invalid JSON: " + e.message);
                }
            },

            convertToBuilderFormat: function(obj) {
                var result = {};
                for (var key in obj) {
                    var val = obj[key];
                    if (Array.isArray(val)) {
                        result[key] = {type: "array", items: {type: "string", source_type: "attribute", source_value: ""}};
                    } else if (typeof val === "object" && val !== null) {
                        result[key] = {type: "object", properties: this.convertToBuilderFormat(val)};
                    } else if (typeof val === "number") {
                        result[key] = {type: "number", source_type: "attribute", source_value: ""};
                    } else if (typeof val === "boolean") {
                        result[key] = {type: "boolean", source_type: "attribute", source_value: ""};
                    } else {
                        result[key] = {type: "string", source_type: "attribute", source_value: ""};
                    }
                }
                return result;
            },

            togglePreview: function() {
                var panel = document.getElementById("json-preview-panel");
                var label = document.getElementById("json-preview-toggle-label");
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
                var content = document.getElementById("json-preview-content");
                content.textContent = "' . $this->__('Loading...') . '";

                mahoFetch(this.previewUrl, {
                    method: "POST",
                    body: new URLSearchParams({
                        id: this.feedId,
                        structure: JSON.stringify(this.structure),
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

            copyPreview: function() {
                navigator.clipboard.writeText(document.getElementById("json-preview-content").textContent);
            },

            validateJson: function() {
                var content = document.getElementById("json-preview-content").textContent;
                var status = document.getElementById("json-validation-status");

                if (!content || content.trim() === "") {
                    status.innerHTML = \'<span style="color: #666;">' . $this->__('No content to validate') . '</span>\';
                    return;
                }

                try {
                    JSON.parse(content);
                    status.innerHTML = \'<span style="color: #2e7d32;">&#10004; ' . $this->__('Valid JSON') . '</span>\';
                } catch (e) {
                    var errorMsg = e.message || "' . $this->__('Invalid JSON') . '";
                    status.innerHTML = \'<span style="color: #c62828;">&#10008; \' + escapeHtml(errorMsg) + \'</span>\';
                }
            }
        };

        document.addEventListener("DOMContentLoaded", function() {
            JsonBuilder.init();
        });
        </script>

        <style>
        .json-node { padding: 5px 10px; cursor: pointer; border-radius: 3px; margin: 2px 0; }
        .json-node:hover { background: #f5f5f5; }
        .json-node.selected { background: #e3f2fd; }
        .json-key { font-weight: 600; color: #1976d2; }
        .json-type { color: #666; font-size: 11px; }
        .json-toggle { cursor: pointer; color: #666; }
        .json-children { margin-left: 10px; }
        </style>
        ';
    }
}

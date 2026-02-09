<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * CSV Builder block for feed mapping configuration
 *
 * Provides a tabular interface for defining CSV columns with drag-and-drop reordering,
 * attribute/value mapping, transformer configuration, and live preview.
 */
class Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Mapping_Csv extends Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Mapping_AbstractBuilder
{
    #[\Override]
    public function getBuilderHtml(): string
    {
        $feed = $this->_getFeed();
        $columns = $feed->getCsvColumns();
        $columnsData = $columns ? Mage::helper('core')->jsonDecode($columns) : [];
        $sourceTypes = Maho_FeedManager_Model_Mapper::getSourceTypeOptions();
        $attributeOptions = $this->_getProductAttributeOptionsForEditor();
        $ruleOptions = $this->_getDynamicRuleOptionsArray();
        $platformOptions = $this->_getPlatformPresetOptions();
        $taxonomyPlatforms = Maho_FeedManager_Model_Mapper::getTaxonomyPlatformOptions();

        return '
        <div id="csv-builder-container">
            <div class="fm-toolbar">
                <select id="csv-preset-select" onchange="CsvBuilder.loadPreset(this.value)" class="fm-input-lg">
                    <option value="">' . $this->__('Load Preset...') . '</option>
                    ' . $platformOptions . '
                </select>
                <span id="csv-platform-badge" class="fm-platform-badge"' . ($feed->getPlatform() && $feed->getPlatform() !== 'custom' ? '' : ' style="display:none"') . '>' . ucfirst($feed->getPlatform() ?: '') . '</span>
                <button type="button" class="scalable" onclick="CsvBuilder.showImportModal()">
                    <span>' . $this->__('Import CSV') . '</span>
                </button>
                <div class="fm-toolbar-spacer"></div>
                <button type="button" class="scalable" onclick="CsvBuilder.togglePreview()">
                    <span id="csv-preview-toggle-label">' . $this->__('Show Preview') . '</span>
                </button>
            </div>

            <div id="csv-grid-container">
                <table class="data csv-grid" id="csv-grid" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr class="headings">
                            <th class="fm-csv-col-drag"></th>
                            <th>' . $this->__('Column Name') . '</th>
                            <th>' . $this->__('Source Type') . '</th>
                            <th>' . $this->__('Source Value') . '</th>
                            <th title="' . $this->__('Use parent product value') . '" class="fm-csv-col-parent">' . $this->__('Parent') . '</th>
                            <th>' . $this->__('Transformers') . '</th>
                            <th class="fm-csv-col-actions"></th>
                        </tr>
                    </thead>
                    <tbody id="csv-grid-body">
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="7" class="fm-csv-empty">
                                <button type="button" class="scalable add" onclick="CsvBuilder.addColumn()">
                                    <span>' . $this->__('+ Add Column') . '</span>
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div id="csv-preview-panel" class="fm-preview-panel">
                <div class="fm-preview-header">
                    <span class="fm-preview-title">' . $this->__('Preview') . '</span>
                    <span class="fm-preview-meta">(<span id="csv-preview-count">0</span> ' . $this->__('sample products') . ')</span>
                    <button type="button" class="scalable" onclick="CsvBuilder.refreshPreview()"><span>' . $this->__('Refresh') . '</span></button>
                    <button type="button" class="scalable" onclick="CsvBuilder.copyPreview()"><span>' . $this->__('Copy') . '</span></button>
                </div>
                <pre id="csv-preview-content" class="fm-preview-content"></pre>
            </div>
        </div>

        <div id="csv-import-modal" class="fm-modal-overlay">
            <div class="fm-modal">
                <h3 class="fm-modal-title">' . $this->__('Import CSV Structure') . '</h3>
                <p>' . $this->__('Paste a header row or sample CSV:') . '</p>
                <textarea id="csv-import-input" class="fm-modal-textarea fm-modal-textarea-sm" placeholder="id,title,price,description,link"></textarea>
                <div class="fm-modal-footer">
                    <button type="button" class="scalable" onclick="CsvBuilder.hideImportModal()">
                        <span>' . $this->__('Cancel') . '</span>
                    </button>
                    <button type="button" class="scalable save" onclick="CsvBuilder.importColumns()">
                        <span>' . $this->__('Import Columns') . '</span>
                    </button>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        var CsvBuilder = {
            columns: ' . Mage::helper('core')->jsonEncode($columnsData) . ',
            sourceTypes: ' . Mage::helper('core')->jsonEncode($sourceTypes) . ',
            attributeOptionsHtml: ' . Mage::helper('core')->jsonEncode($attributeOptions) . ',
            ruleOptions: ' . Mage::helper('core')->jsonEncode($ruleOptions) . ',
            taxonomyPlatforms: ' . Mage::helper('core')->jsonEncode($taxonomyPlatforms) . ',
            previewUrl: "' . $this->getUrl('*/*/csvPreview') . '",
            presetUrl: "' . $this->getUrl('*/*/platformPreset') . '",
            feedId: ' . (int) $feed->getId() . ',

            init: function() {
                this.render();
            },

            render: function() {
                var tbody = document.getElementById("csv-grid-body");
                if (!tbody) return;
                tbody.innerHTML = "";

                for (var i = 0; i < this.columns.length; i++) {
                    tbody.appendChild(this.createRow(this.columns[i], i));
                }

                this.updateHiddenField();
            },

            createRow: function(col, index) {
                var tr = document.createElement("tr");
                tr.className = "csv-row";
                tr.dataset.index = index;
                tr.draggable = true;

                // Drag handle
                var tdDrag = document.createElement("td");
                tdDrag.innerHTML = "<span class=\"fm-drag-handle\">⋮⋮</span>";
                tdDrag.className = "a-center";
                tr.appendChild(tdDrag);

                // Column name
                var tdName = document.createElement("td");
                tdName.innerHTML = "<input type=\"text\" class=\"input-text fm-input-full\" value=\"" + escapeHtml(col.name || "", true) + "\" onchange=\"CsvBuilder.updateColumn(" + index + ", \'name\', this.value)\">";
                tr.appendChild(tdName);

                // Source type
                var tdType = document.createElement("td");
                var typeSelect = "<select class=\"fm-input-full\" onchange=\"CsvBuilder.updateColumn(" + index + ", \'source_type\', this.value); CsvBuilder.render();\">";
                for (var key in this.sourceTypes) {
                    var selected = col.source_type === key ? " selected" : "";
                    typeSelect += "<option value=\"" + key + "\"" + selected + ">" + this.sourceTypes[key] + "</option>";
                }
                typeSelect += "</select>";
                tdType.innerHTML = typeSelect;
                tr.appendChild(tdType);

                // Source value
                var tdValue = document.createElement("td");
                if (col.source_type === "attribute" || col.source_type === "custom_field" || !col.source_type) {
                    var selectHtml = this.attributeOptionsHtml.replace(new RegExp("value=\"" + escapeHtml(col.source_value || "", true) + "\""), "value=\"" + escapeHtml(col.source_value || "", true) + "\" selected");
                    tdValue.innerHTML = "<select class=\"fm-input-full\" onchange=\"CsvBuilder.updateColumn(" + index + ", \'source_value\', this.value)\">" + selectHtml + "</select>";
                } else if (col.source_type === "rule") {
                    var ruleSelectHtml = "<select class=\"fm-input-full\" onchange=\"CsvBuilder.updateColumn(" + index + ", \'source_value\', this.value)\">";
                    ruleSelectHtml += "<option value=\"\">' . addslashes($this->__('-- Select Rule --')) . '</option>";
                    for (var ruleCode in this.ruleOptions) {
                        var selected = (col.source_value === ruleCode) ? " selected" : "";
                        ruleSelectHtml += "<option value=\"" + escapeHtml(ruleCode, true) + "\"" + selected + ">" + escapeHtml(this.ruleOptions[ruleCode]) + "</option>";
                    }
                    ruleSelectHtml += "</select>";
                    tdValue.innerHTML = ruleSelectHtml;
                } else if (col.source_type === "taxonomy") {
                    var taxSelectHtml = "<select class=\"fm-input-full\" onchange=\"CsvBuilder.updateColumn(" + index + ", \'source_value\', this.value)\">";
                    for (var platform in this.taxonomyPlatforms) {
                        var selected = (col.source_value === platform) ? " selected" : "";
                        taxSelectHtml += "<option value=\"" + escapeHtml(platform, true) + "\"" + selected + ">" + escapeHtml(this.taxonomyPlatforms[platform]) + "</option>";
                    }
                    taxSelectHtml += "</select>";
                    tdValue.innerHTML = taxSelectHtml;
                } else {
                    var placeholder = col.source_type === "combined" ? "{{manufacturer}} - {{name}}" : "";
                    tdValue.innerHTML = "<input type=\"text\" class=\"input-text fm-input-full\" value=\"" + escapeHtml(col.source_value || "", true) + "\" onchange=\"CsvBuilder.updateColumn(" + index + ", \'source_value\', this.value)\" placeholder=\"" + placeholder + "\">";
                }
                tr.appendChild(tdValue);

                // Use Parent select
                var tdParent = document.createElement("td");
                tdParent.className = "a-center";
                var parentVal = col.use_parent || "";
                var selectHtml = "<select class=\"fm-input-sm\" onchange=\"CsvBuilder.updateColumn(" + index + ", \'use_parent\', this.value)\">";
                selectHtml += "<option value=\"\"" + (parentVal === "" ? " selected" : "") + ">—</option>";
                selectHtml += "<option value=\"if_empty\"" + (parentVal === "if_empty" ? " selected" : "") + ">' . addslashes($this->__('If empty')) . '</option>";
                selectHtml += "<option value=\"always\"" + (parentVal === "always" ? " selected" : "") + ">' . addslashes($this->__('Always')) . '</option>";
                selectHtml += "</select>";
                tdParent.innerHTML = selectHtml;
                tr.appendChild(tdParent);

                // Transformers
                var tdTransform = document.createElement("td");
                var transformCount = col.transformers ? col.transformers.split("|").filter(function(t) { return t.trim(); }).length : 0;
                var btnLabel = transformCount > 0 ? transformCount + " ✎" : "+ Add";
                tdTransform.innerHTML = "<button type=\"button\" class=\"scalable\" onclick=\"CsvBuilder.openTransformers(" + index + ")\"><span>" + btnLabel + "</span></button>";
                tr.appendChild(tdTransform);

                // Actions (duplicate + delete)
                var tdActions = document.createElement("td");
                tdActions.innerHTML = "<button type=\"button\" class=\"scalable fm-btn-inline\" onclick=\"CsvBuilder.duplicateColumn(" + index + ")\" title=\"Duplicate\"><span>⧉</span></button>" +
                    "<button type=\"button\" class=\"scalable delete fm-btn-inline\" onclick=\"CsvBuilder.removeColumn(" + index + ")\" title=\"Delete\"><span>×</span></button>";
                tr.appendChild(tdActions);

                // Drag events
                var self = this;
                tr.addEventListener("dragstart", function(e) {
                    e.dataTransfer.setData("text/plain", index);
                    tr.classList.add("dragging");
                });
                tr.addEventListener("dragend", function() {
                    tr.classList.remove("dragging");
                });
                tr.addEventListener("dragover", function(e) {
                    e.preventDefault();
                });
                tr.addEventListener("drop", function(e) {
                    e.preventDefault();
                    var fromIndex = parseInt(e.dataTransfer.getData("text/plain"));
                    var toIndex = parseInt(tr.dataset.index);
                    self.moveColumn(fromIndex, toIndex);
                });

                return tr;
            },

            addColumn: function() {
                this.columns.push({name: "", source_type: "attribute", source_value: "", use_parent: "", transformers: ""});
                this.render();
            },

            duplicateColumn: function(index) {
                var original = this.columns[index];
                var copy = JSON.parse(JSON.stringify(original));
                copy.name = copy.name + "_copy";
                this.columns.splice(index + 1, 0, copy);
                this.render();
            },

            removeColumn: function(index) {
                this.columns.splice(index, 1);
                this.render();
            },

            updateColumn: function(index, field, value) {
                this.columns[index][field] = value;
                this.updateHiddenField();
            },

            moveColumn: function(fromIndex, toIndex) {
                if (fromIndex === toIndex) return;
                var col = this.columns.splice(fromIndex, 1)[0];
                this.columns.splice(toIndex, 0, col);
                this.render();
            },

            openTransformers: function(index) {
                CsvBuilder.currentColumnIndex = index;
                var current = this.columns[index].transformers || "";
                document.getElementById("editor_transformers").value = current;
                TransformerModal.open();
            },

            updateHiddenField: function() {
                var field = document.getElementById("mapping_csv_columns");
                if (field) {
                    field.value = JSON.stringify(this.columns);
                }
            },

            loadPreset: function(platform) {
                if (!platform) return;

                // Confirm before overwriting existing mappings
                if (this.columns && this.columns.length > 0) {
                    if (!confirm("Loading a preset will replace your current column mappings. Continue?")) {
                        document.getElementById("csv-preset-select").value = "";
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
                    } else if (data.columns) {
                        self.columns = data.columns;
                        self.render();
                        // Update platform field in General tab
                        self.updatePlatform(data.platform);
                    }
                })
                .catch(function(err) {
                    alert("Error loading preset: " + err.message);
                });

                document.getElementById("csv-preset-select").value = "";
            },

            updatePlatform: function(platform) {
                var platformField = document.getElementById("platform") || document.querySelector("input[name=\'platform\']");
                if (platformField) {
                    platformField.value = platform;
                }
                var badge = document.getElementById("csv-platform-badge");
                if (badge) {
                    badge.textContent = platform ? platform.charAt(0).toUpperCase() + platform.slice(1) : "";
                    badge.style.display = platform ? "inline-block" : "none";
                }
            },

            showImportModal: function() {
                document.getElementById("csv-import-modal").style.display = "block";
            },

            hideImportModal: function() {
                document.getElementById("csv-import-modal").style.display = "none";
                document.getElementById("csv-import-input").value = "";
            },

            importColumns: function() {
                var input = document.getElementById("csv-import-input").value.trim();
                if (!input) return;

                // Parse first line as headers
                var firstLine = input.split("\n")[0];
                var headers = firstLine.split(/[,\t;|]/).map(function(h) {
                    return h.trim().replace(/^["\']+|["\']+$/g, "");
                });

                this.columns = headers.map(function(h) {
                    return {name: h, source_type: "attribute", source_value: "", use_parent: "", transformers: ""};
                });

                this.render();
                this.hideImportModal();
            },

            togglePreview: function() {
                var panel = document.getElementById("csv-preview-panel");
                var label = document.getElementById("csv-preview-toggle-label");
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
                var content = document.getElementById("csv-preview-content");
                content.textContent = "' . $this->__('Loading...') . '";

                mahoFetch(this.previewUrl, {
                    method: "POST",
                    body: new URLSearchParams({
                        id: this.feedId,
                        columns: JSON.stringify(this.columns),
                        form_key: FORM_KEY
                    }),
                    loaderArea: false
                })
                .then(function(data) {
                    if (data.error) {
                        content.textContent = "Error: " + data.message;
                    } else {
                        content.textContent = data.preview;
                        document.getElementById("csv-preview-count").textContent = data.count;
                    }
                })
                .catch(function(err) {
                    content.textContent = "Error loading preview";
                });
            },

            copyPreview: function() {
                var content = document.getElementById("csv-preview-content").textContent;
                navigator.clipboard.writeText(content);
            }
        };

        document.addEventListener("DOMContentLoaded", function() {
            CsvBuilder.init();
        });
        </script>
        ';
    }
}

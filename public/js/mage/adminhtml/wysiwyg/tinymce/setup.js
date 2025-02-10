/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2018 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class tinyMceWysiwygSetup {

    mediaBrowserCallback = null;
    mediaBrowserMetal = null;
    mediaBrowserValue = null;

    openmagePluginsOptions = new Map();

    constructor() {
        this.initialize(...arguments);
    }

    initialize(htmlId, config) {
        this.id = htmlId;
        this.selector = `textarea#${htmlId}`;
        this.config = config;

        if (typeof tinyMceEditors === 'undefined') {
            window.tinyMceEditors = $H({});
        }
        tinyMceEditors.set(this.id, this);

        this.bindEventListeners();
        if (!config.hidden) {
            this.setup('exact');
        }
    }

    bindEventListeners() {
        this.getToggleButton().addEventListener('click', this.toggle.bind(this));

        this.onFormValidation = this.onFormValidation.bind(this);
        varienGlobalEvents.attachEventHandler('formSubmit', this.onFormValidation);

        varienGlobalEvents.attachEventHandler('tinymceChange', this.onChangeContent.bind(this));
        varienGlobalEvents.attachEventHandler('tinymceBeforeSetContent', this.beforeSetContent.bind(this));
        varienGlobalEvents.attachEventHandler('tinymceSaveContent', this.saveContent.bind(this));

        varienGlobalEvents.clearEventHandlers('open_browser_callback');
        varienGlobalEvents.attachEventHandler('open_browser_callback', this.openFileBrowser.bind(this));
    }

    unbindEventListeners() {
        varienGlobalEvents.removeEventHandler('formSubmit', this.onFormValidation);
    }

    destroy() {
        this.unbindEventListeners();
        if (tinymce.get(this.id)) {
            tinymce.get(this.id).remove();
        }
    }

    setup(mode) {
        if (this.config.widget_plugin_src) {
            tinymce.PluginManager.load('openmagewidget', this.config.widget_plugin_src);
            this.openmagePluginsOptions.set('openmagewidget', {
                'widget_window_url': this.config.widget_window_url,
            });
        }

        if (this.config.plugins) {
            for (const plugin of this.config.plugins) {
                tinymce.PluginManager.load(plugin.name, plugin.src);
                this.openmagePluginsOptions.set(plugin.name, plugin.options);
            }
        }

        tinymce.init(this.getSettings(mode));
    }

    getSettings(mode) {
        let plugins = 'autoresize accordion searchreplace visualblocks visualchars anchor code lists advlist fullscreen pagebreak table wordcount directionality image charmap link media nonbreaking help';
        let toolbar = 'undo redo | bold italic underline strikethrough | insertfile image media template link anchor codesample | alignleft aligncenter alignright alignjustify | outdent indent |  numlist bullist | forecolor backcolor removeformat | fontfamily fontsize blocks | pagebreak | charmap | fullscreen preview save print | ltr rtl'

        // load and add to toolbar openmagePlugins
        if (this.openmagePluginsOptions) {
            let openmageToolbarButtons = '';
            for (const [ key, plugin ] of this.openmagePluginsOptions) {
                plugins = key + ' ' + plugins;
                openmageToolbarButtons = key + ' ' + openmageToolbarButtons;
            }
            toolbar = openmageToolbarButtons + ' | ' + toolbar;
        }

        const settings = {
            selector: this.selector,
            config: this.config,
            valid_children: '+body[style]',
            custom_elements: 'style,~style',
            protect: [
                /[\S]?<script[\s\S]*?>[\s\S]*?<\/script[\s\S]*?>[\S]?/ig
            ],
            menu: {
                insert: {
                    title: 'Insert',
                    items: 'image link media addcomment pageembed template codesample inserttable | openmagevariable openmagewidget | charmap emoticons hr | pagebreak nonbreaking anchor tableofcontents | insertdatetime'
                }
            },
            menubar: 'file edit view insert format tools table help',
            plugins: plugins,
            toolbar: toolbar,
            language: this.config.lang,
            paste_as_text: true,
            file_picker_types: 'file image media',
            automatic_uploads: false,
            branding: false,
            promotion: false,
            convert_urls: false,
            convert_unsafe_embeds: true,
            relative_urls: true,
            skin: this.config.skin,
            min_height: 460,
            init_instance_callback: (editor) => {
                // hack for tinymce inside dialog HTML element
                const dialogContainer = editor.editorContainer.closest('dialog');
                if (dialogContainer) {
                    const auxElements = document.querySelectorAll('body > .tox-tinymce-aux');
                    if (auxElements.length) {
                        dialogContainer.append(auxElements[auxElements.length - 1]);
                    }
                }
            },
            urlconverter_callback: (url, node, on_save, name) => {
                // some callback here to convert urls
                //url = this.decodeContent(url);
                return url;
            },
            setup: (editor) => {
                editor.on('BeforeSetContent', function (evt) {
                    varienGlobalEvents.fireEvent('tinymceBeforeSetContent', evt);
                });

                editor.on('SaveContent', function (evt) {
                    varienGlobalEvents.fireEvent('tinymceSaveContent', evt);
                });

                editor.on('Paste', function (ed, e, o) {
                    varienGlobalEvents.fireEvent('tinymcePaste', o);
                });

                editor.on('PostProcess', function (evt) {
                    varienGlobalEvents.fireEvent('tinymceSaveContent', evt);
                });

                editor.on('setContent', function (evt) {
                    varienGlobalEvents.fireEvent('tinymceSetContent', evt);
                });

                const onChange = function (evt) {
                    varienGlobalEvents.fireEvent('tinymceChange', evt);
                };

                editor.on('Change', onChange);
                editor.on('keyup', onChange);

                editor.on('ExecCommand', function (cmd, ui, val) {
                    varienGlobalEvents.fireEvent('tinymceExecCommand', cmd);
                });

                editor.on('init', function (args) {
                    varienGlobalEvents.fireEvent('wysiwygEditorInitialized', args.target);
                });
            }
        }

        // Set the document base URL
        if (this.config.document_base_url) {
            settings.document_base_url = this.config.document_base_url;
        }

        if (this.config.files_browser_window_url) {
            settings.file_picker_callback = (callback, value, meta) => {
                varienGlobalEvents.fireEvent("open_browser_callback", { callback: callback, value: value, meta: meta });
            };
        }
        return settings;
    }

    openFileBrowser(o) {
        var typeTitle;
        var storeId = this.config.store_id !== null ? this.config.store_id : 0;
        var wUrl = this.config.files_browser_window_url +
            'target_element_id/' + this.id + '/' +
            'store/' + storeId + '/';

        this.mediaBrowserCallback = o.callback;
        this.mediaBrowserMeta = o.meta;
        this.mediaBrowserValue = o.value;

        if (typeof (o.meta.filetype) != 'undefined' && o.meta.filetype == "image") {
            typeTitle = 'image' == o.meta.filetype ? this.translate('Insert Image...') : this.translate('Insert Media...');
            wUrl = wUrl + "type/" + o.meta.filetype + "/";
        } else {
            typeTitle = this.translate('Insert File...');
        }

        MediabrowserUtility.openDialog(wUrl, false, false, typeTitle, {
            onBeforeShow: function (win) {
                win.element.setStyle({ zIndex: 300200 });
            }
        });
    }

    translate(string) {
        return typeof Translator !== 'undefined' ? Translator.translate(string) : string;
    }

    getToggleButton() {
        return document.getElementById(`toggle${this.id}`);
    }

    getPluginButtons() {
        return document.querySelectorAll(`#buttons${this.id} > button.plugin`);
    }

    turnOn() {
        this.closePopups();
        this.setup();
        this.getPluginButtons().forEach((el) => el.classList.add('no-display'));
    }

    turnOff() {
        this.closePopups();
        if (tinymce.get(this.id)) {
            tinymce.get(this.id).destroy();
        }
        this.getPluginButtons().forEach((el) => el.classList.remove('no-display'));
    }

    closePopups() {
    }

    toggle() {
        if (tinymce.get(this.id) === null) {
            this.turnOn();
            return true;
        } else {
            this.turnOff();
            return false;
        }
    }

    onFormValidation() {
        if (tinymce.get(this.id)) {
            document.getElementById(this.id).value = tinymce.get(this.id).getContent();
        }
    }

    onChangeContent() {
        if (this.config.tab_id) {
            const tab = document.querySelector(`a[id$=${this.config.tab_id}]`);
            if (tab && tab.classList.contains('tab-item-link')) {
                tab.classList.add('changed');
            }
        }
    }

    beforeSetContent(o) {
        o.content = this.encodeContent(o.content);
    }

    saveContent(o) {
        o.content = this.decodeContent(o.content);
    }

    updateTextArea() {
        const content = this.decodeContent(tinymce.get(this.id).getContent());
        this.getTextArea().value = content;
        this.triggerChange(this.getTextArea());
    }

    getTextArea() {
        return document.getElementById(this.id);
    }

    triggerChange(element) {
        element.dispatchEvent(new Event('change', { bubbles: false, cancelable: true }));
        return element;
    }

    encodeContent(content) {
        if (this.config.add_widgets) {
            return this.encodeDirectives(this.encodeWidgets(content));
        } else if (this.config.add_directives) {
            return this.encodeDirectives(content);
        }
    }

    decodeContent(content) {
        if (this.config.add_widgets) {
            return this.decodeDirectives(this.decodeWidgets(content));
        } else if (this.config.add_directives) {
            return this.decodeDirectives(content);
        }
    }

    makeDirectiveUrl(directive) {
        // retrieve directives URL with substituted directive value
        return this.config.directives_url.replace('directive', 'directive/___directive/' + directive);
    }

    encodeDirectives(content) {
        // collect all HTML tags with attributes that contain directives
        return content.replaceAll(/<([a-z0-9\-\_]+.+?)([a-z0-9\-\_]+=".*?\{\{.+?\}\}.*?".+?)>/gi, (...match) => {
            // process tag attributes string
            const attributesString = match[2].replaceAll(/([a-z0-9\-\_]+)="(.*?)(\{\{.+?\}\})(.*?)"/gi, (...m) => {
                return m[1] + '="' + m[2] + this.makeDirectiveUrl(Base64.mageEncode(m[3])) + m[4] + '"';
            });
            return '<' + match[1] + attributesString + '>';
        });
    }

    encodeWidgets(content) {
        return content.replaceAll(/\{\{widget(.*?)\}\}/gi, (...match) => {
            const attributes = this.parseAttributesString(match[1]);
            if (attributes.type) {
                let placeholderFilename = attributes.type.replace(/\//g, "__") + ".gif";
                if (!this.widgetPlaceholderExist(placeholderFilename)) {
                    placeholderFilename = 'default.gif';
                }
                const attributesObj = {
                    id: Base64.idEncode(match[0]),
                    src: this.config.widget_images_url + placeholderFilename,
                    title: match[0].replace(/\{\{/g, '{').replace(/\}\}/g, '}').replace(/\"/g, '&quot;'),
                };
                const attributesString = Object.entries(attributesObj)
                      .map(([key, value]) => `${key}="${value}"`)
                      .join(' ');

                return `<img ${attributesString}>`;
            }
        });
    }

    decodeDirectives(content) {
        // escape special chars in directives url to use it in regular expression
        const url = this.makeDirectiveUrl('%directive%').replace(/([$^.?*!+:=()\[\]{}|\\])/g, '\\$1');
        const reg = new RegExp(url.replace('%directive%', '([a-zA-Z0-9,_-]+)'), 'g');
        return content.replaceAll(reg, (...match) => Base64.mageDecode(match[1]));
    }

    decodeWidgets(content) {
        return content.replaceAll(/<img([^>]+id=\"[^>]+)>/gi, (...match) => {
            const attributes = this.parseAttributesString(match[1]);
            if (attributes.id) {
                const widgetCode = Base64.idDecode(attributes.id);
                if (widgetCode.indexOf('{{widget') !== -1) {
                    return widgetCode;
                }
                return match[0];
            }
            return match[0];
        });
    }

    parseAttributesString(attributes) {
        const result = {};
        attributes.replaceAll(/(\w+)(?:\s*=\s*(?:(?:"((?:\\.|[^"\\])*)")|(?:'((?:\\.|[^'\\])*)')|([^>\s]+)))?/g, (...match) => {
            result[match[1]] = match[2];
        });
        return result;
    }

    widgetPlaceholderExist(filename) {
        return this.config.widget_placeholders.indexOf(filename) !== -1;
    }

    getMediaBrowserCallback() {
        return this.mediaBrowserCallback;
    }
}

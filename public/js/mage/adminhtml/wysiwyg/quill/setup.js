/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class quillWysiwygSetup {

    mediaBrowserCallback = null;
    mediaBrowserMetal = null;
    mediaBrowserValue = null;

    constructor() {
        this.initialize(...arguments);
    }

    initialize(htmlId, config) {
        this.id = htmlId;
        this.selector = `#${htmlId}`;
        this.config = config;
        this.editor = null;

        if (typeof quillEditors === 'undefined') {
            window.quillEditors = new Map();
        }
        quillEditors.set(this.id, this);

        this.bindEventListeners();
        if (!config.hidden) {
            this.setup();
        }
    }

    bindEventListeners() {
        this.getToggleButton()?.addEventListener('click', this.toggle.bind(this));

        this.onFormValidation = this.onFormValidation.bind(this);
        varienGlobalEvents.attachEventHandler('formSubmit', this.onFormValidation);

        varienGlobalEvents.clearEventHandlers('open_browser_callback');
        varienGlobalEvents.attachEventHandler('open_browser_callback', this.openFileBrowser.bind(this));
    }

    registerCustomButtons() {
        const icons = Quill.import('ui/icons');
        icons['widget'] = '<svg class="ql-stroke" xmlns="http://www.w3.org/2000/svg" width="18" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-name="packages" data-variant="outline"><path d="M7 16.5l-5 -3l5 -3l5 3v5.5l-5 3z"/><path d="M2 13.5v5.5l5 3"/><path d="M7 16.545l5 -3.03"/><path d="M17 16.5l-5 -3l5 -3l5 3v5.5l-5 3z"/><path d="M12 19l5 3"/><path d="M17 16.5l5 -3"/><path d="M12 13.5v-5.5l-5 -3l5 -3l5 3v5.5"/><path d="M7 5.03v5.455"/><path d="M12 8l5 -3"/></svg>';
        icons['variable'] = '<svg class="ql-stroke" xmlns="http://www.w3.org/2000/svg" width="18" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-name="variable" data-variant="outline"><path d="M5 4c-2.5 5 -2.5 10 0 16m14 -16c2.5 5 2.5 10 0 16m-10 -11h1c1 0 1 1 2.016 3.527c.984 2.473 .984 3.473 1.984 3.473h1"/><path d="M8 16c1.5 0 3 -2 4 -3.5s2.5 -3.5 4 -3.5"/></svg>';
    }

    unbindEventListeners() {
        varienGlobalEvents.removeEventHandler('formSubmit', this.onFormValidation);
    }

    destroy() {
        this.unbindEventListeners();
        if (this.editor) {
            // Save content before destroying
            this.updateTextArea();
            
            // Destroy the Quill instance
            this.editor = null;
        }
        
        // Remove the wrapper which contains everything
        const wrapper = document.getElementById(`${this.id}_wrapper`);
        if (wrapper) {
            wrapper.remove();
        }
    }

    setup() {
        // registering a custom image handler
        var Image = Quill.import('formats/image');
        class MahoQuillImage extends Image {
            static get ATTRIBUTES() {
                return ['id', 'alt', 'height', 'width', 'class', 'data-original', 'data-width', 'data-height', 'style-data'];
            }

            static formats(domNode) {
                return this.ATTRIBUTES.reduce(function(formats, attribute) {
                    if (domNode.hasAttribute(attribute)) {
                        formats[attribute] = domNode.getAttribute(attribute);
                    }
                    return formats;
                }, {});
            }

            format(name, value) {
                if (this.constructor.ATTRIBUTES.indexOf(name) > -1) {
                    if (value) {
                        this.domNode.setAttribute(name, value);
                    } else {
                        this.domNode.removeAttribute(name);
                    }
                } else {
                    super.format(name, value);
                }
            }
        }
        Quill.register(MahoQuillImage);

        // Register custom buttons before initializing Quill
        this.registerCustomButtons();
        
        // Create wrapper container for Quill editor
        const textarea = this.getTextArea();
        const wrapper = document.createElement('div');
        wrapper.id = `${this.id}_wrapper`;
        wrapper.className = 'quill-wrapper';
        
        // Create container for Quill editor content
        const container = document.createElement('div');
        container.id = `${this.id}_editor`;
        container.style.minHeight = '400px';
        container.style.backgroundColor = '#fff';
        
        // Append container to wrapper
        wrapper.appendChild(container);
        
        // Insert wrapper after textarea
        textarea.style.display = 'none';
        textarea.parentNode.insertBefore(wrapper, textarea.nextSibling);

        // Initialize Quill
        const toolbarOptions = this.getToolbarOptions();
        
        this.editor = new Quill(container, {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: toolbarOptions,
                    handlers: {
                        'image': this.imageHandler.bind(this),
                        'widget': this.widgetHandler.bind(this),
                        'variable': this.variableHandler.bind(this),
                    }
                }
            }
        });

        // Set initial content
        let cleanContent = this.getTextArea().value;

        // Decode any existing directive URLs that shouldn't be there
        const urlPattern = this.config.directives_url
            .replace(/([$^.?*!+:=()\[\]{}|\\])/g, '\\$1')
            .replace('directive', 'directive/___directive/([a-zA-Z0-9,_-]+)(?:/key/[a-zA-Z0-9]+/?)?');
        const reg = new RegExp(urlPattern, 'g');

        cleanContent = cleanContent.replace(reg, (match, directive) => {
            return Base64.mageDecode(directive);
        });

        const initialContent = this.encodeContent(cleanContent);
        if (initialContent) {
            this.editor.root.innerHTML = initialContent;
        }

        // Listen for changes
        this.editor.on('text-change', (delta, oldDelta, source) => {
            if (source === 'user') {
                this.updateTextArea();
                this.onChangeContent();
            }
        });

        // Add double-click handler for widget placeholders
        this.editor.root.addEventListener('dblclick', this.handleDoubleClick.bind(this));

        // Add titles to custom buttons
        const toolbar = this.editor.getModule('toolbar');
        if (toolbar) {
            const widgetButton = toolbar.container.querySelector('.ql-widget');
            if (widgetButton) {
                widgetButton.setAttribute('title', 'Insert Widget');
            }
            const variableButton = toolbar.container.querySelector('.ql-variable');
            if (variableButton) {
                variableButton.setAttribute('title', 'Insert Variable');
            }
        }

        // Fire initialization event
        varienGlobalEvents.fireEvent('wysiwygEditorInitialized', this.editor);
    }

    getToolbarOptions() {
        return [
            [{ 'header': [1, 2, 3, 4, 5, false] }],
            ['bold', 'italic', 'underline', 'strike', 'blockquote'],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }, { 'align': [] }],
            ['link', 'image', 'widget', 'variable']
        ];
    }

    imageHandler() {
        if (this.config.files_browser_window_url) {
            varienGlobalEvents.fireEvent("open_browser_callback", { 
                callback: (url) => {
                    const range = this.editor.getSelection();
                    if (range) {
                        this.editor.insertEmbed(range.index, 'image', url);
                    }
                },
                value: '',
                meta: { filetype: 'image' }
            });
        }
    }

    widgetHandler() {
        if (this.config.widget_window_url) {
            // Clear any editing state when inserting a new widget
            this.editingWidgetId = null;
            delete window.widgetFormInitialValues;
            
            const url = this.config.widget_window_url + 'widget_target_id/' + this.id + '/';
            widgetTools.openDialog(url);
        }
    }

    handleDoubleClick(event) {
        const target = event.target;
        
        // Check if the clicked element is a widget placeholder
        if (target.tagName === 'IMG' && target.classList.contains('maho-widget-placeholder')) {
            event.preventDefault();
            event.stopPropagation();
            
            // Get the widget ID from the image
            const widgetId = target.getAttribute('id');
            if (widgetId) {
                this.openWidgetForEdit(widgetId);
            }
        }
    }

    openWidgetForEdit(widgetId) {
        if (!this.config.widget_window_url || !widgetId) {
            return;
        }

        // Decode the widget content from the ID
        const widgetCode = Base64.idDecode(widgetId);
        
        // Parse the widget code to extract parameters
        const widgetParams = this.parseWidgetCode(widgetCode);
        
        if (widgetParams && widgetParams.type) {
            // Store the widget element for later replacement
            this.editingWidgetId = widgetId;
            
            // Store widget parameters globally for the widget form to access
            window.widgetFormInitialValues = widgetParams;
            
            // Open the widget dialog
            const url = this.config.widget_window_url + 'widget_target_id/' + this.id + '/';
            widgetTools.openDialog(url);
        }
    }

    parseWidgetCode(widgetCode) {
        // Extract widget parameters from the widget directive
        const match = widgetCode.match(/\{\{widget\s+(.+?)\}\}/);
        if (!match) return null;
        
        const params = {};
        const paramString = match[1];
        
        // Parse key="value" pairs
        const regex = /(\w+)="([^"]*)"/g;
        let paramMatch;
        
        while ((paramMatch = regex.exec(paramString)) !== null) {
            params[paramMatch[1]] = paramMatch[2];
        }
        
        return params;
    }

    variableHandler() {
        if (this.config.variable_window_url) {
            // Store cursor position before opening dialog
            this.lastRange = this.editor.getSelection();
            const url = this.config.variable_window_url + 'variable_target_id/' + this.id + '/';
            OpenmagevariablePlugin.loadChooser(url, this.id);
        }
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

    getContainer() {
        return document.getElementById(`${this.id}_editor`);
    }

    getTextArea() {
        return document.getElementById(this.id);
    }

    turnOn() {
        this.setup();
        this.getPluginButtons().forEach((el) => el.classList.add('no-display'));
    }

    turnOff() {
        this.destroy();
        this.getTextArea().style.display = '';
        this.getPluginButtons().forEach((el) => el.classList.remove('no-display'));
    }

    toggle() {
        if (this.editor === null) {
            this.turnOn();
            return true;
        } else {
            this.turnOff();
            return false;
        }
    }

    onFormValidation() {
        if (this.editor) {
            this.updateTextArea();
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

    updateTextArea() {
        if (this.editor) {
            const textarea = this.getTextArea();
            if (textarea) {
                const content = this.decodeContent(this.editor.root.innerHTML);
                textarea.value = content;
                this.triggerChange(textarea);
            }
        }
    }

    triggerChange(element) {
        element.dispatchEvent(new Event('change', { bubbles: false, cancelable: true }));
        return element;
    }

    encodeContent(content) {
        if (!content) return '';
        // Only encode widgets, do NOT encode any directives for QuillJS
        // Variables should remain as plain text in the editor
        return this.encodeWidgets(content);
    }

    decodeContent(content) {
        if (!content) return '';
        return this.decodeDirectives(this.decodeWidgets(content));
    }

    makeDirectiveUrl(directive) {
        return this.config.directives_url.replace('directive', 'directive/___directive/' + directive);
    }

    encodeDirectives(content) {
        return content.replace(/<([a-z0-9\-\_]+.+?)([a-z0-9\-\_]+=".*?\{\{.+?\}\}.*?".+?)>/gi, (match, p1, p2) => {
            const attributesString = p2.replace(/([a-z0-9\-\_]+)="(.*?)(\{\{.+?\}\})(.*?)"/gi, (m, attr, before, directive, after) => {
                return attr + '="' + before + this.makeDirectiveUrl(Base64.mageEncode(directive)) + after + '"';
            });
            return '<' + p1 + attributesString + '>';
        });
    }

    encodeWidgets(content) {
        return content.replace(/\{\{widget(.*?)\}\}/gi, (match, attributes) => {
            const attrs = this.parseAttributesString(attributes);
            if (attrs.type) {
                let placeholderFilename = attrs.type.replace(/\//g, "__") + ".svg";
                if (!this.widgetPlaceholderExist(placeholderFilename)) {
                    placeholderFilename = 'default.svg';
                }
                const attributesObj = {
                    id: Base64.idEncode(match),
                    src: this.config.widget_images_url + placeholderFilename,
                    title: 'Double-click to edit: ' + match.replace(/\{\{/g, '{').replace(/\}\}/g, '}').replace(/\"/g, '&quot;'),
                    class: 'maho-widget-placeholder'
                };
                const attributesString = Object.entries(attributesObj)
                      .map(([key, value]) => `${key}="${value}"`)
                      .join(' ');

                return `<img ${attributesString}>`;
            }
            return match;
        });
    }

    decodeDirectives(content) {
        return content.replace(/<([a-z0-9\-\_]+[^>]*?)>/gi, (match) => {
            const urlPattern = this.config.directives_url
                .replace(/([$^.?*!+:=()\[\]{}|\\])/g, '\\$1')
                .replace('directive', 'directive/___directive/([a-zA-Z0-9,_-]+)(?:/key/[a-zA-Z0-9]+/?)?');
            const reg = new RegExp(urlPattern, 'g');
            return match.replace(reg, (m, directive) => Base64.mageDecode(directive));
        });
    }

    decodeWidgets(content) {
        return content.replace(/<img([^>]+id=\"[^>]+)>/gi, (match, attributes) => {
            const attrs = this.parseAttributesString(attributes);
            if (attrs.id) {
                const widgetCode = Base64.idDecode(attrs.id);
                if (widgetCode.indexOf('{{widget') !== -1) {
                    return widgetCode;
                }
            }
            return match;
        });
    }

    parseAttributesString(attributes) {
        // Create a temporary element with unique ID
        const tempElement = document.createElement('div');
        tempElement.innerHTML = `<div ${attributes}></div>`;

        // Add to DOM temporarily (some browsers need this for full parsing)
        document.body.appendChild(tempElement);

        const element = tempElement.firstChild;
        const result = {};

        // Extract all attributes
        for (const attr of element.attributes) {
            result[attr.name] = attr.value;
        }

        // Clean up - remove the temporary element
        document.body.removeChild(tempElement);
        return result;
    }

    widgetPlaceholderExist(filename) {
        return this.config.widget_placeholders && this.config.widget_placeholders.indexOf(filename) !== -1;
    }

    getMediaBrowserCallback() {
        return this.mediaBrowserCallback;
    }

    // Method to insert content at cursor position (for widgets/variables)
    insertContent(content) {
        if (this.editor) {
            // Check if we're replacing an existing widget
            if (this.editingWidgetId) {
                const widgetElement = this.editor.root.querySelector(`img[id="${this.editingWidgetId}"]`);
                if (widgetElement) {
                    // Replace the existing widget placeholder
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = content;
                    const newElement = tempDiv.firstChild;
                    
                    if (newElement) {
                        widgetElement.parentNode.replaceChild(newElement, widgetElement);
                    }
                    
                    // Clear the editing widget ID
                    this.editingWidgetId = null;
                    
                    // Update the textarea
                    this.updateTextArea();
                    return;
                }
            }
            
            // Normal insertion at cursor position
            const range = this.editor.getSelection() || this.lastRange;
            if (range) {
                // If it's HTML content, insert as HTML
                if (content.includes('<') && content.includes('>')) {
                    this.editor.clipboard.dangerouslyPasteHTML(range.index, content);
                    this.turnOff();
                    this.turnOn();
                } else {
                    this.editor.insertText(range.index, content);
                    // Set cursor after inserted content
                    this.editor.setSelection(range.index + content.length);
                }
            }
        }
    }
}

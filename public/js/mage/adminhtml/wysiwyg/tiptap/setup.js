/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

import * as TiptapModules from './extensions.js';
import { html_beautify } from 'https://esm.sh/js-beautify@1.15.4/js/lib/beautify-html.js';

class tiptapWysiwygSetup {

    constructor() {
        this.initialize(...arguments);
    }

    initialize(htmlId, config) {
        this.id = htmlId;
        this.config = config;
        this.editor = null;
        this.wrapper = null;
        this.textarea = document.getElementById(this.id);
        this.storeId = config.store_id ?? 0;
        this.invalidContent = null;

        window.tiptapEditors ??= new Map();
        window.tiptapEditors.set(this.id, this);

        this.setup();

        if (!config.hidden) {
            if (this.invalidContent === null) {
                this.turnOn();
            } else {
                toggleVis(this.textarea, true);
            }
        }
    }

    bindEventListeners() {
        this.getToggleButton()?.addEventListener('click', () => {
            if (this.invalidContent !== null) {
                if (!confirm(this.translate('Some content is not supported. Clean and continue?'))) {
                    return;
                }
                this.invalidContent = null;
            }
            this.toggle();
        });

        this.syncHandler = () => {
            if (this.isTiptapActive()) {
                this.syncWysiwygToPlain();
            }
        }
        this.syncHandlerDebounced = debounce(this.syncHandler, 200);

        varienGlobalEvents?.attachEventHandler('formValidate', this.syncHandler.bind(this));
        varienGlobalEvents?.attachEventHandler('formSubmit', this.syncHandler.bind(this));

        const formEl = this.textarea.closest('form');
        formEl?.addEventListener('formdata', this.syncHandler.bind(this));
        formEl?.addEventListener('submit', this.syncHandler.bind(this));
    }

    unbindEventListeners() {
        varienGlobalEvents?.removeEventHandler('formValidate', this.syncHandler.bind(this));
        varienGlobalEvents?.removeEventHandler('formSubmit', this.syncHandler.bind(this));

        const formEl = this.textarea.closest('form');
        formEl?.removeEventListener('formdata', this.syncHandler.bind(this));
        formEl?.removeEventListener('submit', this.syncHandler.bind(this));
    }

    convertFromPlain(content) {
        // Find all directives, then search backwards for an angle bracket followed by a letter
        // If found, we are in an attribute. Then:
        //
        // Escape directives contained in attributes since this is not valid HTML. For example:
        // <a href="mailto:{{config path="trans_email/ident_sales/email"}}"> into
        // <a href="mailto:{{config path=&quot;trans_email/ident_sales/email&quot;}}">
        //
        // Convert all other directives into MahoWidget nodes since tiptap cannot recognize text nodes. For example:
        // {{widget type="cms/some_type"}} into
        // <span data-type="widget" data-directive="{{widget type=&quot;cms/some_type&quot;}}"></span>

        content = content.replace(/{{(.*?)}}/gi, (match, directive, offset, string) => {
            const escapedDirective = escapeHtml('{{' + directive.trim() + '}}', true);

            let inAttribute = false;
            while (offset-- > 0) {
                const char = string[offset];
                if (char === '<' && string[offset + 1].match(/[a-z]/i)) {
                    inAttribute = true;
                    break;
                }
                if (['\n', '\r', '<', '>'].includes(char)) {
                    break;
                }
            }
            if (inAttribute) {
                return escapedDirective;
            }
            return `<span data-type="maho-widget" data-directive="${escapedDirective}"></span>`;
        });

        // Convert any <span> widget into <div> if they only have other <div> parents
        const blockTags = ['DIV'];
        const doc = new DOMParser().parseFromString(xssFilter(content), 'text/html');

        for (const widget of doc.body.querySelectorAll('span[data-type=maho-widget]')) {
            let ref = widget.parentElement;
            while (blockTags.includes(ref.tagName)) {
                ref = ref.parentElement;
            }
            if (ref.tagName !== 'BODY') {
                continue;
            }
            const newWidget = document.createElement('div');
            newWidget.dataset.type = widget.dataset.type;
            newWidget.dataset.directive = widget.dataset.directive;
            newWidget.innerHTML = widget.innerHTML;
            widget.replaceWith(newWidget);
        }

        return doc.body.innerHTML;
    }

    convertToPlain(content) {
        // TipTap generates minified HTML, so when switching to the plain editor beautify it
        content = html_beautify(content, { indent_size: 4 });

        // Extract directives from MahoWidget nodes
        content = content.replace(/<(div|span) data-type="maho-widget" data-directive="(.*?)"><\/\1>/gi, (match, tagName, directive) => {
            return directive;
        });

        // Unescape all directives both in attributes and as text nodes
        content = content.replace(/{{(.*?)}}/gi, (match, directive) => {
            return unescapeHtml('{{' + directive.trim() + '}}');
        });

        return content;
    }

    syncPlainToWysiwyg() {
        if (!this.textarea || !this.editor) {
            return;
        }
        this.editor.commands.setContent(this.convertFromPlain(this.textarea.value));
    }

    syncWysiwygToPlain() {
        if (!this.textarea || !this.editor) {
            return;
        }
        this.textarea.value = this.convertToPlain(this.editor.getHTML());
        this.textarea.dispatchEvent(new Event('change', { bubbles: false, cancelable: true }));
    }

    isTiptapActive() {
        return this.wrapper?.checkVisibility() ?? false;
    }

    toggle() {
        const enabled = !this.isTiptapActive();
        if (enabled) {
            this.syncPlainToWysiwyg();
        } else {
            this.syncWysiwygToPlain();
        }
        toggleVis(this.textarea, !enabled);
        toggleVis(this.wrapper, enabled);
        for (const button of this.getPluginButtons()) {
            toggleVis(button, !enabled);
        }
        return enabled;
    }

    turnOn() {
        if (!this.isTiptapActive()) {
            this.toggle();
        }
    }

    turnOff() {
        if (this.isTiptapActive()) {
            this.toggle();
        }
    }

    setup() {
        // Create wrapper for Tiptap editor
        this.wrapper = document.createElement('div');
        this.wrapper.id = `${this.id}_wrapper`;
        this.wrapper.className = 'tiptap-wrapper no-display';

        // Create main toolbar
        const toolbar = this.createMainToolbar();
        this.wrapper.appendChild(toolbar);

        // Create table bubble menu
        const tableBubbleMenu = this.createTableBubbleMenu();
        this.wrapper.appendChild(tableBubbleMenu);

        // Create columns bubble menu
        const columnsBubbleMenu = this.createColumnsBubbleMenu();
        this.wrapper.appendChild(columnsBubbleMenu);

        // Create bento grid bubble menu
        const bentoBubbleMenu = this.createBentoBubbleMenu();
        this.wrapper.appendChild(bentoBubbleMenu);

        // Create container for Tiptap editor content
        const container = document.createElement('div');
        container.id = `${this.id}_editor`;
        container.className = 'tiptap-content';
        this.wrapper.appendChild(container);

        // Insert wrapper after textarea
        this.textarea.after(this.wrapper);

        // Initialize Tiptap
        this.editor = new TiptapModules.Editor({
            wysiwygSetup: this,
            element: container,
            enableContentCheck: true,
            content: this.convertFromPlain(this.textarea.value),
            extensions: [
                TiptapModules.GlobalAttributes,
                TiptapModules.StarterKit.configure({
                    heading: {
                        levels: [1, 2, 3, 4, 5]
                    },
                    link: {
                        openOnClick: false,
                        HTMLAttributes: {
                            rel: null,
                            target: null
                        }
                    }
                }),
                TiptapModules.MahoImage.configure({
                    inline: true,
                    title: this.translate('Insert Image...'),
                    directivesUrl: this.config.directives_url,
                    browserUrl: setRouteParams(this.config.files_browser_window_url, {
                        target_element_id: this.id,
                        store: this.storeId,
                        filetype: 'image',
                    }),
                }),
                TiptapModules.MahoWidgetBlock.configure({
                    widgetUrl: setRouteParams(this.config.widget_window_url, {
                        widget_target_id: this.id,
                    }),
                    variableUrl: setRouteParams(this.config.variable_window_url, {
                        variable_target_id: this.id,
                    }),
                }),
                TiptapModules.MahoWidgetInline.configure({
                    widgetUrl: setRouteParams(this.config.widget_window_url, {
                        widget_target_id: this.id,
                    }),
                    variableUrl: setRouteParams(this.config.variable_window_url, {
                        variable_target_id: this.id,
                    }),
                }),
                ...(this.config.add_slideshows !== false ? [TiptapModules.MahoSlideshow.configure({
                    directivesUrl: this.config.directives_url,
                    browserUrl: setRouteParams(this.config.files_browser_window_url, {
                        store: this.storeId,
                        filetype: 'image',
                    }),
                })] : []),
                TiptapModules.Table.configure({
                    resizable: true
                }),
                TiptapModules.TableRow,
                TiptapModules.TableCell,
                TiptapModules.TableHeader,
                TiptapModules.TextAlign.configure({
                    types: ['heading', 'paragraph', 'tableCell', 'tableHeader'],
                }),
                TiptapModules.VerticalAlign,
                TiptapModules.BubbleMenu.configure({
                    element: tableBubbleMenu,
                    shouldShow: ({ editor, view, state, oldState }) => {
                        const isInTable = editor.isActive('table');
                        const isInCell = editor.isActive('tableCell') || editor.isActive('tableHeader');
                        const shouldShow = isInTable && isInCell;
                        tableBubbleMenu.style.display = shouldShow ? 'flex' : 'none';
                        return shouldShow;
                    },
                }),
                TiptapModules.MahoDiv,
                TiptapModules.MahoColumns.configure({
                    // Store bubble menu reference for NodeView to access
                    bubbleMenu: columnsBubbleMenu,
                }),
                TiptapModules.MahoColumn,
                TiptapModules.MahoBentoGrid.configure({
                    bubbleMenu: bentoBubbleMenu,
                }),
                TiptapModules.MahoBentoCell,
                TiptapModules.MahoFullscreen,
                TiptapModules.DragHandle.configure({
                    render: () => {
                        const el = document.createElement('div');
                        el.classList.add('tiptap-drag-handle');
                        el.innerHTML = this.getIcon('drag-handle');
                        return el;
                    },
                }),
            ],
            onTransaction: ({ editor }) => {
                if (editor.isInitialized) {
                    this.syncHandlerDebounced();
                    this.updateToolbarState();
                }
            },
            onContentError: ({ editor, error }) => {
                this.invalidContent = error.cause.message;
            },
        });

        // Turn off content check after initial content
        this.editor.options.enableContentCheck = false;

        // Update toolbar state initially
        this.updateToolbarState();

        this.bindEventListeners();

        // Fire initialization event
        varienGlobalEvents?.fireEvent('wysiwygEditorInitialized', this.editor);
    }

    destroy() {
        if (this.editor) {
            // Save content before destroying
            this.syncWysiwygToPlain();

            // Destroy the Tiptap instance
            this.editor.destroy();
            this.editor = null;
        }

        // Remove the wrapper which contains everything
        document.getElementById(`${this.id}_wrapper`)?.remove();

        this.unbindEventListeners();
    }

    linkHandler() {
        const previousUrl = this.editor.getAttributes('link').href;
        const url = window.prompt('URL', previousUrl);

        if (url === null) {
            return;
        }

        if (url === '') {
            this.editor.chain().focus().extendMarkRange('link').unsetLink().run();
            return;
        }

        this.editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    }

    headingHandler(event) {
        const level = parseInt(event.target.value);
        if (level) {
            this.editor.chain().focus().toggleHeading({ level }).run();
        } else {
            this.editor.chain().focus().setParagraph().run();
        }
    }

    createMainToolbar() {
        const toolbar = this.createToolbar([
            { type: 'select', options: [['','Paragraph'], [1,'Heading 1'], [2,'Heading 2'], [3,'Heading 3'], [4,'Heading 4'], [5,'Heading 5']], onChange: this.headingHandler.bind(this) },
            { type: 'separator'},
            { type: 'button', title: 'Bold', icon: 'bold', command: 'toggleMark', args: ['bold'] },
            { type: 'button', title: 'Italic', icon: 'italic', command: 'toggleMark', args: ['italic'] },
            { type: 'button', title: 'Underline', icon: 'underline', command: 'toggleMark', args: ['underline'] },
            { type: 'button', title: 'Strike', icon: 'strike', command: 'toggleMark', args: ['strike'] },
            { type: 'button', title: 'Block Quote', icon: 'blockquote', command: 'toggleBlockquote' },
            { type: 'separator'},
            { type: 'button', title: 'Bullet List', icon: 'bullet-list', command: 'toggleBulletList' },
            { type: 'button', title: 'Ordered List', icon: 'ordered-list', command: 'toggleOrderedList' },
            { type: 'separator'},
            { type: 'button', title: 'Align Left', icon: 'align-left', command: 'setTextAlign', args: ['left'] },
            { type: 'button', title: 'Align Center', icon: 'align-center', command: 'setTextAlign', args: ['center'] },
            { type: 'button', title: 'Align Right', icon: 'align-right', command: 'setTextAlign', args: ['right'] },
            { type: 'separator'},
            { type: 'button', title: 'Link', icon: 'link', onClick: this.linkHandler.bind(this) },
            { type: 'button', title: 'Insert Table', icon: 'table', command: 'insertTable', args: [{rows:3, cols:3, withHeaderRow:true}] },
            {
                type: 'dropdown',
                title: 'Columns',
                icon: 'columns-2',
                showTitle: false,
                showChevron: false,
                items: [
                    { title: '2 Columns', icon: 'columns-2', command: 'insertColumns', args: ['2-equal'] },
                    { title: '3 Columns', icon: 'columns-3', command: 'insertColumns', args: ['3-equal'] },
                    { title: '4 Columns', icon: 'columns-4', command: 'insertColumns', args: ['4-equal'] },
                    { title: 'Sidebar Left', icon: 'columns-sidebar-left', command: 'insertColumns', args: ['sidebar-left'] },
                    { title: 'Sidebar Right', icon: 'columns-sidebar-right', command: 'insertColumns', args: ['sidebar-right'] },
                    { title: 'Wide Center', icon: 'columns-wide-center', command: 'insertColumns', args: ['wide-center'] },
                ],
            },
            {
                type: 'dropdown',
                title: 'Bento Grid',
                icon: 'bento-grid',
                showTitle: false,
                showChevron: false,
                items: [
                    { title: 'Hero + 2 Cards', icon: 'bento-hero-2', command: 'insertBentoGrid', args: ['hero-2'] },
                    { title: 'Feature Left', icon: 'bento-feature-left', command: 'insertBentoGrid', args: ['feature-left'] },
                    { title: 'Feature Right', icon: 'bento-feature-right', command: 'insertBentoGrid', args: ['feature-right'] },
                    { title: 'Hero + 3 Cards', icon: 'bento-hero-3', command: 'insertBentoGrid', args: ['hero-3'] },
                    { title: 'Dashboard', icon: 'bento-dashboard', command: 'insertBentoGrid', args: ['dashboard'] },
                    { title: 'Magazine', icon: 'bento-magazine', command: 'insertBentoGrid', args: ['magazine'] },
                    { title: 'Showcase', icon: 'bento-showcase', command: 'insertBentoGrid', args: ['showcase'] },
                    { title: 'Mosaic', icon: 'bento-mosaic', command: 'insertBentoGrid', args: ['mosaic'] },
                    { title: 'Hero + 4 Cards', icon: 'bento-hero-4', command: 'insertBentoGrid', args: ['hero-4'] },
                    { title: 'Gallery', icon: 'bento-gallery', command: 'insertBentoGrid', args: ['gallery'] },
                    { title: 'Editorial', icon: 'bento-editorial', command: 'insertBentoGrid', args: ['editorial'] },
                    { title: 'Banner + Cards', icon: 'bento-banner-cards', command: 'insertBentoGrid', args: ['banner-cards'] },
                ],
            },
            { type: 'button', title: 'Insert Image', icon: 'image', command: 'insertMahoImage', enabled: this.config.add_images },
            { type: 'button', title: 'Insert Slideshow', icon: 'slideshow', command: 'insertMahoSlideshow', enabled: this.config.add_images && this.config.add_slideshows !== false },
            { type: 'button', title: 'Insert Widget', icon: 'widget', command: 'insertMahoWidget', enabled: this.config.add_widgets },
            { type: 'button', title: 'Insert Variable', icon: 'variable', command: 'insertMahoVariable', enabled: this.config.add_variables},
            { type: 'spacer' },
            { type: 'button', title: 'Fullscreen', icon: 'fullscreen-maximize', command: 'toggleFullscreen', enabled: !this.textarea.closest('dialog') },
        ]);

        toolbar.id = `${this.id}_toolbar`;
        toolbar.className = 'tiptap-toolbar';
        return toolbar;
    }

    createTableBubbleMenu() {
        const bubbleMenu = this.createToolbar([
            {
                type: 'dropdown',
                title: 'Columns',
                icon: 'columns',
                items: [
                    { title: 'Add Column Before', command: 'addColumnBefore', icon: 'column-insert-left' },
                    { title: 'Add Column After', command: 'addColumnAfter', icon: 'column-insert-right' },
                    { title: 'Delete Column', command: 'deleteColumn', icon: 'column-remove' },
                ],
            },
            {
                type: 'dropdown',
                title: 'Rows',
                icon: 'rows',
                items: [
                    { title: 'Add Row Before', command: 'addRowBefore', icon: 'row-insert-top' },
                    { title: 'Add Row After', command: 'addRowAfter', icon: 'row-insert-bottom' },
                    { title: 'Delete Row', command: 'deleteRow', icon: 'row-remove' },
                ],
            },
            {
                type: 'dropdown',
                title: 'Alignment',
                icon: 'align-center',
                items: [
                    { title: 'Align Left', command: 'setTextAlign', args: ['left'], icon: 'align-left' },
                    { title: 'Align Center', command: 'setTextAlign', args: ['center'], icon: 'align-center' },
                    { title: 'Align Right', command: 'setTextAlign', args: ['right'], icon: 'align-right' },
                    { title: 'Align Top', command: 'setVerticalAlign', args: ['top'], icon: 'valign-top' },
                    { title: 'Align Middle', command: 'setVerticalAlign', args: ['middle'], icon: 'valign-middle' },
                    { title: 'Align Bottom', command: 'setVerticalAlign', args: ['bottom'], icon: 'valign-bottom' },
                ],
            },
            {
                type: 'dropdown',
                title: 'Cells',
                icon: 'cells',
                items: [
                    { title: 'Merge Cells', command: 'mergeCells', icon: 'arrows-join' },
                    { title: 'Split Cell', command: 'splitCell', icon: 'arrows-split' },
                    { title: 'Toggle Header Column', command: 'toggleHeaderColumn', icon: 'table-column' },
                    { title: 'Toggle Header Row', command: 'toggleHeaderRow', icon: 'table-row' },
                ],
            },
            { type: 'button', title: 'Delete Table', command: 'deleteTable', icon: 'trash' },
        ]);

        bubbleMenu.id = `${this.id}_table_bubble_menu`;
        bubbleMenu.className = 'tiptap-bubble-menu';
        return bubbleMenu;
    }

    createColumnsBubbleMenu() {
        const bubbleMenu = this.createToolbar([
            { type: 'label', text: 'Gap:' },
            { type: 'button', title: 'No Gap', icon: 'gap-none', command: 'setColumnsGap', args: ['none'], dataGap: 'none' },
            { type: 'button', title: 'Small', icon: 'gap-small', command: 'setColumnsGap', args: ['small'], dataGap: 'small' },
            { type: 'button', title: 'Medium', icon: 'gap-medium', command: 'setColumnsGap', args: ['medium'], dataGap: 'medium' },
            { type: 'button', title: 'Large', icon: 'gap-large', command: 'setColumnsGap', args: ['large'], dataGap: 'large' },
            { type: 'separator' },
            { type: 'label', text: 'Style:' },
            { type: 'button', title: 'None', icon: 'style-none', command: 'setColumnsStyle', args: ['none'], dataGridStyle: 'none' },
            { type: 'button', title: 'Cards', icon: 'style-cards', command: 'setColumnsStyle', args: ['cards'], dataGridStyle: 'cards' },
            { type: 'button', title: 'Separated', icon: 'style-separated', command: 'setColumnsStyle', args: ['separated'], dataGridStyle: 'separated' },
            { type: 'separator' },
            { type: 'button', title: 'Delete Columns', icon: 'trash', command: 'deleteColumns' },
        ]);

        bubbleMenu.id = `${this.id}_columns_bubble_menu`;
        bubbleMenu.className = 'tiptap-bubble-menu';
        bubbleMenu.style.display = 'none';
        return bubbleMenu;
    }

    createBentoBubbleMenu() {
        const bubbleMenu = this.createToolbar([
            { type: 'label', text: 'Gap:' },
            { type: 'button', title: 'No Gap', icon: 'gap-none', command: 'setBentoGap', args: ['none'], dataGap: 'none' },
            { type: 'button', title: 'Small', icon: 'gap-small', command: 'setBentoGap', args: ['small'], dataGap: 'small' },
            { type: 'button', title: 'Medium', icon: 'gap-medium', command: 'setBentoGap', args: ['medium'], dataGap: 'medium' },
            { type: 'button', title: 'Large', icon: 'gap-large', command: 'setBentoGap', args: ['large'], dataGap: 'large' },
            { type: 'separator' },
            { type: 'label', text: 'Style:' },
            { type: 'button', title: 'None', icon: 'style-none', command: 'setBentoStyle', args: ['none'], dataGridStyle: 'none' },
            { type: 'button', title: 'Cards', icon: 'style-cards', command: 'setBentoStyle', args: ['cards'], dataGridStyle: 'cards' },
            { type: 'separator' },
            { type: 'button', title: 'Delete Bento Grid', icon: 'trash', command: 'deleteBentoGrid' },
        ]);

        bubbleMenu.id = `${this.id}_bento_bubble_menu`;
        bubbleMenu.className = 'tiptap-bubble-menu';
        bubbleMenu.style.display = 'none';
        return bubbleMenu;
    }

    createToolbar(items) {
        const toolbar = document.createElement('div');
        const addGroup = () => {
            const group = document.createElement('div');
            group.className = 'toolbar-group';
            return toolbar.appendChild(group);
        }
        let group = addGroup();
        for (const item of items) {
            if (!(item.enabled ?? true)) {
                continue;
            }
            if (item.type === 'separator') {
                const separator = document.createElement('div');
                separator.className = 'toolbar-separator';
                toolbar.append(separator);
                group = addGroup();
            }
            else if (item.type === 'spacer') {
                const spacer = document.createElement('div');
                spacer.className = 'toolbar-spacer';
                toolbar.append(spacer);
                group = addGroup();
            }
            else if (item.type === 'label') {
                const label = document.createElement('span');
                label.className = 'toolbar-label';
                label.textContent = this.translate(item.text);
                group.append(label);
            }
            else if (item.type === 'select') {
                const select = document.createElement('select');
                for (const option of item.options) {
                    const [ value, label ] = option;
                    select.add(new Option(this.translate(label), value));
                }
                if (typeof item.onChange === 'function') {
                    select.addEventListener('change', item.onChange);
                }
                group.append(select);
            }
            else if (item.type === 'dropdown') {
                const dropdown = document.createElement('div');
                dropdown.className = 'toolbar-dropdown';

                const toggle = document.createElement('button');
                toggle.type = 'button';
                const isIconOnly = item.showTitle === false && item.showChevron === false;
                toggle.className = 'toolbar-dropdown-toggle' + (isIconOnly ? ' icon-only' : '');
                toggle.title = this.translate(item.title ?? '');
                const titleHtml = item.showTitle !== false ? `<span>${this.translate(item.title ?? '')}</span>` : '';
                const chevronHtml = item.showChevron !== false ? this.getIcon('chevron-down') : '';
                toggle.innerHTML = this.getIcon(item.icon) + titleHtml + chevronHtml;
                dropdown.append(toggle);

                const menu = document.createElement('div');
                menu.className = 'toolbar-dropdown-menu';

                for (const menuItem of item.items) {
                    const menuButton = document.createElement('button');
                    menuButton.type = 'button';
                    menuButton.innerHTML = this.getIcon(menuItem.icon) + `<span>${this.translate(menuItem.title)}</span>`;

                    if (typeof menuItem.onClick === 'function') {
                        menuButton.addEventListener('click', () => {
                            menuItem.onClick();
                            dropdown.classList.remove('is-open');
                        });
                    } else if (menuItem.command) {
                        const args = Array.isArray(menuItem.args) ? menuItem.args : [];
                        menuButton.addEventListener('click', () => {
                            this.editor.chain().focus()[menuItem.command](...args).run();
                            dropdown.classList.remove('is-open');
                        });
                    }
                    menu.append(menuButton);
                }

                dropdown.append(menu);

                // Toggle dropdown on click
                toggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    // Close other open dropdowns
                    for (const other of toolbar.querySelectorAll('.toolbar-dropdown.is-open')) {
                        if (other !== dropdown) {
                            other.classList.remove('is-open');
                        }
                    }
                    dropdown.classList.toggle('is-open');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', () => {
                    dropdown.classList.remove('is-open');
                });

                group.append(dropdown);
            }
            else if (item.type === 'button') {
                const button = document.createElement('button');
                button.type = 'button';
                button.title = this.translate(item.title ?? '');
                button.innerHTML = this.getIcon(item.icon);

                if (typeof item.onClick === 'function') {
                    button.addEventListener('click', item.onClick);
                }
                else if (item.command) {
                    item.args = Array.isArray(item.args) ? item.args : [];
                    button.addEventListener('click', () => {
                        this.editor.chain().focus()[item.command](...item.args).run();
                    });
                    button.dataset.command = item.command;
                    button.dataset.args = JSON.stringify(item.args);
                }
                if (item.dataGap) {
                    button.dataset.gap = item.dataGap;
                }
                if (item.dataGridStyle) {
                    button.dataset.gridStyle = item.dataGridStyle;
                }
                group.append(button);
            }
            else if (item instanceof HTMLElement) {
                group.append(item);
            }
        }
        return toolbar;
    }

    updateToolbarState() {
        if (!this.editor) {
            return;
        }

        // Update heading dropdown
        const level = this.editor.getAttributes('heading').level ?? '';
        document.querySelector(`#${this.id}_toolbar select`).value = level;

        // Update button states
        for (const button of document.querySelectorAll(`#${this.id}_toolbar button[data-command]`)) {
            const command = button.dataset.command;
            const args = JSON.parse(button.dataset.args);
            let isActive = false;
            switch (command) {
            case 'toggleMark':
                isActive = this.editor.isActive(args[0]);
                break;
            case 'setTextAlign':
                isActive = this.editor.isActive({ textAlign: args[0] });
                break;
            case 'toggleBlockquote':
                isActive = this.editor.isActive('blockquote');
                break;
            case 'toggleBulletList':
                isActive = this.editor.isActive('bulletList');
                break;
            case 'toggleOrderedList':
                isActive = this.editor.isActive('orderedList');
                break;
            }
            button.classList.toggle('is-active', isActive);
        }
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

    getTextArea() {
        return this.textarea;
    }

    updateTextArea() {
        this.syncWysiwygToPlain();
    }

    getIcon(name) {
        const iconPath = tiptapWysiwygSetup.iconRegistry[name];
        if (!iconPath) {
            console.warn(`Icon "${name}" not found in registry`);
            return '';
        }
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${iconPath}</svg>`;
    }

    static iconRegistry = {
        // Formatting icons
        'bold': '<path d="M7 5h6a3.5 3.5 0 0 1 0 7h-6z"/><path d="M13 12h1a3.5 3.5 0 0 1 0 7h-7v-7"/>',
        'italic': '<path d="M11 5l6 0"/><path d="M7 19l6 0"/><path d="M14 5l-4 14"/>',
        'underline': '<path d="M7 5v5a5 5 0 0 0 10 0v-5"/><path d="M5 19h14"/>',
        'strike': '<path d="M5 12l14 0"/><path d="M16 6.5a4 2 0 0 0 -4 -1.5h-1a3.5 3.5 0 0 0 0 7h2a3.5 3.5 0 0 1 0 7h-1.5a4 2 0 0 1 -4 -1.5"/>',
        'blockquote': '<path d="M6 15h15"/><path d="M21 19h-15"/><path d="M15 11h6"/><path d="M21 7h-6"/><path d="M9 9h1a1 1 0 1 1 -1 1v-2.5a2 2 0 0 1 2 -2"/><path d="M3 9h1a1 1 0 1 1 -1 1v-2.5a2 2 0 0 1 2 -2"/>',

        // List icons
        'bullet-list': '<path d="M9 6l11 0"/><path d="M9 12l11 0"/><path d="M9 18l11 0"/><path d="M5 6l0 .01"/><path d="M5 12l0 .01"/><path d="M5 18l0 .01"/>',
        'ordered-list': '<path d="M11 6h9"/><path d="M11 12h9"/><path d="M12 18h8"/><path d="M4 16a2 2 0 1 1 4 0c0 .591 -.5 1 -1 1.5l-3 2.5h4"/><path d="M6 10v-6l-2 2"/>',

        // Alignment icons
        'align-left': '<path d="M4 6l16 0"/><path d="M4 12l10 0"/><path d="M4 18l14 0"/>',
        'align-center': '<path d="M4 6l16 0"/><path d="M8 12l8 0"/><path d="M6 18l12 0"/>',
        'align-right': '<path d="M4 6l16 0"/><path d="M10 12l10 0"/><path d="M6 18l14 0"/>',

        // Vertical alignment icons
        'valign-top': '<path d="M4 4h16"/><path d="M12 8v12"/><path d="M8 12l4 -4l4 4"/>',
        'valign-middle': '<path d="M12 4v6"/><path d="M12 14v6"/><path d="M8 8l4 4l4 -4"/><path d="M8 16l4 -4l4 4"/>',
        'valign-bottom': '<path d="M4 20h16"/><path d="M12 4v12"/><path d="M8 12l4 4l4 -4"/>',

        // Insert icons
        'link': '<path d="M9 15l6 -6"/><path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464"/><path d="M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.524 -.463"/>',
        'image': '<path d="M15 8h.01"/><path d="M3 6a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v12a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3v-12z"/><path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l5 5"/><path d="M14 14l1 -1c.928 -.893 2.072 -.893 3 0l3 3"/>',
        'table': '<path d="M3 5a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-14z"/><path d="M3 10h18"/><path d="M10 3v18"/>',
        'widget': '<path d="M18 16v.01"/><path d="M6 16v.01"/><path d="M12 5v.01"/><path d="M12 12v.01"/><path d="M12 1a4 4 0 0 1 2.001 7.464l.001 .072a3.998 3.998 0 0 1 1.987 3.758l.22 .128a3.978 3.978 0 0 1 1.591 -.417l.2 -.005a4 4 0 1 1 -3.994 3.77l-.28 -.16c-.522 .25 -1.108 .39 -1.726 .39c-.619 0 -1.205 -.14 -1.728 -.391l-.279 .16l.007 .231a4 4 0 1 1 -2.212 -3.579l.222 -.129a3.998 3.998 0 0 1 1.988 -3.756l.002 -.071a4 4 0 0 1 -1.995 -3.265l-.005 -.2a4 4 0 0 1 4 -4z"/>',
        'block': '<path d="M3 3m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"/><path d="M9 9h6v6h-6z"/><path d="M3 15h6m6 0h6m-18 -6h6m6 0h6"/>',
        'variable': '<path d="M5 4c-2.5 5 -2.5 10 0 16m14 -16c2.5 5 2.5 10 0 16m-10 -11h1c1 0 1 1 2.016 3.527c.984 2.473 .984 3.473 1.984 3.473h1"/><path d="M8 16c1.5 0 3 -2 4 -3.5s2.5 -3.5 4 -3.5"/>',
        'slideshow': '<path d="M15 6l.01 0"/><path d="M3 3m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z"/><path d="M3 13l4 -4a3 5 0 0 1 3 0l4 4"/><path d="M13 12l2 -2a3 5 0 0 1 3 0l3 3"/><path d="M8 21l.01 0"/><path d="M12 21l.01 0"/><path d="M16 21l.01 0"/>',

        // Table operation icons
        'column-insert-left': '<path d="M14 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-14a1 1 0 0 1 1 -1z"/><path d="M5 12l4 0"/><path d="M7 10l0 4"/>',
        'column-insert-right': '<path d="M6 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-14a1 1 0 0 1 1 -1z"/><path d="M15 12l4 0"/><path d="M17 10l0 4"/>',
        'column-remove': '<path d="M6 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-14a1 1 0 0 1 1 -1z"/><path d="M16 10l4 4"/><path d="M16 14l4 -4"/>',
        'row-insert-top': '<path d="M4 6h16a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-16a1 1 0 0 1 -1 -1v-4a1 1 0 0 1 1 -1z"/><path d="M12 2l0 4"/><path d="M10 4l4 0"/>',
        'row-insert-bottom': '<path d="M20 14v4a1 1 0 0 1 -1 1h-14a1 1 0 0 1 -1 -1v-4a1 1 0 0 1 1 -1h14a1 1 0 0 1 1 1z"/><path d="M12 2l0 4"/><path d="M10 4l4 0"/>',
        'row-remove': '<path d="M20 6v4a1 1 0 0 1 -1 1h-14a1 1 0 0 1 -1 -1v-4a1 1 0 0 1 1 -1h14a1 1 0 0 1 1 1z"/><path d="M10 16l4 4"/><path d="M10 20l4 -4"/>',
        'arrows-join': '<path d="M12 21v-6m-3 3l3 3l3 -3m-3 -9v-6m-3 3l3 -3l3 3"/>',
        'arrows-split': '<path d="M21 17h-8l-3.5 -5h-6.5"/><path d="M21 7h-8l-3.5 5h-6.5"/><path d="M18 10l3 -3l-3 -3"/><path d="M18 20l3 -3l-3 -3"/>',
        'table-row': '<path d="M3 5a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-14z"/><path d="M9 3l-6 6"/><path d="M14 3l-7 7"/><path d="M19 3l-7 7"/><path d="M21 6l-4 4"/><path d="M3 10h18"/><path d="M10 10v11"/>',
        'table-column': '<path d="M3 5a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-14z"/><path d="M10 10h11"/><path d="M10 3v18"/><path d="M9 3l-6 6"/><path d="M10 7l-7 7"/><path d="M10 12l-7 7"/><path d="M10 17l-4 4"/>',
        'trash': '<path d="M4 7l16 0"/><path d="M10 11l0 6"/><path d="M14 11l0 6"/><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"/><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"/>',

        // Misc icons
        'hamburger': '<path d="M4 6l16 0"></path><path d="M4 12l16 0"></path><path d="M4 18l16 0"></path>',
        'fullscreen-maximize': '<path d="M16 4l4 0l0 4"/><path d="M14 10l6 -6"/><path d="M8 20l-4 0l0 -4"/><path d="M4 20l6 -6"/><path d="M16 20l4 0l0 -4"/><path d="M14 14l6 6"/><path d="M8 4l-4 0l0 4"/><path d="M4 4l6 6"/>',
        'fullscreen-minimize': '<path d="M5 9l4 0l0 -4"/><path d="M3 3l6 6"/><path d="M5 15l4 0l0 4"/><path d="M3 21l6 -6"/><path d="M19 9l-4 0l0 -4"/><path d="M15 9l6 -6"/><path d="M19 15l-4 0l0 4"/><path d="M15 15l6 6"/>',
        'drag-handle': '<path d="M9 5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M9 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M9 19m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M15 5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M15 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M15 19m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/>',

        // Dropdown icons
        'chevron-down': '<path d="M6 9l6 6l6 -6"/>',
        'columns': '<path d="M4 4m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z"/><path d="M12 4v16"/>',
        'rows': '<path d="M4 4m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z"/><path d="M4 12h16"/>',
        'cells': '<path d="M4 4m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z"/><path d="M4 10h16"/><path d="M10 4v16"/><path d="M4 16h16"/>',

        // Column layout icons
        'layout-columns': '<path d="M4 4m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z"/><path d="M12 4v16"/>',
        'columns-2': '<path d="M4 4m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z"/><path d="M12 4v16"/>',
        'columns-3': '<rect x="1" y="3" width="6" height="18" rx="1" stroke="currentColor" fill="none"/><rect x="9" y="3" width="6" height="18" rx="1" stroke="currentColor" fill="none"/><rect x="17" y="3" width="6" height="18" rx="1" stroke="currentColor" fill="none"/>',
        'columns-4': '<rect x="1" y="3" width="4" height="18" rx="1" stroke="currentColor" fill="none"/><rect x="6.5" y="3" width="4" height="18" rx="1" stroke="currentColor" fill="none"/><rect x="12" y="3" width="4" height="18" rx="1" stroke="currentColor" fill="none"/><rect x="17.5" y="3" width="4" height="18" rx="1" stroke="currentColor" fill="none"/>',
        'columns-sidebar-left': '<rect x="2" y="3" width="6" height="18" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.15"/><rect x="10" y="3" width="12" height="18" rx="1" stroke="currentColor" fill="none"/>',
        'columns-sidebar-right': '<rect x="2" y="3" width="12" height="18" rx="1" stroke="currentColor" fill="none"/><rect x="16" y="3" width="6" height="18" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.15"/>',
        'columns-wide-center': '<rect x="1" y="3" width="5" height="18" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.15"/><rect x="8" y="3" width="8" height="18" rx="1" stroke="currentColor" fill="none"/><rect x="18" y="3" width="5" height="18" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.15"/>',

        // Gap icons
        'gap-none': '<rect x="3" y="4" width="8" height="16" rx="1" fill="currentColor" opacity="0.3"/><rect x="13" y="4" width="8" height="16" rx="1" fill="currentColor" opacity="0.3"/>',
        'gap-small': '<rect x="3" y="4" width="7" height="16" rx="1" fill="currentColor" opacity="0.3"/><rect x="14" y="4" width="7" height="16" rx="1" fill="currentColor" opacity="0.3"/>',
        'gap-medium': '<rect x="3" y="4" width="6" height="16" rx="1" fill="currentColor" opacity="0.3"/><rect x="15" y="4" width="6" height="16" rx="1" fill="currentColor" opacity="0.3"/>',
        'gap-large': '<rect x="3" y="4" width="5" height="16" rx="1" fill="currentColor" opacity="0.3"/><rect x="16" y="4" width="5" height="16" rx="1" fill="currentColor" opacity="0.3"/>',

        // Style icons
        'style-none': '<rect x="3" y="4" width="18" height="16" rx="2" fill="none" stroke="currentColor" stroke-dasharray="2 2"/>',
        'style-cards': '<rect x="3" y="4" width="18" height="16" rx="2" fill="none" stroke="currentColor"/>',
        'style-separated': '<rect x="3" y="4" width="7" height="16" rx="1" fill="currentColor" opacity="0.15"/><rect x="14" y="4" width="7" height="16" rx="1" fill="currentColor" opacity="0.15"/><path d="M12 4v16" stroke="currentColor" stroke-width="1"/>',

        // Bento grid icon (toolbar button)
        'bento-grid': '<rect x="2" y="2" width="20" height="10" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.1"/><rect x="2" y="14" width="9" height="8" rx="1" stroke="currentColor" fill="none"/><rect x="13" y="14" width="9" height="8" rx="1" stroke="currentColor" fill="none"/>',

        // Bento preset icons (2-column)
        'bento-hero-2': '<rect x="2" y="2" width="20" height="10" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.15"/><rect x="2" y="14" width="9" height="8" rx="1" stroke="currentColor" fill="none"/><rect x="13" y="14" width="9" height="8" rx="1" stroke="currentColor" fill="none"/>',
        'bento-feature-left': '<rect x="2" y="2" width="12" height="20" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.15"/><rect x="16" y="2" width="6" height="9" rx="1" stroke="currentColor" fill="none"/><rect x="16" y="13" width="6" height="9" rx="1" stroke="currentColor" fill="none"/>',
        'bento-feature-right': '<rect x="2" y="2" width="6" height="9" rx="1" stroke="currentColor" fill="none"/><rect x="10" y="2" width="12" height="20" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.15"/><rect x="2" y="13" width="6" height="9" rx="1" stroke="currentColor" fill="none"/>',

        // Bento preset icons (3-column)
        'bento-hero-3': '<rect x="2" y="2" width="20" height="10" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.15"/><rect x="2" y="14" width="6" height="8" rx="1" stroke="currentColor" fill="none"/><rect x="9.5" y="14" width="5" height="8" rx="1" stroke="currentColor" fill="none"/><rect x="16" y="14" width="6" height="8" rx="1" stroke="currentColor" fill="none"/>',
        'bento-dashboard': '<rect x="2" y="2" width="13" height="10" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.15"/><rect x="16.5" y="2" width="5.5" height="10" rx="1" stroke="currentColor" fill="none"/><rect x="2" y="14" width="6" height="8" rx="1" stroke="currentColor" fill="none"/><rect x="9.5" y="14" width="5" height="8" rx="1" stroke="currentColor" fill="none"/><rect x="16" y="14" width="6" height="8" rx="1" stroke="currentColor" fill="none"/>',
        'bento-magazine': '<rect x="2" y="2" width="13" height="20" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.15"/><rect x="16.5" y="2" width="5.5" height="9" rx="1" stroke="currentColor" fill="none"/><rect x="16.5" y="13" width="5.5" height="9" rx="1" stroke="currentColor" fill="none"/>',
        'bento-showcase': '<rect x="2" y="2" width="13" height="9" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.15"/><rect x="16.5" y="2" width="5.5" height="9" rx="1" stroke="currentColor" fill="none"/><rect x="2" y="13" width="5.5" height="9" rx="1" stroke="currentColor" fill="none"/><rect x="9" y="13" width="13" height="9" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.15"/>',
        'bento-mosaic': '<rect x="2" y="2" width="6" height="20" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.15"/><rect x="9.5" y="2" width="12.5" height="9" rx="1" stroke="currentColor" fill="none"/><rect x="9.5" y="13" width="5.5" height="9" rx="1" stroke="currentColor" fill="none"/><rect x="16.5" y="13" width="5.5" height="9" rx="1" stroke="currentColor" fill="none"/>',

        // Bento preset icons (4-column)
        'bento-hero-4': '<rect x="1" y="2" width="22" height="10" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.15"/><rect x="1" y="14" width="5" height="8" rx="1" stroke="currentColor" fill="none"/><rect x="7" y="14" width="4" height="8" rx="1" stroke="currentColor" fill="none"/><rect x="12.5" y="14" width="5" height="8" rx="1" stroke="currentColor" fill="none"/><rect x="18.5" y="14" width="4.5" height="8" rx="1" stroke="currentColor" fill="none"/>',
        'bento-gallery': '<rect x="1" y="2" width="10.5" height="9" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.15"/><rect x="13" y="2" width="4.5" height="9" rx="1" stroke="currentColor" fill="none"/><rect x="19" y="2" width="4" height="20" rx="1" stroke="currentColor" fill="none"/><rect x="1" y="13" width="4.5" height="9" rx="1" stroke="currentColor" fill="none"/><rect x="7" y="13" width="10.5" height="9" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.15"/>',
        'bento-editorial': '<rect x="1" y="2" width="5" height="20" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.15"/><rect x="7.5" y="2" width="9.5" height="9" rx="1" stroke="currentColor" fill="none"/><rect x="18.5" y="2" width="4.5" height="20" rx="1" stroke="currentColor" fill="none"/><rect x="7.5" y="13" width="9.5" height="9" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.15"/>',
        'bento-banner-cards': '<rect x="1" y="1" width="22" height="7" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.15"/><rect x="1" y="10" width="10.5" height="6" rx="1" stroke="currentColor" fill="none"/><rect x="12.5" y="10" width="10.5" height="6" rx="1" stroke="currentColor" fill="none"/><rect x="1" y="18" width="5" height="5" rx="1" stroke="currentColor" fill="none"/><rect x="7.5" y="18" width="9.5" height="5" rx="1" stroke="currentColor" fill="none"/><rect x="18.5" y="18" width="4.5" height="5" rx="1" stroke="currentColor" fill="none"/>',
    };
}

window.tiptapWysiwygSetup = tiptapWysiwygSetup;

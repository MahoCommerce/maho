/**
 * Maho
 *
 * @package     js
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * Maho Tree - create a nested tree with checkboxes, drag-and-drop, and lazy-loading
 */
class MahoTree {
    static nodeDataMap = new WeakMap();

    /**
     * @typedef {Object} SelectableOpts
     * @prop {string} [mode='nested'] - `radio|single|simple|nested`
     * @prop {boolean} [showInputs=true] - `false` to hide radio / checkbox inputs and show outline around selected nodes
     * @prop {string} [radioName] - if radio mode, then form name of the radio elements
     * @prop {function(Array<MahoTreeNode>):null} [onSelect] - callback when a node is selected
     *
     * @typedef {Object} SortableOpts - plus any from {@link https://github.com/SortableJS/Sortable?tab=readme-ov-file#options}
     * @prop {Object|string} [group] - use the same group name to allow nodes to be dragged across trees
     * @prop {boolean} [mahoTreeNestedFolder=true] - enable the nested folder sortable.js plugin
     * @prop {boolean} [containDepth=false] - contain draggable nodes to the same depth
     * @prop {boolean} [rootSortable=true] - allow the root node to be sortable
     *
     * @typedef {Object} LazyloadOpts
     * @prop {string} [dataUrl] - URL to load children from
     * @prop {string} [nodeParameter='node'] - POST param to send node's ID as
     * @prop {function(MahoTreeNode, URLSearchParams):null} [onBeforeLoad] - callback before children are loaded
     * @prop {function(MahoTreeNode, Error):null} [onLoadException] - callback when loading children fails
     *
     * @typedef {Object} MahoTreeCssVars
     * @prop {string} [indent='1.25rem'] - length to indent each node level
     * @prop {string} [spacing='0.25rem'] - length to space in between each node
     * @prop {string} [line-style='1px dotted #aaa'] - border style for connecting lines
     * @prop {string} [outline-style='2px solid #0090FF'] - outline style for selected nodes when `config.selectable.showInputs=false`
     * @prop {string} [marker-size='10px'] - size for the [+] and [-] expand icon
     * @prop {string} [icon-size='16px'] - size for the folder or leaf node icons
     * @prop {string} [label-gap='0.25rem'] - length between icon, checkbox, and label
     * @prop {string} [disabled-color='#999'] - text color for disabled nodes
     * @prop {string} [drop-color='#ccc'] - background color for dropping nodes onto folders
     *
     * @param {string} container - the container element's DOM ID
     * @param {Object} [config] - config options
     * @param {SelectableOpts|boolean|string} [config.selectable=false] - `true` for default options, string `radio|single|simple|nested`, or object
     * @param {SortableOpts|boolean|string} [config.sortable=false] - `true` for default options, string for sortable group name, or object
     * @param {LazyloadOpts|boolean|string} [config.lazyload=false] - `true` for default options, string for dataUrl, or object
     * @param {boolean} [config.showRootNode=true] - toggle visibility of the root node
     * @param {boolean} [config.showIcons=true] - toggle visibility of icons
     * @param {boolean} [config.treatAllNodesAsFolders=false] - make all node type folder
     * @param {boolean} [config.varienSetHasChanges] - emit event marking the tab as having changes
     * @param {MahoTreeCssVars} [config.cssVars] -
     */
    constructor(container, config = {}) {
        const containerEl = document.getElementById(container);
        if (!containerEl) {
            throw new Error(`Element with ID ${container} not found in DOM`);
        }

        this.uniqId = generateRandomString(6);

        this.config = {
            selectable: false,
            sortable: false,
            lazyload: false,
            showRootNode: true,
            treatAllNodesAsFolders: false,
            showIcons: true,
            varienSetHasChanges: true,
            cssVars: {},
            ...config,
        };

        this.selectableOpts = {
            mode: 'nested',
            showInputs: true,
            radioName: this.uniqId,
            onSelect: null,
        };

        this.sortableOpts = {
            group: null,
            animation: 150,
            invertSwap: true,
            fallbackOnBody: true,
            revertOnSpill: true,
            mahoTreeNestedFolder: true,
            containDepth: false,
            rootSortable: true,
        };

        this.lazyloadOpts = {
            dataUrl: null,
            nodeParameter: 'node',
            onBeforeLoad: null,
            onLoadException: null,
        };

        // Check for options using the string short-hand and set the appropriate option
        if (typeof this.config.selectable === 'string') {
            this.selectableOpts.mode = this.config.selectable;
            this.config.selectable = true;
        }
        if (typeof this.config.sortable === 'string') {
            this.sortableOpts.group = this.config.sortable;
            this.config.sortable = true;
        }
        if (typeof this.config.lazyload === 'string') {
            this.lazyloadOpts.dataUrl = this.config.lazyload;
            this.config.lazyload = true;
        }

        // Check for options using full object definitions, and bind any callbacks to this tree instance
        for (const key of ['selectable', 'sortable', 'lazyload']) {
            if (typeof this.config[key] === 'object' && this.config[key] !== null) {
                const obj = Object.assign(this[key + 'Opts'], this.config[key]);
                for (const callback of Object.keys(obj)) {
                    if (typeof obj[callback] === 'function') {
                        obj[callback] = obj[callback].bind(this);
                    }
                }
                this.config[key] = true;
            }
        }

        this.createElement();
        containerEl.appendChild(this.rootEl);

        this.bindEventListeners();
    }

    setRootNode(node) {
        if (node instanceof MahoTreeNode) {
            this.rootNode = node;
        } else if (Array.isArray(node)) {
            this.rootNode = new MahoTreeNode(this, {
                id: '__root__',
                text: 'Root',
                expanded: true,
                children: node,
            });
        } else if (typeof node === 'object' && node !== null) {
            this.rootNode = new MahoTreeNode(this, {
                expanded: true,
                children: [],
                ...node,
            });
        } else {
            throw new TypeError('Root node must be an object, array, or MahoTreeNode');
        }
        this.rootNode.isRoot = true;
        this.rootEl.replaceChildren(this.rootNode.ui.wrap);
    }

    setRootVisible(flag) {
        this.config.showRootNode = flag;
        this.rootEl.classList.toggle('hide-root-node', !flag);
    }

    createElement() {
        this.rootEl = document.createElement('ul');
        this.rootEl.classList.add('maho-tree');
        this.setRootVisible(this.config.showRootNode);

        for (const [cssVar, cssVal] of Object.entries(this.config.cssVars)) {
            this.rootEl.style.setProperty(`--${cssVar}`, cssVal);
        }
        if (!this.selectableOpts.showInputs) {
            this.rootEl.classList.add('hide-checkbox');
        }
    }

    bindEventListeners() {
        this.rootEl.addEventListener('change', (event) => {
            // Check conditions where checkboxes might change
            const shouldUpdate = [
                typeof event.originalEvent === 'DragEvent',
                event.target.tagName === 'INPUT' && ['checkbox', 'radio'].includes(event.target.type),
            ];
            if (!shouldUpdate.some(Boolean)) {
                return;
            }
            if (this.selectableOpts.mode === 'nested') {
                this.updateNestedCheckboxes();
            } else if (this.selectableOpts.mode === 'single') {
                this.rootEl.querySelectorAll('input[type=checkbox]:checked').forEach((el) => {
                    el.checked = el === event.target;
                });
            }
            if (typeof this.selectableOpts.onSelect === 'function') {
                this.selectableOpts.onSelect(this.getChecked());
            }
        });

        this.mutationObserver = new MutationObserver((mutationList, observer) => {
            for (const mutation of mutationList) {
                for (const el of mutation.addedNodes) {
                    if (el.tagName === 'LI') {
                        el.querySelectorAll(':scope ul').forEach(this.bindSortable.bind(this));
                    }
                }
            }
            if (this.selectableOpts.mode === 'nested') {
                this.updateNestedCheckboxes();
            }
        });

        this.mutationObserver.observe(this.rootEl, { childList: true, subtree: true });
    }

    bindSortable(el) {
        if (!this.config.sortable || Sortable.get(el)) {
            return;
        }
        if (this.sortableOpts.rootSortable === false && this.rootNode.ui.ctNode === el) {
            return;
        }

        const group = typeof this.sortableOpts.group === 'object' && this.sortableOpts.group !== null
              ? this.sortableOpts.group
              : { name: this.sortableOpts.group };

        group.name ??= `sortable.${this.uniqId}`;

        if (this.sortableOpts.containDepth === true) {
            let current = el, depth = 0;
            while (current !== this.rootEl) {
                current = current.parentNode.closest('ul');
                depth++;
            }
            group.name += '.' + depth;
        }

        MahoTreeDropZonePlugin.mount();
        MahoTreeNestedFolderPlugin.mount();

        new Sortable(el, { ...this.sortableOpts, group });
    }

    updateNestedCheckboxes() {
        if (this.selectableOpts.mode !== 'nested') {
            return;
        }
        Array.from(this.rootEl.querySelectorAll('li')).reverse().forEach((el) => {
            const parent = el.querySelector('input[type=checkbox]');
            const children = Array.from(el.querySelectorAll(':scope ul input[type=checkbox]'));
            if (parent && children.length) {
                if (children.every((el) => el.checked)) {
                    parent.checked = true;
                    parent.indeterminate = false;
                } else if (children.every((el) => !el.checked)) {
                    parent.checked = false;
                    parent.indeterminate = false;
                } else {
                    parent.checked = false;
                    parent.indeterminate = true;
                }
            }
        });
    }

    storeNode(node) {
        MahoTree.nodeDataMap.set(node.ui.wrap, node);
    }

    getRootNode() {
        return this.rootNode;
    }

    getNodeByEl(el) {
        return MahoTree.nodeDataMap.get(el);
    }

    getNodeById(id) {
        return this.getNodeByEl(this.rootEl.querySelector(`li[data-id='${id}']`));
    }

    getNodeByText(text) {
        return this.getNodeByEl(this.rootEl.querySelector(`li[data-text='${text}']`));
    }

    getChecked() {
        return Array.from(this.rootEl.querySelectorAll('input:checked')).map((el) => {
            return this.getNodeByEl(el.closest('li'));
        });
    }

    async expandPath(path) {
        const parts = path.split('/').filter(Boolean);
        let current = this.rootNode;
        for (const part of parts) {
            const node = this.getNodeById(part);
            if (node) {
                current = await node.expand();
            } else {
                break;
            }
        }
        return current;
    }

    async expandAll() {
        await this.rootNode.expandAll();
    }

    collapseAll() {
        this.rootNode.collapseAll();
    }

    selectAll() {
        this.rootEl.querySelectorAll('input[type=checkbox]').forEach((el) => {
            el.indeterminate = false;
            el.checked = true;
        });
        if (typeof this.selectableOpts.onSelect === 'function') {
            this.selectableOpts.onSelect(this.getChecked());
        }
    }

    deselectAll() {
        this.rootEl.querySelectorAll('input[type=checkbox],input[type=radio]').forEach((el) => {
            el.indeterminate = false;
            el.checked = false;
        });
        if (typeof this.selectableOpts.onSelect === 'function') {
            this.selectableOpts.onSelect(this.getChecked());
        }
    }

    dispatchEvent() {
        this.rootEl.dispatchEvent(...arguments);
    }
}

/**
 * MahoTreeNode - a node belonging to a MahoTree instance
 */
class MahoTreeNode {
    /**
     * @typedef {Object} MahoTreeNodeData
     * @prop {string|number} [id] - a unique id for this node
     * @prop {string} [type] - type of node, can be `folder|leaf`, or blank for auto-detection
     * @prop {string} [text] - label for the node
     * @prop {string} [name] - alias for text
     * @prop {string|boolean} [icon] - icon for the node, or false to hide
     * @prop {string} [cls] - extra classes to add to icon node
     * @prop {boolean} [selectable] - is the node selectable, defaults to tree setting
     * @prop {boolean} [disabled=false] - is the node disabled
     * @prop {boolean} [expanded=false] - if type folder, is the node expanded
     * @prop {boolean} [allowDrag=true] - can the node be dragged
     * @prop {boolean} [allowDrop=true] - if type folder, can nodes be dropped into it
     * @prop {MahoTreeNodeData[]} [children] - if type folder, list of child nodes
     *
     * @param {MahoTree} tree - the MahoTree instance this node is attached to
     * @param {MahoTreeNodeData|MahoTreeNodeData[]} data -
     */
    constructor(tree, data) {
        if (!(tree instanceof MahoTree)) {
            throw new TypeError('Tree parameter must be instance of MahoTree');
        }
        if (typeof data !== 'object' || Array.isArray(data) || data === null) {
            throw new TypeError('Data parameter must be an object');
        }

        const { children, ...attributes } = data;

        /**
         * @type {{
         *     wrap: HTMLLIElement,
         *     label: HTMLDivElement|HTMLLabelElement,
         *     textNode: HTMLSpanElement,
         *     iconNode: HTMLSpanElement,
         *     ctNode: HTMLUListElement|null,
         *     details: HTMLDetailsElement|null,
         *     summary: HTMLSummaryElement|null,
         *     checkbox: HTMLInputElement|null,
         * }}
         */
        this.ui = {};
        this.tree = tree;
        this.attributes = attributes;
        this.attributes.id ??= 'node-' + generateRandomString(6);

        if (this.attributes.type) {
            this.type = this.attributes.type;
        } else if (this.tree.config.treatAllNodesAsFolders || Array.isArray(children)) {
            this.type = 'folder';
        } else {
            this.type = 'leaf';
        }

        this.createElement();
        this.updateAttributes();
        this.bindEventListeners();

        if (this.type === 'folder') {
            if (Array.isArray(children)) {
                for (const child of children) {
                    this.appendChild(new MahoTreeNode(this.tree, child))
                }
                this.hasLoadedChildren = true;
            }
            this.ui.wrap.append(this.ui.details);
        } else {
            this.ui.wrap.append(this.ui.label);
        }

        this.isRoot = false;
        this.tree.storeNode(this);
    }

    createElement() {
        this.ui.wrap = document.createElement('li');
        this.createLabelElement();
        if (this.type === 'folder') {
            this.createDetailsElement()
            this.ui.wrap.append(this.ui.details);
            this.ui.summary.append(this.ui.label);
        } else {
            this.ui.wrap.append(this.ui.label);
        }
    }

    createLabelElement() {
        if (this.attributes.selectable ?? this.tree.config.selectable) {
            this.ui.label = document.createElement('label');
            this.ui.label.innerHTML = '<span class="icon"></span><input type="checkbox"><span></span>';
            this.ui.checkbox = this.ui.label.querySelector('input');
            if (this.tree.selectableOpts.mode === 'radio') {
                this.ui.checkbox.type = 'radio';
            }
        } else {
            this.ui.label = document.createElement('div');
            this.ui.label.innerHTML = '<span class="icon"></span><span></span>';
        }
        this.ui.label.classList.add('label');
        this.ui.textNode = this.ui.label.querySelector('span:not(.icon)');
        this.ui.iconNode = this.ui.label.querySelector('span.icon');
    }

    createDetailsElement() {
        this.ui.details = document.createElement('details');
        this.ui.details.open = this.attributes.expanded;
        this.ui.details.innerHTML = '<summary></summary><ul></ul>';
        this.ui.summary = this.ui.details.children[0];
        this.ui.ctNode = this.ui.details.children[1];
    }

    updateAttributes(data = {}) {
        Object.assign(this.attributes, data);

        this.id = this.attributes.id;
        this.ui.wrap.dataset.id = this.id;

        this.text = this.attributes.text ?? this.attributes.name;
        this.text ??= this.type.charAt(0).toUpperCase() + this.type.slice(1);
        this.ui.wrap.dataset.text = this.text;
        this.ui.textNode.textContent = unescapeHtml(this.text);

        this.ui.label.classList.toggle('disabled', this.attributes.disabled ?? false);

        this.icons = [];
        if (typeof this.attributes.icon === 'string') {
            this.icons.push(...this.attributes.icon.trim().split(/\s+/).filter(Boolean));
        } else if (this.attributes.icon === false || this.tree.config.showIcons === false) {
            this.icons.push('no-icon');
        }
        if (typeof this.attributes.cls === 'string') {
            this.icons.push(...this.attributes.cls.trim().split(/\s+/).filter(Boolean));
        }
        if (this.icons.length === 0) {
            this.icons.push(this.type);
        }
        this.ui.iconNode.className = 'icon ' + this.icons.join(' ');

        if (this.ui.checkbox) {
            this.ui.checkbox.checked = this.attributes.checked;
            this.ui.checkbox.disabled = this.attributes.disabled;
            this.ui.checkbox.name = this.ui.checkbox.type === 'radio'
                ? this.tree.selectableOpts.radioName
                : this.attributes.name;
        }
    }

    bindEventListeners() {
        this.ui.checkbox?.addEventListener('change', () => {
            if (this.tree.selectableOpts.mode === 'nested') {
                if (this.ui.checkbox.checked) {
                    this.selectChildren();
                } else {
                    this.deselectChildren();
                }
            }
            if (this.tree.config.varienSetHasChanges) {
                window.varienElementMethods?.setHasChanges(this.ui.checkbox);
            }
        });
        this.ui.label?.addEventListener('click', (event) => {
            if (event.srcElement.tagName === 'INPUT') {
                return;
            }
            if (this.ui.details && this.ui.checkbox?.type === 'radio') {
                if (this.ui.details.open && this.ui.checkbox.checked) {
                    this.collapse()
                } else {
                    this.expand();
                }
            }
        });
        this.ui.details?.addEventListener('toggle', () => {
            if (this.ui.details.open && !this.hasLoadedChildren) {
                this.loadChildren();
            }
        });
    }

    getUI() {
        return this.ui;
    }

    getPath() {
        const parts = [];
        let current = this;
        while (current) {
            parts.push(current.id);
            current = current.parentNode;
        }
        return parts.reverse().join('/');
    }

    contains(node) {
        return this.ui.wrap.contains(node.ui.wrap);
    }

    get allowDrag() {
        return this.attributes.allowDrag ?? true;
    }

    get allowDrop() {
        return this.attributes.allowDrop ?? true;
    }

    get parentNode() {
        const el = this.ui.wrap.parentElement?.closest('li');
        if (el) {
            return this.tree.getNodeByEl(el);
        }
    }

    get previousNode() {
        return this.tree.getNodeByEl(this.ui.wrap.previousSibling);
    }

    get nextNode() {
        return this.tree.getNodeByEl(this.ui.wrap.nextSibling);
    }

    get childNodes() {
        if (this.type !== 'folder') {
            return [];
        }
        return Array.from(this.ui.ctNode?.children).map((el) => {
            return this.tree.getNodeByEl(el);
        });
    }

    toObject() {
        if (this.type === 'folder') {
            return {
                ...this.attributes,
                children: this.childNodes.map((child) => child.toObject()),
            }
        }
        return this.attributes;
    }

    async expand() {
        if (this.ui.details) {
            if (!this.hasLoadedChildren) {
                await this.loadChildren();
            }
            this.ui.details.open = true;
        }
        return this;
    }

    async expandAll() {
        await this.expand();
        await Promise.all(this.childNodes.map((child) => child.expandAll()))
    }

    collapse() {
        if (this.ui.details) {
            this.ui.details.open = false;
        }
    }

    collapseAll() {
        if (!this.isRoot || this.tree.config.showRootNode) {
            this.collapse();
        }
        this.childNodes.map((child) => child.collapseAll());
    }

    select() {
        if (this.ui.checkbox && !(this.attributes.disabled ?? false)) {
            this.ui.checkbox.indeterminate = false;
            if (this.ui.checkbox.checked === false) {
                this.ui.checkbox.checked = true;
                this.ui.checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    }

    deselect() {
        if (this.ui.checkbox && !(this.attributes.disabled ?? false)) {
            this.ui.checkbox.indeterminate = false;
            if (this.ui.checkbox.checked === true) {
                this.ui.checkbox.checked = false;
                this.ui.checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    }

    remove() {
        this.ui.wrap.remove();
    }

    appendChild(node) {
        if (this.type !== 'folder') {
            throw new Error('Cannot add child to leaf node');
        }
        this.ui.ctNode.append(node.ui.wrap);
    }

    prependChild(node) {
        if (this.type !== 'folder') {
            throw new Error('Cannot add child to leaf node');
        }
        this.ui.ctNode.prepend(node.ui.wrap);
    }

    removeChild(node) {
        if (this.type !== 'folder') {
            throw new Error('Cannot remove child from leaf node');
        }
        if (this.ui.ctNode.contains(node.ui.wrap)) {
            node.ui.wrap.remove();
        }
    }

    removeAllChildren() {
        this.ui.ctNode.replaceChildren();
    }

    sortChildren() {
        Array.from(this.ui.ctNode.children)
            .sort((a, b) => a.dataset.text > b.dataset.text ? 1 : -1)
            .forEach(node => this.ui.ctNode.appendChild(node));
    }

    selectChildren() {
        this.ui.ctNode?.querySelectorAll('input[type=checkbox]').forEach((el) => {
            el.checked = true;
            el.indeterminate = false;
        });
    }

    deselectChildren() {
        this.ui.ctNode?.querySelectorAll('input[type=checkbox]').forEach((el) => {
            el.checked = false;
            el.indeterminate = false;
        });
    }

    get hasLoadedChildren() {
        return this.ui.wrap.hasAttribute('data-loaded');
    }

    set hasLoadedChildren(value) {
        if (value) {
            this.ui.wrap.setAttribute('data-loaded', '');
        } else {
            this.ui.wrap.removeAttribute('data-loaded');
        }
    }

    async loadChildren() {
        if (!this.tree.lazyloadOpts.dataUrl) {
            return;
        }

        const timeoutID = setTimeout(() => {
            this.ui.iconNode.classList.add('loading');
        }, LOADING_TIMEOUT);

        try {
            const params = new URLSearchParams({
                [this.tree.lazyloadOpts.nodeParameter]: this.id,
            });

            if (typeof this.tree.lazyloadOpts.onBeforeLoad === 'function') {
                await this.tree.lazyloadOpts.onBeforeLoad(this, params);
            }

            const children = await mahoFetch(this.tree.lazyloadOpts.dataUrl, {
                method: 'POST',
                body: params,
            });

            for (const child of this.childNodes) {
                if (child.isNew && !children.some((node) => node.id === child.id)) {
                    child.isNew = false;
                } else {
                    child.remove();
                }
            }

            for (const child of children) {
                this.appendChild(new MahoTreeNode(this.tree, child))
            }
            this.hasLoadedChildren = true;

        } catch (error) {
            console.error('Error loading children:', error)
            if (typeof this.tree.lazyloadOpts.onLoadException === 'function') {
                this.tree.lazyloadOpts.onLoadException(this, error);
            }
        }

        clearTimeout(timeoutID)
        this.ui.iconNode.classList.remove('loading');
    }
}

/**
 * MahoTreeNestedFolderPlugin - a Sortable.js plugin for dropping nodes onto folders
 */
class MahoTreeNestedFolderPlugin
{
    static pluginName = 'mahoTreeNestedFolder';
    static mounted = false;
    static state = {};

    constructor(sortable, el, options) {
        this.dragEnter = this.dragEnter.bind(this);
        this.defaults = {
	    dropClass: 'drop'
	};
    }

    static mount() {
        if (MahoTreeNestedFolderPlugin.mounted === false) {
            MahoTreeNestedFolderPlugin.mounted = true;
            Sortable.mount(MahoTreeNestedFolderPlugin);
        }
    }

    dragStart({ dragEl, cancel, originalEvent }) {
        const dragNode = MahoTree.nodeDataMap.get(dragEl);
        if (!dragNode.allowDrag) {
            originalEvent.preventDefault();
            return cancel();
        }
        MahoTreeNestedFolderPlugin.state = { dragNode, dropNode: null, hoverNode: null };
        window.addEventListener('dragenter', this.dragEnter);
    }

    dragEnter(event) {
        const state = MahoTreeNestedFolderPlugin.state;
        if (state.dropNode && !state.dropNode.ui.label.contains(event.target)) {
            state.dropNode.ui.wrap.classList.remove('drop');
            state.dropNode = null;
        }
    }

    dragOver({ target, originalEvent, fromSortable, cancel }) {
        if (target.tagName === 'UL' && target.childNodes.length === 0) {
            return cancel();
        }

        const state = MahoTreeNestedFolderPlugin.state;

        // The node we are currently hovering over, potential to become new dropNode
        state.hoverNode = MahoTree.nodeDataMap.get(originalEvent.target.closest('li'));
        if (!state.hoverNode || state.dropNode === state.hoverNode) {
            return;
        }

        if (target.tagName === 'UL' && !state.hoverNode.allowDrop) {
            cancel();
        }

        if (target.tagName === 'LI' && !state.hoverNode.parentNode?.allowDrop) {
            cancel();
        }

        state.dropNode?.ui.wrap.classList.remove(this.options.dropClass);
        state.dropNode = null;

        if (!originalEvent.target.closest('.label')) {
            return;
        }
        if (state.hoverNode.type !== 'folder' || !state.hoverNode.allowDrop) {
            return;
        }
        if (state.hoverNode.contains(state.dragNode) || state.dragNode.contains(state.hoverNode)) {
            return;
        }
        if (fromSortable.options.group.name !== Sortable.get(state.hoverNode.ui.ctNode).options.group.name) {
            return;
        }

        state.dropNode = state.hoverNode;
        state.dropNode.ui.wrap.classList.add(this.options.dropClass);
    }

    drop({ activeSortable, putSortable }) {
        const state = MahoTreeNestedFolderPlugin.state;
        if (!state.dropNode) {
            return;
        }

        if (state.dropNode.childNodes[0] === state.dragNode) {
            return false;
        }

        // Prepare the dragged node to be inserted
        state.dragNode.isNew = true;
        state.dropNode.ui.details.open = true;

        // Insert into new sortable and animate
        const animateSortables = new Set([ putSortable || this.sortable, activeSortable ]);

        animateSortables.forEach((sortable) => sortable.captureAnimationState());
        state.dropNode.prependChild(state.dragNode);
        animateSortables.forEach((sortable) => sortable.animateAll());

        return false;
    }

    nulling() {
        const state = MahoTreeNestedFolderPlugin.state;
        state.dropNode?.ui.wrap.classList.remove(this.options.dropClass);
        window.removeEventListener('dragenter', this.dragEnter);
        MahoTreeNestedFolderPlugin.state = {};
    }
}

/**
 * MahoTreeDropZonePlugin - a Sortable.js plugin for dropping nodes into a drop zone
 */
class MahoTreeDropZonePlugin
{
    static pluginName = 'mahoTreeDropZone';
    static mounted = false;
    static state = {};

    constructor(sortable, el, options) {
        if (typeof options.mahoTreeDropZone === 'object' && options.mahoTreeDropZone !== null) {
            this.dropZone = options.mahoTreeDropZone.dropZone;
            this.dragEnter = this.dragEnter.bind(this);
            if (typeof options.mahoTreeDropZone.onHover === 'function') {
                this.onHover = options.mahoTreeDropZone.onHover;
            }
            if (typeof options.mahoTreeDropZone.onDrop === 'function') {
                this.onDrop = options.mahoTreeDropZone.onDrop;
            }
        }
    }

    static mount() {
        if (MahoTreeDropZonePlugin.mounted === false) {
            MahoTreeDropZonePlugin.mounted = true;
            MahoTreeDropZonePlugin.dropMsg = document.createElement('div');
            Sortable.mount(MahoTreeDropZonePlugin);
        }
    }

    dragStart({ dragEl, cancel, originalEvent }) {
        if (!this.dropZone || this.dropZone.contains(dragEl)) {
            return;
        }
        const dragNode = MahoTree.nodeDataMap.get(dragEl);
        MahoTreeDropZonePlugin.state = { dragNode, hovering: false };
        window.addEventListener('dragenter', this.dragEnter);
    }

    dragEnter(event) {
        const state = MahoTreeDropZonePlugin.state;
        const dropMsg = MahoTreeDropZonePlugin.dropMsg;
        if (this.dropZone.contains(event.target) === state.hovering) {
            return;
        }
        state.hovering = !state.hovering;
        this.dropZone.classList.toggle('hovering', state.hovering)

        if (state.hovering) {
            const { icon, message } = this.onHover({ dragNode: state.dragNode });
            dropMsg.className = `drop-message ${icon}`;
            dropMsg.textContent = message;
            this.dropZone.prepend(dropMsg);
        } else {
            dropMsg.remove();
        }
    }

    drop({ activeSortable, putSortable }) {
        const state = MahoTreeDropZonePlugin.state;
        if (state.hovering) {
            const moveFn = this.onDrop({ ...arguments, dragNode: state.dragNode });
            if (typeof moveFn === 'function') {
                const animateSortables = new Set([ putSortable || this.sortable, activeSortable ]);

                animateSortables.forEach((sortable) => sortable.captureAnimationState());
                moveFn();
                animateSortables.forEach((sortable) => sortable.animateAll());

                return false;
            }
        }
    }

    onHover({ dragNode }) {
        return { icon: '', message: 'Drop' };
    }

    onDrop({ dragNode }) {
    }

    nulling() {
        this.dropZone.classList.remove('hovering');
        window.removeEventListener('dragenter', this.dragEnter);
        MahoTreeDropZonePlugin.dropMsg.remove();
        MahoTreeDropZonePlugin.state = {};
    }
}

// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

import { blockRegistry } from './blocks.js';

const STORAGE_KEY = 'tiptap-page-builder';
const CONTAINER_NODES = ['mahoColumn', 'mahoBentoCell', 'mahoColumns', 'mahoBentoGrid', 'detailsContent'];

const readState = () => {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}') ?? {};
    } catch {
        return {};
    }
};

const writeState = (state) => {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch {
        // The browser can refuse storage. The panes then open with the defaults.
    }
};

/** Depth of the deepest text block at a resolved position, or -1. */
const textblockDepth = ($pos) => {
    for (let depth = $pos.depth; depth > 0; depth -= 1) {
        if ($pos.node(depth).isTextblock) {
            return depth;
        }
    }
    return -1;
};

/**
 * The fullscreen editor as a page builder: a Blocks pane on the left and, for
 * content that the storefront can show, a Preview pane on the right.
 */
export class MahoPageBuilder {
    constructor(setup) {
        this.setup = setup;
        this.editor = setup.editor;
        this.wrapper = setup.wrapper;
        this.toolbar = setup.wrapper.querySelector('.tiptap-toolbar');
        this.state = { blocks: true, preview: true, ...readState() };
        this.preview = null;
        this.drag = null;
        this.dropTarget = null;
        this.isOpen = false;

        this.toggles = this.createToggles();
        this.blocksPane = this.createBlocksPane();
        this.line = document.createElement('div');
        this.line.className = 'tiptap-drop-line';
        this.line.hidden = true;

        this.resizeObserver = new ResizeObserver(() => this.layout());
        this.onDrag = this.handleDrag.bind(this);
        this.onKeydown = this.handleKeydown.bind(this);
    }

    get hasPreview() {
        return Boolean(this.setup.config.preview?.url);
    }

    open() {
        if (this.isOpen) {
            return;
        }
        this.isOpen = true;
        this.wrapper.classList.add('tiptap-page-builder');
        this.toggles.hidden = false;
        this.wrapper.append(this.blocksPane, this.line);
        for (const type of ['dragenter', 'dragover', 'drop']) {
            this.wrapper.addEventListener(type, this.onDrag, true);
        }
        this.wrapper.addEventListener('keydown', this.onKeydown);
        this.resizeObserver.observe(this.toolbar);
        this.apply();
    }

    close() {
        if (!this.isOpen) {
            return;
        }
        this.isOpen = false;
        this.endDrag();
        this.preview?.hide();
        this.resizeObserver.disconnect();
        this.wrapper.removeEventListener('keydown', this.onKeydown);
        for (const type of ['dragenter', 'dragover', 'drop']) {
            this.wrapper.removeEventListener(type, this.onDrag, true);
        }
        this.blocksPane.remove();
        this.line.remove();
        this.toggles.hidden = true;
        this.wrapper.classList.remove('tiptap-page-builder', 'has-blocks', 'has-preview');
    }

    destroy() {
        this.close();
        this.preview?.destroy();
        this.preview = null;
        this.toggles.remove();
    }

    /** Show the panes that the state asks for. */
    async apply() {
        this.wrapper.classList.toggle('has-blocks', this.state.blocks);
        this.setPressed(this.blocksToggle, this.state.blocks);
        if (this.previewToggle) {
            this.setPressed(this.previewToggle, this.state.preview);
        }

        const showPreview = this.hasPreview && this.state.preview;
        this.wrapper.classList.toggle('has-preview', showPreview);
        if (showPreview) {
            if (!this.preview) {
                const { MahoPreviewPane } = await import('./preview-pane.js');
                this.preview ??= new MahoPreviewPane(this);
            }
            if (this.isOpen && this.state.preview) {
                this.preview.show();
            }
        } else {
            this.preview?.hide();
        }
        this.layout();
    }

    layout() {
        this.wrapper.style.setProperty('--tt-toolbar-height', `${this.toolbar.offsetHeight}px`);
        this.preview?.layout();
    }

    toggle(pane) {
        this.state[pane] = !this.state[pane];
        writeState(this.state);
        this.apply();
    }

    saveState(changes) {
        Object.assign(this.state, changes);
        writeState(this.state);
    }

    setPressed(button, pressed) {
        button.classList.toggle('is-active', pressed);
        button.setAttribute('aria-pressed', pressed ? 'true' : 'false');
    }

    createToggles() {
        const holder = this.setup.createToolbar([
            { type: 'button', title: 'Blocks', icon: 'blocks-pane', onClick: () => this.toggle('blocks') },
            { type: 'button', title: 'Preview', icon: 'preview-pane', onClick: () => this.toggle('preview'), enabled: this.hasPreview },
        ]);
        const group = holder.firstElementChild;
        group.classList.add('tiptap-page-builder-toggles');
        group.hidden = true;
        [this.blocksToggle, this.previewToggle = null] = group.querySelectorAll('button');

        const fullscreen = this.toolbar.querySelector('button[data-command="toggleFullscreen"]')?.closest('.toolbar-group');
        if (fullscreen) {
            fullscreen.before(group);
        } else {
            this.toolbar.append(group);
        }
        return group;
    }

    createBlocksPane() {
        const pane = document.createElement('aside');
        pane.className = 'tiptap-page-builder-pane tiptap-blocks-pane';
        pane.setAttribute('aria-label', this.setup.translate('Blocks'));

        const search = document.createElement('label');
        search.className = 'tiptap-blocks-search';
        search.innerHTML = this.setup.getIcon('search');
        const input = document.createElement('input');
        input.type = 'search';
        input.placeholder = this.setup.translate('Search blocks');
        input.setAttribute('aria-label', input.placeholder);
        input.addEventListener('input', () => this.filterBlocks(input.value));
        search.append(input);
        pane.append(search);

        for (const section of blockRegistry(this.setup)) {
            const group = document.createElement('section');
            group.className = 'tiptap-blocks-section';
            const heading = document.createElement('h3');
            heading.textContent = this.setup.translate(section.title);
            const grid = document.createElement('div');
            grid.className = 'tiptap-blocks-grid';
            for (const item of section.items) {
                grid.append(this.createTile(item));
            }
            group.append(heading, grid);
            pane.append(group);
        }
        return pane;
    }

    /**
     * A tile is a div with the button role. Firefox does not start a drag from a
     * button element.
     */
    createTile(item) {
        const label = this.setup.translate(item.title);
        const tile = document.createElement('div');
        tile.className = 'tiptap-blocks-tile';
        tile.setAttribute('role', 'button');
        tile.tabIndex = 0;
        tile.draggable = true;
        tile.title = label;
        tile.dataset.search = `${label} ${item.title}`.toLowerCase();
        tile.innerHTML = this.setup.getIcon(item.icon);
        const text = document.createElement('span');
        text.textContent = label;
        tile.append(text);

        tile.addEventListener('click', () => this.run(item));
        tile.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                this.run(item);
            }
        });
        tile.addEventListener('dragstart', (event) => {
            this.drag = item;
            event.dataTransfer.effectAllowed = 'copy';
            // Firefox starts a drag only with data
            event.dataTransfer.setData('text/plain', '');
        });
        tile.addEventListener('dragend', () => this.endDrag());
        return tile;
    }

    filterBlocks(query) {
        const words = query.toLowerCase().split(/\s+/).filter(Boolean);
        for (const section of this.blocksPane.querySelectorAll('.tiptap-blocks-section')) {
            let visible = 0;
            for (const tile of section.querySelectorAll('.tiptap-blocks-tile')) {
                const match = words.every((word) => tile.dataset.search.includes(word));
                tile.hidden = !match;
                visible += match ? 1 : 0;
            }
            section.hidden = visible === 0;
        }
    }

    /**
     * Run the command of a block at the current selection. The command runs outside a
     * chain: focus() is deferred, and a chain would dispatch before the caret moves.
     * Each call reads `editor.commands` again, because the getter binds the current state.
     */
    run(item) {
        this.editor.commands.focus();
        this.editor.commands[item.command]?.(...(item.args ?? []));
        this.editor.commands.scrollIntoView();
    }

    handleKeydown(event) {
        if (event.key !== 'Escape' || event.defaultPrevented || document.querySelector('dialog[open]')) {
            return;
        }
        if (event.target instanceof HTMLInputElement && event.target.type === 'search' && event.target.value !== '') {
            return;
        }
        if (this.editor.view.dom.contains(event.target)) {
            return;
        }
        event.preventDefault();
        this.editor.commands.toggleFullscreen();
    }

    /**
     * Only a drag that starts on a tile is handled here. The drop is claimed on the
     * whole page builder, so a tile released outside the content never pastes its label.
     */
    handleDrag(event) {
        if (!this.drag) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();

        const position = this.dropPosition(event);
        if (event.type === 'drop') {
            const item = this.drag;
            this.endDrag();
            if (position !== null) {
                this.editor.commands.setTextSelection(this.dropSelection(position, event.clientY));
                this.run(item);
            }
            return;
        }
        event.dataTransfer.dropEffect = 'copy';
        if (position === null) {
            this.hideLine();
        } else {
            this.showLine(position);
        }
    }

    endDrag() {
        this.drag = null;
        this.hideLine();
    }

    dropPosition(event) {
        const content = this.wrapper.querySelector('.tiptap-content');
        const rect = content.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) {
            return null;
        }
        return this.editor.view.posAtCoords({ left: event.clientX, top: event.clientY })?.pos ?? null;
    }

    /**
     * The caret for the drop: the start of the text block under the pointer on its
     * upper half, its end on the lower half. A block command then inserts before or
     * after that block. Selection.near is not used: it can return a node selection
     * on a cell, and the command would replace the whole cell.
     */
    dropSelection(position, clientY) {
        const { doc } = this.editor.state;
        const $pos = doc.resolve(Math.max(0, Math.min(position, doc.content.size)));
        const depth = textblockDepth($pos);
        if (depth === -1) {
            return this.nearestTextPosition(doc, $pos.pos);
        }
        const dom = this.editor.view.nodeDOM($pos.before(depth));
        if (!(dom instanceof HTMLElement)) {
            return $pos.start(depth);
        }
        const rect = dom.getBoundingClientRect();
        return clientY < rect.top + rect.height / 2 ? $pos.start(depth) : $pos.end(depth);
    }

    nearestTextPosition(doc, position) {
        const limit = 200;
        for (let offset = 0; offset <= limit; offset += 1) {
            for (const candidate of [position - offset, position + offset]) {
                if (candidate >= 0 && candidate <= doc.content.size && doc.resolve(candidate).parent.isTextblock) {
                    return candidate;
                }
            }
        }
        return position;
    }

    /** The column, cell or grid that receives the block, so the line can match its width. */
    dropContainer(position) {
        const $pos = this.editor.state.doc.resolve(position);
        for (let depth = $pos.depth; depth > 0; depth -= 1) {
            if (CONTAINER_NODES.includes($pos.node(depth).type.name)) {
                const dom = this.editor.view.nodeDOM($pos.before(depth));
                return dom instanceof HTMLElement ? dom : null;
            }
        }
        return null;
    }

    showLine(position) {
        const content = this.wrapper.querySelector('.tiptap-content').getBoundingClientRect();
        const container = this.dropContainer(position);
        const box = container?.getBoundingClientRect();
        const left = Math.max(content.left + 4, box?.left ?? content.left + 8);
        const right = Math.min(content.right - 4, box?.right ?? content.right - 8);
        const top = this.editor.view.coordsAtPos(position).top;

        this.line.style.top = `${Math.round(top)}px`;
        this.line.style.left = `${Math.round(left)}px`;
        this.line.style.width = `${Math.round(right - left)}px`;
        this.line.hidden = false;
        this.highlight(container);
    }

    hideLine() {
        this.line.hidden = true;
        this.highlight(null);
    }

    highlight(element) {
        if (this.dropTarget === element) {
            return;
        }
        this.dropTarget?.classList.remove('tiptap-drop-target');
        this.dropTarget = element;
        this.dropTarget?.classList.add('tiptap-drop-target');
    }
}

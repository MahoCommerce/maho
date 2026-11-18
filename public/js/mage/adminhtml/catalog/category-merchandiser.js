// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

/**
 * The tile view of the products of a category, where the merchant sets the manual order.
 *
 * The moves stay in the page. The view changes the map of product IDs and positions that the
 * "Category Products" grid holds, so Save Category sends them with the existing hidden field.
 * A position is the rank of the product in the whole list, from 1. A pinned product keeps its slot.
 */
class CategoryMerchandiser {
    static STORAGE_KEY = 'category_products_view';
    static PAGE_SIZE = 100;
    static PIN_ICON = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/></svg>';

    /**
     * @param {Object} config - the "merchandiser" part of window.productsInfo
     * @param {Object<string, string|number>} products - position of each product ID, shared with the grid
     * @param {Object} options
     * @param {HTMLInputElement} options.pinnedEl - the hidden field that sends the pinned product IDs
     * @param {Function} options.onChange - called after the map of products changes
     * @param {Function} options.onShowGrid - called when the merchant goes back to the grid
     */
    constructor(config, products, { pinnedEl, onChange, onShowGrid }) {
        this.config = config;
        this.labels = config.labels;
        this.products = products;
        this.pinnedEl = pinnedEl;
        this.onChange = onChange;
        this.onShowGrid = onShowGrid;

        this.ui = {
            gridView: document.getElementById('category_products_grid_view'),
            tilesView: document.getElementById('category_products_tiles_view'),
            gridButton: document.getElementById('category_products_view_grid'),
            tilesButton: document.getElementById('category_products_view_tiles'),
            notes: document.getElementById('merchandiser_notes'),
            search: document.getElementById('merchandiser_search'),
            outOfStock: document.getElementById('merchandiser_out_of_stock'),
            addBox: document.getElementById('merchandiser_add_box'),
            add: document.getElementById('merchandiser_add'),
            addResults: document.getElementById('merchandiser_add_results'),
            reset: document.getElementById('merchandiser_reset'),
            bulk: document.getElementById('merchandiser_bulk'),
            selected: document.getElementById('merchandiser_selected'),
            position: document.getElementById('merchandiser_position'),
            list: document.getElementById('merchandiser_tiles'),
            empty: document.getElementById('merchandiser_empty'),
            sentinel: document.getElementById('merchandiser_sentinel'),
            statistics: document.getElementById('merchandiser_statistics'),
        };
        if (!this.ui.tilesView) {
            return;
        }

        this.isManual = config.isManual;
        this.isEditable = config.isManual && !config.isReadonly;
        this.canChangeProducts = this.isEditable && !config.isDynamic;
        this.initial = { products: { ...products }, pinned: [...config.pinned] };
        this.tiles = new Map();
        this.view = [];
        this.total = 0;
        this.selected = new Set();
        this.isDirty = false;
        this.isLoading = false;
        this.ranks = new Map();
        this.pinned = new Map();
        this.requestId = 0;

        this.order = [];
        this.rebuildOrder();
        this.restorePins();

        this.bind();
        this.renderNotes();
        this.renderStatistics();
        this.setView(localStorage.getItem(CategoryMerchandiser.STORAGE_KEY) === 'tiles' ? 'tiles' : 'grid');
    }

    get filters() {
        return {
            search: this.ui.search.value.trim(),
            outOfStock: this.ui.outOfStock.checked,
        };
    }

    get isFiltered() {
        const { search, outOfStock } = this.filters;
        return search !== '' || outOfStock;
    }

    bind() {
        this.ui.gridButton.addEventListener('click', () => this.setView('grid'));
        this.ui.tilesButton.addEventListener('click', () => this.setView('tiles'));

        let timer = null;
        this.ui.search.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => this.applyFilters(), 300);
        });
        this.ui.outOfStock.addEventListener('change', () => this.applyFilters());

        this.ui.reset.addEventListener('click', () => this.reset());
        this.ui.reset.hidden = !this.isEditable;

        this.ui.bulk.hidden = !this.isEditable;
        this.ui.bulk.querySelectorAll('button[data-action]').forEach((button) => {
            button.addEventListener('click', () => this.runBulk(button.dataset.action));
        });

        this.ui.addBox.hidden = !this.canChangeProducts;
        let addTimer = null;
        this.ui.add.addEventListener('input', () => {
            clearTimeout(addTimer);
            addTimer = setTimeout(() => this.searchProducts(), 300);
        });
        this.ui.addResults.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-id]');
            if (button) {
                this.addProduct(Number(button.dataset.id));
            }
        });

        this.observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                this.loadMore();
            }
        }, { rootMargin: '400px' });
        this.observer.observe(this.ui.sentinel);

        if (this.isEditable && typeof Sortable !== 'undefined') {
            this.sortable = new Sortable(this.ui.list, {
                animation: 150,
                draggable: '.merchandiser-tile',
                filter: '.is-pinned, input, button, label',
                preventOnFilter: false,
                ghostClass: 'is-dragging',
                onEnd: () => this.onDragEnd(),
            });
        }
    }

    setView(name) {
        const isTiles = name === 'tiles';
        localStorage.setItem(CategoryMerchandiser.STORAGE_KEY, name);
        this.ui.tilesView.hidden = !isTiles;
        this.ui.gridView.hidden = isTiles;
        this.ui.tilesButton.setAttribute('aria-pressed', String(isTiles));
        this.ui.gridButton.setAttribute('aria-pressed', String(!isTiles));
        if (isTiles) {
            this.refresh();
        } else if (this.isDirty) {
            this.onShowGrid?.();
        }
    }

    /**
     * Retrieve the IDs of the products in the order of their positions.
     * A product that the grid added has no position: it goes to the top or the bottom, as the category says.
     */
    rebuildOrder() {
        if (!this.isManual) {
            return;
        }
        const ids = Object.keys(this.products).map(Number);
        const position = (id) => Number(this.products[id]) || 0;
        const isNew = (id) => !(id in this.initial.products) && position(id) === 0;
        const entering = ids.filter(isNew).sort((a, b) => a - b);
        const others = ids.filter((id) => !isNew(id)).sort((a, b) => position(a) - position(b) || a - b);

        this.order = this.layout(this.config.placement === 'bottom' ? [...others, ...entering] : [...entering, ...others]);
        this.indexRanks();
        if (entering.length > 0) {
            this.commit();
        }
    }

    /**
     * Pin the products that the category has pinned. Another sort keeps the pins unchanged: the form sends them back.
     */
    restorePins() {
        this.pinned.clear();
        this.initial.pinned.forEach((id) => {
            if (!this.isManual) {
                this.pinned.set(id, 0);
            } else if (this.ranks.has(id)) {
                this.pinned.set(id, this.ranks.get(id) - 1);
            }
        });
        this.writePins();
    }

    indexRanks() {
        this.ranks = new Map(this.order.map((id, index) => [id, index + 1]));
    }

    /**
     * Put each pinned product in its slot and the other products in the free slots, in their order.
     *
     * @param {number[]} sequence
     * @returns {number[]}
     */
    layout(sequence) {
        const count = sequence.length;
        const fixed = new Map();
        const inSequence = new Set(sequence);
        const pinned = [...this.pinned].filter(([id]) => inSequence.has(id)).sort((a, b) => a[1] - b[1] || a[0] - b[0]);
        for (const [id, wanted] of pinned) {
            let slot = Math.min(wanted, count - 1);
            while (fixed.has(slot)) {
                slot++;
            }
            if (slot >= count) {
                slot = 0;
                while (fixed.has(slot)) {
                    slot++;
                }
            }
            fixed.set(slot, id);
            this.pinned.set(id, slot);
        }
        const fixedIds = new Set(fixed.values());
        const free = sequence.filter((id) => !fixedIds.has(id));
        return sequence.map((_, slot) => fixed.get(slot) ?? free.shift());
    }

    /**
     * Write the rank of each product into the map of the grid and tell the form.
     */
    commit() {
        this.order.forEach((id, index) => {
            this.products[id] = index + 1;
        });
        this.indexRanks();
        this.writePins();
        this.isDirty = true;
        this.onChange();
        this.renderNotes();
    }

    writePins() {
        this.pinnedEl.disabled = false;
        this.pinnedEl.value = [...this.pinned.keys()].join(',');
    }

    async applyFilters() {
        if (this.sortable) {
            this.sortable.option('disabled', this.isFiltered);
        }
        await this.refresh();
    }

    /**
     * Load the first page of tiles again.
     */
    async refresh() {
        if (this.ui.tilesView.hidden) {
            return;
        }
        this.rebuildOrder();
        this.view = [];
        this.total = 0;
        this.selected.clear();
        this.requestId++;
        this.isLoading = false;
        this.render();
        await this.loadMore();
    }

    isServerPaged() {
        return !this.isManual || this.isFiltered;
    }

    /**
     * Load the next page of tiles. A list that the merchant orders is paged here, because the moves are not saved,
     * and the server only reads the details of each product. Any other list is paged by the server.
     */
    async loadMore() {
        if (this.isLoading || this.ui.tilesView.hidden) {
            return;
        }
        const total = this.isServerPaged() ? this.total : this.order.length;
        if (this.view.length > 0 && this.view.length >= total) {
            return;
        }
        if (!this.isServerPaged() && this.order.length === 0) {
            this.render();
            return;
        }

        this.isLoading = true;
        const requestId = ++this.requestId;
        try {
            const body = new URLSearchParams();
            let ids;
            if (this.isServerPaged()) {
                if (this.order.length > 0) {
                    body.set('ids', this.order.join(','));
                }
                body.set('offset', String(this.view.length));
                body.set('q', this.filters.search);
                body.set('out_of_stock', this.filters.outOfStock ? '1' : '');
                body.set('limit', String(CategoryMerchandiser.PAGE_SIZE));
            } else {
                ids = this.order.slice(this.view.length, this.view.length + CategoryMerchandiser.PAGE_SIZE);
                body.set('ids', ids.join(','));
            }
            const result = await mahoFetch(this.config.tilesUrl, { method: 'POST', body, loaderArea: false });
            if (requestId !== this.requestId) {
                return;
            }
            if (result.order) {
                this.order = result.order;
            }
            result.tiles.forEach((tile) => this.tiles.set(tile.id, tile));
            this.view.push(...(ids ?? result.tiles.map((tile) => tile.id)));
            this.total = this.isServerPaged() ? result.total : this.order.length;
            this.render();
        } catch (error) {
            setMessagesDiv(this.labels.error, 'error');
        } finally {
            if (requestId === this.requestId) {
                this.isLoading = false;
            }
        }
        if (this.view.length < (this.isServerPaged() ? this.total : this.order.length) && this.isSentinelVisible()) {
            this.loadMore();
        }
    }

    isSentinelVisible() {
        const rect = this.ui.sentinel.getBoundingClientRect();
        return rect.top < window.innerHeight + 400;
    }

    /**
     * Load the details of the products that no tile has shown yet.
     *
     * @param {number[]} ids
     */
    async ensureTiles(ids) {
        const missing = ids.filter((id) => !this.tiles.has(id));
        if (missing.length === 0) {
            return;
        }
        for (let start = 0; start < missing.length; start += CategoryMerchandiser.PAGE_SIZE) {
            const body = new URLSearchParams({ ids: missing.slice(start, start + CategoryMerchandiser.PAGE_SIZE).join(',') });
            const result = await mahoFetch(this.config.tilesUrl, { method: 'POST', body, loaderArea: false });
            result.tiles.forEach((tile) => this.tiles.set(tile.id, tile));
        }
    }

    rankOf(tile) {
        return this.config.isManual ? this.ranks.get(tile.id) : tile.rank;
    }

    render() {
        const list = this.ui.list;
        list.replaceChildren();
        const showBreaks = !this.isFiltered;
        const total = this.isServerPaged() ? this.total : this.order.length;

        this.view.forEach((id, index) => {
            const tile = this.tiles.get(id);
            if (!tile) {
                return;
            }
            list.append(this.renderTile(tile));
            const rank = this.rankOf(tile);
            const isLast = index === this.view.length - 1 && this.view.length >= total;
            if (showBreaks && rank % this.config.perPage === 0 && !isLast) {
                list.append(this.renderBreak(rank / this.config.perPage));
            }
        });

        this.ui.empty.hidden = this.view.length > 0 || this.isLoading;
        this.ui.empty.textContent = this.isFiltered ? this.labels.noResults : this.labels.empty;
        this.renderSelection();
    }

    renderBreak(page) {
        const item = document.createElement('li');
        item.className = 'merchandiser-break';
        item.textContent = this.labels.endOfPage.replace('%s', String(page));
        return item;
    }

    renderTile(tile) {
        const isPinned = this.pinned.has(tile.id);
        const item = document.createElement('li');
        item.className = 'merchandiser-tile';
        item.dataset.id = String(tile.id);
        item.classList.toggle('is-pinned', isPinned && this.isEditable);
        item.classList.toggle('is-out-of-stock', !tile.in_stock);

        if (this.isEditable) {
            const select = document.createElement('input');
            select.type = 'checkbox';
            select.className = 'd-checkbox d-checkbox-sm merchandiser-select';
            select.checked = this.selected.has(tile.id);
            select.setAttribute('aria-label', this.labels.select);
            select.addEventListener('change', () => {
                select.checked ? this.selected.add(tile.id) : this.selected.delete(tile.id);
                this.renderSelection();
            });
            item.append(select);
        }

        const image = document.createElement('img');
        image.src = tile.image;
        image.alt = '';
        image.loading = 'lazy';
        image.className = 'merchandiser-image';
        item.append(image);

        const name = document.createElement('strong');
        name.className = 'merchandiser-name';
        name.textContent = tile.name;
        const sku = document.createElement('span');
        sku.className = 'merchandiser-sku';
        sku.textContent = `${tile.sku} · ${tile.price}`;
        item.append(name, sku);

        const badges = document.createElement('span');
        badges.className = 'merchandiser-badges';
        badges.append(this.badge(tile.in_stock ? this.labels.inStock : this.labels.outOfStock, tile.in_stock ? 'd-badge-success' : 'd-badge-error'));
        if (!tile.is_enabled) {
            badges.append(this.badge(this.labels.disabled, 'd-badge-warning'));
        }
        if (!tile.is_visible && tile.visibility) {
            badges.append(this.badge(tile.visibility, 'd-badge-warning'));
        }
        if (tile.by_rule) {
            badges.append(this.badge(this.labels.byRule, 'd-badge-info'));
        }
        item.append(badges);

        const sales = document.createElement('div');
        sales.className = 'merchandiser-sales';
        [[this.labels.sold, String(tile.qty_sold)], [this.labels.revenue, tile.revenue]].forEach(([term, value]) => {
            const line = document.createElement('p');
            line.textContent = `${term}: `;
            const figure = document.createElement('strong');
            figure.textContent = value;
            line.append(figure);
            sales.append(line);
        });
        item.append(sales);

        item.append(this.renderFooter(tile, isPinned));
        return item;
    }

    badge(text, variant) {
        const badge = document.createElement('span');
        badge.className = `d-badge d-badge-sm ${variant}`;
        badge.textContent = text;
        return badge;
    }

    renderFooter(tile, isPinned) {
        const footer = document.createElement('div');
        footer.className = 'merchandiser-foot';

        if (this.isEditable) {
            const pin = document.createElement('button');
            pin.type = 'button';
            pin.className = 'merchandiser-pin d-btn-ghost d-btn-xs';
            pin.innerHTML = CategoryMerchandiser.PIN_ICON;
            pin.title = isPinned ? this.labels.unpin : this.labels.pin;
            pin.setAttribute('aria-label', pin.title);
            pin.setAttribute('aria-pressed', String(isPinned));
            pin.addEventListener('click', () => this.setPinned([tile.id], !isPinned));
            footer.append(pin);
        }

        const position = document.createElement('input');
        position.type = 'number';
        position.min = '1';
        position.className = 'd-input d-input-xs merchandiser-position';
        position.value = String(this.rankOf(tile));
        position.disabled = !this.isEditable || this.pinned.has(tile.id);
        position.setAttribute('aria-label', this.labels.position);
        position.addEventListener('change', () => {
            const slot = parseInt(position.value, 10);
            if (slot >= 1) {
                this.moveProducts([tile.id], slot);
            } else {
                position.value = String(this.rankOf(tile));
            }
        });
        footer.append(position);

        if (this.canChangeProducts) {
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'merchandiser-remove d-btn-ghost d-btn-xs';
            remove.textContent = this.labels.remove;
            remove.addEventListener('click', () => this.removeProduct(tile.id));
            footer.append(remove);
        }
        return footer;
    }

    renderSelection() {
        this.ui.selected.textContent = this.labels.selected.replace('%s', String(this.selected.size));
        this.ui.bulk.querySelectorAll('button[data-action]').forEach((button) => {
            button.disabled = this.selected.size === 0;
        });
        this.ui.position.disabled = this.selected.size === 0;
    }

    renderNotes() {
        const notes = [];
        if (this.config.readonlyMessage) {
            notes.push(this.config.readonlyMessage);
        }
        if (this.config.isManual && this.config.hasStore) {
            notes.push(this.labels.global);
        }
        if (this.config.outOfStockLast) {
            notes.push(this.labels.outOfStockLast);
        }
        if (this.isDirty) {
            notes.push(this.labels.unsaved);
        }
        this.ui.notes.replaceChildren(...notes.map((text) => {
            const note = document.createElement('p');
            note.textContent = text;
            return note;
        }));
        this.ui.notes.hidden = notes.length === 0;
    }

    renderStatistics() {
        const lines = [];
        lines.push(this.config.salesUpdatedAt ? this.labels.salesUpdated.replace('%s', this.config.salesUpdatedAt) : this.labels.noStatistics);
        if (this.config.viewsUpdatedAt !== null) {
            lines.push(this.config.viewsUpdatedAt ? this.labels.viewsUpdated.replace('%s', this.config.viewsUpdatedAt) : this.labels.noStatistics);
        }
        this.ui.statistics.replaceChildren(...lines.map((text) => {
            const line = document.createElement('p');
            line.textContent = text;
            return line;
        }));
    }

    /**
     * The list in the page changed by a drag: read the new order of the tiles that the page shows.
     */
    onDragEnd() {
        const shown = [...this.ui.list.querySelectorAll('.merchandiser-tile')].map((item) => Number(item.dataset.id));
        const shownSet = new Set(shown);
        this.order = this.layout([...shown, ...this.order.filter((id) => !shownSet.has(id))]);
        this.afterOrderChange();
    }

    /**
     * Show the first products of the new order again, and load the details of the ones that no tile has shown.
     */
    async afterOrderChange() {
        this.commit();
        if (this.isFiltered) {
            await this.refresh();
            return;
        }
        this.requestId++;
        this.isLoading = false;
        this.view = this.order.slice(0, Math.max(this.view.length, 1));
        try {
            await this.ensureTiles(this.view);
        } catch (error) {
            setMessagesDiv(this.labels.error, 'error');
        }
        this.render();
    }

    /**
     * Move products to the top, to the bottom or to a slot. A pinned product stays where it is.
     *
     * @param {number[]} ids
     * @param {'top'|'bottom'|number} target - a slot counts from 1
     */
    moveProducts(ids, target) {
        const moving = this.order.filter((id) => ids.includes(id) && !this.pinned.has(id));
        if (moving.length === 0) {
            this.render();
            return;
        }
        const rest = this.order.filter((id) => !moving.includes(id));
        const index = target === 'top' ? 0 : target === 'bottom' ? rest.length : Math.min(Math.max(target - 1, 0), rest.length);
        rest.splice(index, 0, ...moving);
        this.order = this.layout(rest);
        this.selected.clear();
        this.afterOrderChange();
    }

    setPinned(ids, isPinned) {
        ids.forEach((id) => {
            if (isPinned) {
                this.pinned.set(id, this.order.indexOf(id));
            } else {
                this.pinned.delete(id);
            }
        });
        this.selected.clear();
        this.commit();
        this.render();
    }

    runBulk(action) {
        const ids = [...this.selected];
        if (action === 'top' || action === 'bottom') {
            this.moveProducts(ids, action);
        } else if (action === 'position') {
            const slot = parseInt(this.ui.position.value, 10);
            if (slot >= 1) {
                this.moveProducts(ids, slot);
            }
        } else {
            this.setPinned(ids, action === 'pin');
        }
    }

    removeProduct(id) {
        delete this.products[id];
        this.pinned.delete(id);
        this.tiles.delete(id);
        this.selected.delete(id);
        this.order = this.layout(this.order.filter((orderId) => orderId !== id));
        this.view = this.view.filter((viewId) => viewId !== id);
        this.afterOrderChange();
    }

    async searchProducts() {
        const q = this.ui.add.value.trim();
        const results = this.ui.addResults;
        results.replaceChildren();
        results.hidden = true;
        if (q.length < 2) {
            return;
        }
        try {
            const body = new URLSearchParams({ q, ids: this.order.join(',') });
            const result = await mahoFetch(this.config.searchUrl, { method: 'POST', body, loaderArea: false });
            result.tiles.forEach((tile) => {
                this.tiles.set(tile.id, tile);
                const item = document.createElement('li');
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.id = String(tile.id);
                button.textContent = `${tile.name} (${tile.sku})`;
                item.append(button);
                results.append(item);
            });
            if (result.tiles.length === 0) {
                const item = document.createElement('li');
                item.textContent = this.labels.noResults;
                results.append(item);
            }
            results.hidden = false;
        } catch (error) {
            setMessagesDiv(this.labels.error, 'error');
        }
    }

    addProduct(id) {
        this.ui.addResults.replaceChildren();
        this.ui.addResults.hidden = true;
        this.ui.add.value = '';
        if (id in this.products) {
            return;
        }
        this.products[id] = 0;
        if (this.view.length >= this.order.length) {
            this.view.push(id);
        }
        const sequence = this.config.placement === 'bottom' ? [...this.order, id] : [id, ...this.order];
        this.order = this.layout(sequence);
        this.afterOrderChange();
    }

    /**
     * Discard the moves, the pins, the added products and the removed products that are not saved.
     */
    reset() {
        Object.keys(this.products).forEach((id) => delete this.products[id]);
        Object.assign(this.products, this.initial.products);
        this.pinned.clear();
        this.rebuildOrder();
        this.restorePins();
        this.isDirty = false;
        this.onChange();
        this.renderNotes();
        this.refresh();
    }
}

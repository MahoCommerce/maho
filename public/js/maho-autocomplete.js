/**
 * Maho
 *
 * @package     js
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class MahoAutocomplete {
    constructor(field, destinationElement, url, options = {}) {
        this.field = field;
        this.destinationElement = destinationElement;
        this.url = url;
        this.options = Object.assign({
            paramName: this.field.name,
            method: 'GET',
            minChars: 3,
            debounceDelay: 200
        }, options);

        this.debounceTimer = null;
        this.abortController = null;

        this.setupEventListeners();
    }

    setupEventListeners() {
        this.field.addEventListener('input', this.onInput.bind(this));
        document.addEventListener('click', this.onDocumentClick.bind(this));
    }

    onInput() {
        const value = this.field.value;

        clearTimeout(this.debounceTimer);
        this.abortController?.abort();

        if (value.length >= this.options.minChars) {
            this.debounceTimer = setTimeout(() => this.fetchSuggestions(value), this.options.debounceDelay);
        } else {
            this.hideSuggestions();
        }
    }

    onDocumentClick(event) {
        if (!this.destinationElement.contains(event.target) && event.target !== this.field) {
            this.hideSuggestions();
        }
    }

    fetchSuggestions(query) {
        this.abortController = new AbortController();

        const params = new URLSearchParams({ [this.options.paramName]: query });
        const url = `${this.url}?${params}`;

        mahoFetch(url, { method: this.options.method, signal: this.abortController.signal, loaderArea: false })
            .then(html => this.showSuggestions(html))
            .catch(() => {});
    }

    showSuggestions(html) {
        if (!html.trim()) {
            this.hideSuggestions();
            return;
        }

        this.destinationElement.innerHTML = html;
        this.positionSuggestions();
        this.destinationElement.style.display = 'block';
        this.addClickListenersToItems();
    }

    addClickListenersToItems() {
        const items = this.destinationElement.querySelectorAll('li');
        items.forEach(item => {
            item.addEventListener('click', () => this.selectItem(item));
        });
    }

    hideSuggestions() {
        this.destinationElement.style.display = 'none';
    }

    positionSuggestions() {
        const rect = this.field.getBoundingClientRect();
        this.destinationElement.style.position = 'absolute';
        this.destinationElement.style.left = `${rect.left}px`;
        this.destinationElement.style.top = `${rect.bottom}px`;
        this.destinationElement.style.width = `${rect.width}px`;
    }

    selectItem(item) {
        this.field.value = item.textContent.trim();
        this.hideSuggestions();
        if (this.options.onSelect) {
            this.options.onSelect(item);
        }
    }
}

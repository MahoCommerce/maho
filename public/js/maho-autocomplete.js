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
            minChars: 3
        }, options);

        this.setupEventListeners();
    }

    setupEventListeners() {
        this.field.addEventListener('input', this.onInput.bind(this));
        document.addEventListener('click', this.onDocumentClick.bind(this));
    }

    onInput() {
        const value = this.field.value;
        if (value.length >= this.options.minChars) {
            this.fetchSuggestions(value);
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
        const params = new URLSearchParams({ [this.options.paramName]: query });
        const url = `${this.url}?${params}`;

        fetch(url, { method: this.options.method })
            .then(response => response.text())
            .then(html => this.showSuggestions(html))
            .catch(error => console.error('Error fetching suggestions:', error));
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

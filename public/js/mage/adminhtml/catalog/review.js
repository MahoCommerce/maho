/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * Class to control the "Edit Review" page
 */
class ReviewEditForm {
    /**
     * @param {Object} config
     * @param {string} config.productEditUrl - URL to link to product edit page
     * @param {string} config.ratingItemsUrl - URL to POST rating changes
     */
    constructor(config) {
        this.config = {
            productEditUrl: null,
            ratingItemsUrl: null,
            ...config
        };
        this.bindEventListeners();
    }

    bindEventListeners() {
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('select_stores')?.addEventListener('change', this.updateRating.bind(this));
        });
    }

    toggleSaveButton(isEnabled = true) {
        const saveBtn = document.getElementById('save_button');
        if (saveBtn) {
            saveBtn.disabled = !isEnabled;
        }
    }

    toggleForm(isVisible = true) {
        document.getElementById('add_review_form')?.parentNode.classList.toggle('no-display', !isVisible);
        document.getElementById('reviewProductGrid')?.classList.toggle('no-display', !!isVisible);
        document.getElementById('save_button')?.classList.toggle('no-display', !isVisible);
        document.getElementById('reset_button')?.classList.toggle('no-display', !isVisible);
    }

    showForm() {
        this.toggleForm(true);
    }

    hideForm() {
        this.toggleForm(false);
    }

    async gridRowClick(data, event) {
        const url = event.target.closest('tr')?.title;
        const success = await this.loadProductData(url);
        if (success) {
            this.showForm();
        }
    }

    async loadProductData(url) {
        let success = false;
        try {
            // Backwards compatibility: old code would store URL in `this.productInfoUrl`
            // from `gridRowClick()` and then call this function with no parameters
            if (!(url ??= this.productInfoUrl)) {
                throw new Error('Product info URL not found');
            }

            const result = await mahoFetch(url, { method: 'POST' });

            if (this.config.productEditUrl) {
                const linkEl = document.createElement('a');
                linkEl.setAttribute('href', this.config.productEditUrl + `id/${result.id}`);
                linkEl.setAttribute('target', '_blank');
                linkEl.textContent = result.name;
                document.getElementById('product_name').replaceChildren(linkEl);
            } else {
                document.getElementById('product_name').textContent = result.name;
            }

            document.getElementById('product_id').value = result.id;
            success = true;

        } catch (error) {
            setMessagesDiv(`Error loading product: ${error.message}`, 'error');
        }
        return success;
    }

    async updateRating() {
        this.toggleSaveButton(false);

        try {
            if (!this.config.ratingItemsUrl) {
                throw new Error('Rating Items URL not found');
            }

            const body = new URLSearchParams({
                form_key: FORM_KEY,
                stores: [...document.getElementById('select_stores').selectedOptions].map((opt) => opt.value),
            });

            document.querySelectorAll('#rating_detail input[type=radio]:checked').forEach((el) => {
                body.append(el.name, el.value);
            });

            const html = await mahoFetch(this.config.ratingItemsUrl, { method: 'POST', body });
            updateElementHtmlAndExecuteScripts(document.getElementById('rating_detail'), html);

        } catch (error) {
            setMessagesDiv(`Error loading rating details: ${error.message}`, 'error');
        }

        this.toggleSaveButton(true);
    }
}

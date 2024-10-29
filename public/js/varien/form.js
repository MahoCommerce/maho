/**
 * Maho
 *
 * @category   Varien
 * @package    js
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class VarienForm {
    constructor(formId, firstFieldFocus) {
        this.form = document.getElementById(formId);
        if (!this.form) {
            return;
        }
        this.cache = new Map();
        this.currLoader = false;
        this.currDataIndex = false;
        this.validator = new Validation(this.form);
        this.elementFocus = this.elementOnFocus.bind(this);
        this.elementBlur = this.elementOnBlur.bind(this);
        this.childLoader = this.onChangeChildLoad.bind(this);
        this.highlightClass = 'highlight';
        this.extraChildParams = '';
        this.firstFieldFocus = firstFieldFocus || false;
        this.bindElements();
        if (this.firstFieldFocus) {
            try {
                const firstElement = this.form.elements[0];
                if (firstElement) {
                    firstElement.focus();
                }
            } catch(e) {
                console.error('Error focusing on first element:', e);
            }
        }
    }

    submit(url) {
        if (this.validator && this.validator.validate()) {
            this.form.submit();
        }
        return false;
    }

    bindElements() {
        const elements = this.form.elements;
        for (let element of elements) {
            if (element.id) {
                element.addEventListener('focus', this.elementFocus);
                element.addEventListener('blur', this.elementBlur);
            }
        }
    }

    elementOnFocus(event) {
        const element = event.target.closest('fieldset');
        if (element) {
            element.classList.add(this.highlightClass);
        }
    }

    elementOnBlur(event) {
        const element = event.target.closest('fieldset');
        if (element) {
            element.classList.remove(this.highlightClass);
        }
    }

    setElementsRelation(parent, child, dataUrl, first) {
        const parentElement = document.getElementById(parent);
        if (parentElement) {
            if (!this.cache.has(parentElement.id)) {
                this.cache.set(parentElement.id, {
                    child: child,
                    dataUrl: dataUrl,
                    data: new Map(),
                    first: first || false
                });
            }
            parentElement.addEventListener('change', this.childLoader);
        }
    }

    onChangeChildLoad(event) {
        const element = event.target;
        this.elementChildLoad(element);
    }

    elementChildLoad(element, callback) {
        this.callback = callback || false;
        if (element.value) {
            this.currLoader = element.id;
            this.currDataIndex = element.value;
            const cacheData = this.cache.get(element.id);
            if (cacheData.data.has(element.value)) {
                this.setDataToChild(cacheData.data.get(element.value));
            } else {
                fetch(cacheData.dataUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `parent=${encodeURIComponent(element.value)}`
                })
                    .then(response => response.json())
                    .then(data => this.reloadChildren(data))
                    .catch(error => console.error('Error:', error));
            }
        }
    }

    reloadChildren(data) {
        const cacheData = this.cache.get(this.currLoader);
        cacheData.data.set(this.currDataIndex, data);
        this.setDataToChild(data);
    }

    setDataToChild(data) {
        const cacheData = this.cache.get(this.currLoader);
        const child = document.getElementById(cacheData.child);
        if (child) {
            if (data.length) {
                let html = `<select name="${child.name}" id="${child.id}" class="${child.className}" title="${child.title}" ${this.extraChildParams}>`;
                if (cacheData.first) {
                    html += `<option value="">${cacheData.first}</option>`;
                }
                for (let item of data) {
                    if (item.value) {
                        html += `<option value="${item.value}"${(child.value && (child.value == item.value || child.value == item.label)) ? ' selected' : ''}>${item.label}</option>`;
                    }
                }
                html += '</select>';
                child.outerHTML = html;
            } else {
                child.outerHTML = `<input type="text" name="${child.name}" id="${child.id}" class="${child.className}" title="${child.title}" ${this.extraChildParams}>`;
            }
        }

        this.bindElements();
        if (this.callback) {
            this.callback();
        }
    }
}

class RegionUpdater {
    constructor(countryEl, regionTextEl, regionSelectEl, regions, disableAction, zipEl) {
        this.countryEl = document.getElementById(countryEl);
        this.regionTextEl = document.getElementById(regionTextEl);
        this.regionSelectEl = document.getElementById(regionSelectEl);
        this.zipEl = document.getElementById(zipEl);
        this.config = regions.config;
        delete regions.config;
        this.regions = regions;

        this.disableAction = (typeof disableAction === 'undefined') ? 'hide' : disableAction;
        this.zipOptions = (typeof zipOptions === 'undefined') ? false : zipOptions;

        if (this.regionSelectEl.options.length <= 1) {
            this.update();
        }

        this.countryEl.addEventListener('change', this.update.bind(this));
    }

    _checkRegionRequired() {
        if (typeof this.config === 'undefined') {
            return;
        }
        const regionRequired = this.config.regions_required.indexOf(this.countryEl.value) >= 0;
        const elements = [this.regionTextEl, this.regionSelectEl];

        elements.forEach(currentElement => {
            if (typeof Validation !== 'undefined') {
                Validation.reset(currentElement);
            }
            const label = document.querySelector(`label[for="${currentElement.id}"]`);
            if (label) {
                let wildCard = label.querySelector('em') || label.querySelector('span.required');
                if (!wildCard) {
                    label.insertAdjacentHTML('beforeend', ' <span class="required">*</span>');
                    wildCard = label.querySelector('span.required');
                }
                if (!this.config.show_all_regions) {
                    label.parentElement.style.display = regionRequired ? '' : 'none';
                }

                if (wildCard) {
                    wildCard.style.display = regionRequired ? '' : 'none';
                    label.classList.toggle('required', regionRequired);
                }
            }

            currentElement.classList.toggle('required-entry', regionRequired);
            if (currentElement.tagName.toLowerCase() === 'select') {
                currentElement.classList.toggle('validate-select', regionRequired);
            }
        });
    }

    update() {
        if (this.regions[this.countryEl.value]) {
            let def = this.regionSelectEl.dataset.defaultValue ?? this.regionSelectEl.getAttribute('defaultValue');
            if (this.regionTextEl) {
                if (!def) {
                    def = this.regionTextEl.value.toLowerCase();
                }
                this.regionTextEl.value = '';
            }

            this.regionSelectEl.innerHTML = '<option value="">Please select a region, state or province.</option>';
            for (let regionId in this.regions[this.countryEl.value]) {
                const region = this.regions[this.countryEl.value][regionId];
                const option = document.createElement('option');
                option.value = regionId;
                option.textContent = region.name;
                option.title = region.name;

                this.regionSelectEl.appendChild(option);

                if (regionId == def ||
                    (region.name && region.name.toLowerCase() == def) ||
                    (region.name && region.code.toLowerCase() == def)
                ) {
                    this.regionSelectEl.value = regionId;
                }
            }
            this.sortSelect();
            if (this.disableAction == 'hide') {
                if (this.regionTextEl) {
                    this.regionTextEl.style.display = 'none';
                }
                this.regionSelectEl.style.display = '';
            } else if (this.disableAction == 'disable') {
                if (this.regionTextEl) {
                    this.regionTextEl.disabled = true;
                }
                this.regionSelectEl.disabled = false;
            }
            this.setMarkDisplay(this.regionSelectEl, true);
        } else {
            this.regionSelectEl.innerHTML = '<option value="">Please select a region, state or province.</option>';
            this.sortSelect();
            if (this.disableAction == 'hide') {
                if (this.regionTextEl) {
                    this.regionTextEl.style.display = '';
                }
                this.regionSelectEl.style.display = 'none';
                if (typeof Validation !== 'undefined') {
                    Validation.reset(this.regionSelectEl);
                }
            } else if (this.disableAction == 'disable') {
                if (this.regionTextEl) {
                    this.regionTextEl.disabled = false;
                }
                this.regionSelectEl.disabled = true;
            } else if (this.disableAction == 'nullify') {
                this.regionSelectEl.innerHTML = '<option value="">Please select a region, state or province.</option>';
                this.regionSelectEl.value = '';
                this.lastCountryId = '';
            }
            this.setMarkDisplay(this.regionSelectEl, false);
        }

        this._checkRegionRequired();
        // Make Zip and its label required/optional
        const zipUpdater = new ZipUpdater(this.countryEl.value, this.zipEl);
        zipUpdater.update();
    }

    setMarkDisplay(elem, display) {
        const labelElement = elem.closest('div').querySelector('label > span.required') ||
            elem.closest('div').querySelector('label.required > em');
        if (labelElement) {
            const inputElement = labelElement.closest('label').nextElementSibling;
            if (display) {
                labelElement.style.display = '';
                if (inputElement) {
                    inputElement.classList.add('required-entry');
                }
            } else {
                labelElement.style.display = 'none';
                if (inputElement) {
                    inputElement.classList.remove('required-entry');
                }
            }
        }
    }

    sortSelect() {
        const elem = this.regionSelectEl;
        const tmpArray = Array.from(elem.options)
            .slice(1)
            .map(option => [option.text, option.value])
            .sort((a, b) => a[0].localeCompare(b[0]));

        const currentVal = elem.value;
        elem.innerHTML = '<option value="">Please select a region, state or province.</option>';
        tmpArray.forEach(([text, value]) => {
            const option = new Option(text, value);
            elem.add(option);
        });
        elem.value = currentVal;
    }
}

class ZipUpdater {
    constructor(country, zipElement) {
        this.country = country;
        this.zipElement = zipElement;
    }

    update() {
        // Country ISO 2-letter codes must be pre-defined
        if (typeof optionalZipCountries === 'undefined') {
            return false;
        }
        // Ajax-request and normal content load compatibility
        if (this.zipElement != undefined) {
            if (typeof Validation !== 'undefined') {
                Validation.reset(this.zipElement);
            }
            this._setPostcodeOptional();
        } else {
            window.addEventListener("load", this._setPostcodeOptional.bind(this));
        }
    }

    _setPostcodeOptional() {
        if (this.zipElement === undefined) {
            return false;
        }

        const label = document.querySelector(`label[for="${this.zipElement.id}"]`);
        let wildCard;
        if (label !== null) {
            wildCard = label.querySelector('em') || label.querySelector('span.required');
            if (!wildCard) {
                label.insertAdjacentHTML('beforeend', ' <span class="required">*</span>');
                wildCard = label.querySelector('span.required');
            }
        }

        // Make Zip and its label required/optional
        if (optionalZipCountries.indexOf(this.country) != -1) {
            if (label !== null) {
                label.classList.remove('required');
            }
            this.zipElement.classList.remove('required-entry');
            if (wildCard !== undefined) {
                wildCard.style.display = 'none';
            }
        } else {
            if (label !== null) {
                label.classList.add('required');
            }
            this.zipElement.classList.add('required-entry');
            if (wildCard !== undefined) {
                wildCard.style.display = '';
            }
        }
    }
}

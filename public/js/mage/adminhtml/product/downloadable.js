/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2021-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

const Downloadable = {
    uploaderObj: new Map(),
    objCount: 0,
    isReadOnly: false,
    configMaxDownloads: 0,
    alertAlreadyDisplayed: false,

    setUploaderObj(type, key, obj) {
        if (!this.uploaderObj.get(type)) {
            this.uploaderObj.set(type, new Map());
        }
        this.uploaderObj.get(type).set(key, obj);
    },

    getUploaderObj(type, key) {
        try {
            return this.uploaderObj.get(type).get(key);
        } catch (error) {
            console.error(error);
        }
    },

    unsetUploaderObj(type, key) {
        try {
            this.uploaderObj.get(type).delete(key);
        } catch (error) {
            console.error(error);
        }
    },

    massUploadByType(type) {
        try {
            for (const item of this.uploaderObj.get(type).values()) {
                const container = item.elements.container.closest('tr');
                if (checkVisibility(container)) {
                    item.upload();
                } else {
                    Downloadable.unsetUploaderObj(type, item.key);
                }
            }
        } catch (error) {
            console.error(error);
        }
    },

    showValidationAlert() {
        if (!this.alertAlreadyDisplayed) {
            this.alertAlreadyDisplayed = true;
            alert(Translator.translate(
                'There are files that were selected but not uploaded yet. Please upload or remove them first'
            ));
        }
    },

    addValidation() {
        Validation.addAllThese([
            ['validate-downloadable-file', 'Please upload a file.', (v, element) => {
                const linkType = element.parentNode.querySelector('input[value=file]');
                if (linkType.checked && (v === '' || v === '[]')) {
                    const newFileContainer = element.parentNode.querySelector('div.new-file');
                    if (newFileContainer.children.length && checkVisibility(newFileContainer)) {
                        this.showValidationAlert();
                    }
                    return false;
                }
                return true;
            }],
            ['validate-downloadable-url', 'Please specify Url.', (v, element) => {
                const linkType = element.parentNode.querySelector('input[value=url]');
                if (linkType.checked) {
                    return Validation.get('validate-url').test(v);
                }
                return true;
            }],
        ]);
    },
};

Downloadable.FileUploader = class {
    constructor(type, key, elmContainer, fileValueName, fileValue, idName, config) {
        if (!Downloadable.isReadOnly) {
            const uploaderTemplate = new Template(
                document.getElementById('downloadable-uploader-template').innerHTML,
                Template.SQUARE_PATTERN,
            );

            const html = uploaderTemplate.evaluate({
                'idName': idName,
                'fileValueName': fileValueName,
                'uploaderObj': `Downloadable.getUploaderObj('${type}', '${key}')`,
            });

            elmContainer.insertAdjacentHTML('afterbegin', html);

            const saveInputEl = document.getElementById(`${idName}_save`);
            if (saveInputEl) {
                saveInputEl.value = JSON.stringify(fileValue);
            }

            const uploader = new Uploader(
                typeof config === 'string' ? JSON.parse(config) : config
            );

            Downloadable.setUploaderObj(type, key, uploader);
            varienGlobalEvents?.attachEventHandler('tabChangeBefore', uploader.onContainerHideBefore);
            new Downloadable.FileList(idName, uploader, fileValue);
        }
    }
}

Downloadable.FileList = class {
    file = [];
    containerId = '';
    container = null;
    uploader = null;
    listTemplate = null;

    constructor(containerId, uploader, file = []) {
        this.containerId = containerId,
        this.container = document.getElementById(this.containerId);
        this.uploader = uploader;
        this.file = file;

        this.listTemplate = new Template(
            document.getElementById('downloadable-filelist-template').innerHTML,
            Template.HANDLEBARS_PATTERN,
        );

        this.updateFiles();
        this.bindEventListeners();
    }

    bindEventListeners() {
        this.uploader.addEventListener('fileSubmitted', (event) => {
            this.handleFileSelect();
        });

        this.uploader.addEventListener('fileRemoved', (event) => {
            this.handleFileDelete();
        });

        this.uploader.addEventListener('fileSuccess', (event) => {
            this.handleUploadComplete(event.detail.response);
        });
    }

    getElement(name) {
        return document.getElementById(`${this.containerId}_${name}`);
    }

    handleFileSelect() {
        this.getElement('type').checked = true;
        toggleVis(`${this.containerId}-new`, true);
        toggleVis(`${this.containerId}-old`, false);
    }

    handleFileDelete() {
        toggleVis(`${this.containerId}-new`, false);
        toggleVis(`${this.containerId}-old`, true);
    }

    handleUploadComplete(response) {
        this.file[0] = {
            file: response.file,
            name: response.name,
            size: response.size,
            status: 'new'
        }
        this.updateFiles();
    }

    updateFiles() {
        this.getElement('save').value = JSON.stringify(this.file);
        for (const row of this.file) {
            row.size = this.uploader.formatSize(row.size);

            document.getElementById(`${this.containerId}-old`).innerHTML = this.listTemplate.evaluate(row);
            toggleVis(`${this.containerId}-new`, false);
            toggleVis(`${this.containerId}-old`, true);
        }
    }
}

Downloadable.AbstractItems = class {
    table = null;
    tbody = null;
    blockId = null;
    config = null;
    template = null;
    itemCount = 0;

    constructor(tableId, templateId, blockId, config) {
        this.table = document.getElementById(tableId);
        this.tbody = this.table.tBodies.item(0);
        this.blockId = blockId;
        this.config = config;
        this.template = new Template(document.getElementById(templateId).innerHTML, Template.HANDLEBARS_PATTERN);
        this.bindEventListeners();
    }

    bindEventListeners() {
        this.table.tFoot?.querySelector('button.add')?.addEventListener('click', this.add.bind(this));
    }

    bindScopeCheckbox(checkboxEl, useDefault = false) {
        if (!checkboxEl) {
            return;
        }
        const inputEl = checkboxEl.parentNode.querySelector('input[type=text]');
        checkboxEl.addEventListener('click', (event) => {
            inputEl.disabled = checkboxEl.checked
        });
        if (useDefault) {
            checkboxEl.checked = true;
            inputEl.disabled = true;
        }
    }

    getRowSubElement(row, name) {
        return document.getElementById(`${row.id}_${name}`);
    }

    add(data) {
        Downloadable.alertAlreadyDisplayed = false;

        this.tbody.insertAdjacentHTML('beforeend', this.template.evaluate({ ...data, id: this.itemCount++ }));

        const row = this.tbody.lastElementChild;

        this.getRowSubElement(row, 'delete_button')?.addEventListener('click', this.remove.bind(this));

        row.querySelectorAll('input').forEach((el) => {
            el.addEventListener('change', el.setHasChanges);
        });
        row.querySelectorAll('button.delete').forEach((el) => {
            el.addEventListener('click', el.setHasChanges);
        });

        return row;
    }

    remove(event) {
        Downloadable.alertAlreadyDisplayed = false;

        const row = event.target.closest('tr');
        row.classList.add('no-display', 'ignore-validate');
        row.querySelector('input[type=hidden].__delete__').value = 1;
    }

    getUploaderConfig(container, type) {
        const config = JSON.parse(this.config.replaceAll(new RegExp(this.blockId, 'g'), container.id));
        if (type) {
            config.uploaderConfig.fileParameterName = type;
            config.uploaderConfig.target = setRouteParams(config.uploaderConfig.target, { type });
        }
        return config;
    }
}

Downloadable.LinkItems = class extends Downloadable.AbstractItems {
    add(data) {
        data = {
            link_id: 0,
            link_type: 'file',
            sample_type: 'none',
            file_save: [],
            sample_file_save: [],
            number_of_downloads: Downloadable.configMaxDownloads,
            ...data,
        };

        const row = super.add(data);

        this.bindScopeCheckbox(this.getRowSubElement(row, 'title'), !data.store_title);
        this.bindScopeCheckbox(this.getRowSubElement(row, 'price'), !data.website_price);

        this.getRowSubElement(row, `${data.link_type}_type`)?.setAttribute('checked', '');
        this.getRowSubElement(row, `sample_${data.sample_type}_type`)?.setAttribute('checked', '');

        this.getRowSubElement(row, 'is_unlimited').addEventListener('change', (event) => {
            this.getRowSubElement(row, 'downloads').disabled = event.target.checked;
        });

        if (data.is_unlimited) {
            this.getRowSubElement(row, 'is_unlimited').checked = true;
            this.getRowSubElement(row, 'downloads').disabled = true;
        }

        if (data.is_shareable) {
            for (const opt of this.getRowSubElement(row, 'shareable').options) {
                opt.selected = opt.value == data.is_shareable;
            }
        }

        this.getRowSubElement(row, 'url_type').adviceContainer =
            this.getRowSubElement(row, 'url_save').adviceContainer =
            this.getRowSubElement(row, 'file_type').adviceContainer =
            this.getRowSubElement(row, 'file_save').adviceContainer =
            this.getRowSubElement(row, 'link_container');

        this.getRowSubElement(row, 'sample_url_save').adviceContainer =
            this.getRowSubElement(row, 'sample_file_save').adviceContainer =
            this.getRowSubElement(row, 'sample_container');

        this.togglePriceFields();

        const fileContainer = this.getRowSubElement(row, 'file');
        new Downloadable.FileUploader(
            'links',
            `links_${row.id}`,
            fileContainer.closest('td'),
            `downloadable[link][{row.id}]`,
            data.file_save,
            fileContainer.id,
            this.getUploaderConfig(fileContainer),
        );

        const sampleFileContainer = this.getRowSubElement(row, 'sample_file');
        new Downloadable.FileUploader(
            'linkssample',
            `linkssample_${row.id}`,
            sampleFileContainer.closest('td'),
            `downloadable[link][{row.id}][sample]`,
            data.sample_file_save,
            sampleFileContainer.id,
            this.getUploaderConfig(sampleFileContainer, 'link_samples'),
        );
    }

    togglePriceFields() {
        const disabled = document.getElementById('downloadable_link_purchase_type').value !== '1';
        this.table.querySelectorAll('.link-prices[type=text]').forEach((el) => {
            el.disabled = el.classList.contains('disabled') || disabled;
        });
    }
}

Downloadable.SampleItems = class extends Downloadable.AbstractItems {
    add(data) {
        data = {
            sample_type: 'file',
            sample_id: 0,
            file_save: [],
            ...data,
        };

        const row = super.add(data);

        this.bindScopeCheckbox(this.getRowSubElement(row, 'title'), !data.store_title);

        this.getRowSubElement(row, `${data.sample_type}_type`)?.setAttribute('checked', '');

        this.getRowSubElement(row, 'url_type').adviceContainer =
            this.getRowSubElement(row, 'url_save').adviceContainer =
            this.getRowSubElement(row, 'file_type').adviceContainer =
            this.getRowSubElement(row, 'file_save').adviceContainer =
            this.getRowSubElement(row, 'container');

        const fileContainer = this.getRowSubElement(row, 'file');
        new Downloadable.FileUploader(
            'samples',
            row.id,
            fileContainer,
            `downloadable[sample][{row.id}]`,
            data.file_save,
            fileContainer.id,
            this.getUploaderConfig(fileContainer),
        );
    }
}

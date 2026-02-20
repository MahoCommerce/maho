/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * Adminhtml Uploader Instance
 *
 * Events:
 *
 * - `filesAdded` { files: File[] } - Used for file validation, call event.preventDefault() to reject
 * - `fileAdded` { file: File } - Used for file validation, call event.preventDefault() to reject
 * - `filesSubmitted` { files: File[] } - Files passed validation and were added to the queue
 * - `fileSubmitted` { file: File } - File passed validation and was added to the queue
 * - `fileRemoved` { file: File } - File was removed from the queue by pressing the delete button or after file upload
 * - `fileSuccess` { file: File, response: Object } - File was successfully uploaded, included response from server
 * - `fileError` { file: File, error: Error, message: string } - File upload encountered an error
 * - `beforeUpload` { files: File[] } - Files are about to be uploaded
 * - `success` { files: File[] } - Files were successfully uploaded
 * - `complete` { filesSuccess: File[], filesError: File[] } - Upload process is complete
 *
 * All events also include:
 * - `instance` referencing the uploader instance that dispatched the event.
 * - `containerId` referencing the container element passed during construction.
 *
 * Binding to events:
 *
 * - `document.addEventListener('uploader:filesAdded', (event) => {});`
 * - `uploaderInstance.addEventListener('filesAdded', (event) => {});`
 *
 * Note: global events are prefixed with `uploader:`
*/
class Uploader extends EventTarget {

    /** @type {Object.<string, (string|string[])>} Array of elements ids to instantiate DOM collection */
    elementsIds = {};

    /** @type {Object.<string, (HTMLElement|HTMLElement[])>} List of elements ids across all uploader functionality */
    elements = {};

    /** @type {Object} General Uploader config */
    uploaderConfig = {};

    /** @type {Object} browseConfig General Uploader config */
    browseConfig = {};

    /** @type {Object} Misc settings to manipulate Uploader */
    miscConfig = {};

    /** @type {string[]} Sizes in plural */
    sizesPlural = ['bytes', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

    /** @type {number} Precision of calculation during convetion to human readable size format */
    sizePrecisionDefault = 3;

    /** @type {number} Unit type conversion kib or kb, etc */
    sizeUnitType = 1024;

    /** @type {number} Timeout of completion handler */
    onCompleteTimeout = 1000;

    /** @type {number} Timeout of error handler */
    onErrorTimeout = 3000;

    /** @type {HTMLInputElement} Input element for selecting files */
    fileInput = null;

    /** @type {File[]} Files array containing pending uploads */
    files = [];

    /**
     * @param {Object} config
     */
    constructor(config) {
        super();
        this.initialize(config);
    }

    /**
     * @param {Object} config
     */
    initialize(config) {
        this.elementsIds = config.elementIds;
        this.elements = this.getElements(this.elementsIds);

        this.uploaderConfig = config.uploaderConfig;
        this.browseConfig = config.browseConfig;
        this.miscConfig =  config.miscConfig;

        this.createFileInput();
        this.attachEvents();

        // Backwards compatible method names
        this.formatSize = this._getPluralSize.bind(this);
        this.onContainerHideBefore = this.onTabChange.bind(this);
        this._XSSFilter = xssFilter;
    }

    /**
     * Create input element for selecting files
     *
     * @param {Object.<string, string[]>} obj
     * @returns {Object.<string, HTMLElement[]>}
     */
    createFileInput() {
        this.fileInput = document.createElement('input');
        this.fileInput.type = 'file';

        if (this.browseConfig.isDirectory) {
            this.fileInput.setAttribute('webkitdirectory', '');
        }
        if (!this.browseConfig.singleFile) {
            this.fileInput.setAttribute('multiple', '');
        }
        if (this.browseConfig.attributes) {
            for (const [attr, val] of Object.entries(this.browseConfig.attributes)) {
                this.fileInput.setAttribute(attr, val);
            }
        }
    }

    /**
     * Convert elementIds config option to DOM element
     *
     * @param {Object.<string, (string|string[])>} obj
     * @returns {Object.<string, (HTMLElement|HTMLElement[])>}
     */
    getElements(obj) {
        const result = {};
        for (const [ key, ids ] of Object.entries(obj)) {
            result[key] = this.getElementsByIds(ids);
        }
        return result;
    }

    /**
     * Get DOM elements from IDs
     *
     * @param {(string|string[])} ids
     * @returns {(HTMLElement|HTMLElement[])}
     */
    getElementsByIds(ids) {
        if (Array.isArray(ids)) {
            return ids.map(id => document.getElementById(id)).filter(Boolean);
        } else {
            return document.getElementById(ids);
        }
    }

    /**
     * Attach all types of events
     */
    attachEvents() {
        this.fileInput.addEventListener('change', (event) => {
            this.onFilesAdded(event.target.files);
        });

        for (const browseBtn of this.elements.browse ?? []) {
            browseBtn.addEventListener('click', (event) => {
                this.fileInput.dispatchEvent(new MouseEvent('click'));
            });
        }

        for (const uploadBtn of this.elements.upload ?? []) {
            uploadBtn.addEventListener('click', this.onUploadClick.bind(this));
        }

        this.elements.delete?.addEventListener('click', this.onDeleteClick.bind(this));
    }

    /**
     * Handler for varien tab change
     *
     * @param {?Function} successFunc
     */
    onTabChange(successFunc) {
        if (this.files.length === 0) {
            return;
        }
        if (confirm(this._translate('There are files that were selected but not uploaded yet. After switching to another tab your selections will be lost. Do you wish to continue ?'))) {
            if (typeof successFunc === 'function') {
                successFunc();
            } else {
                this._handleDelete(this.files);
            }
        } else {
            return 'cannotchange';
        }
    }

    /**
     * Handler for file input change event
     *
     * @param {File[]} files
     */
    onFilesAdded(files) {
        if (!this._fireEvent('filesAdded', { files })) {
            return;
        }
        const filesSubmitted = [];
        for (const file of files) {
            if (this._checkFileSize(file)) {
                this._showFileSizeAlert();
                continue;
            }
            if (!this._fireEvent('fileAdded', { file })) {
                continue;
            }

            file.uniqueIdentifier = generateRandomString(6);
            this._handleUpdateFile(file);

            filesSubmitted.push(file);
            this._fireEvent('fileSubmitted', { file });
        }
        if (filesSubmitted.length) {
            this._fireEvent('filesSubmitted', { files: filesSubmitted });
        }
    }

    /**
     * Add file to queue and render file-line container
     *
     * @param {File} file
     * @private
     */
    _handleUpdateFile(file) {
        const html = this._renderFromTemplate(this.elements.templateFile, {
            name: file.name,
            size: file.size ? `(${this._getPluralSize(file.size)})` : '',
            id: file.uniqueIdentifier
        });

        if (this.uploaderConfig.singleFile) {
            this.files = [file];
            this.elements.container.innerHTML = html;
        } else {
            this.files.push(file);
            this.elements.container.insertAdjacentHTML('beforeend', html);
        }

        this._handleButtonsSwap(false);
        this._getDeleteButtonById(file.uniqueIdentifier)?.addEventListener('click', this.onDeleteClick.bind(this));
    }

    /**
     * Swap the visibility of browse and delete buttons
     *
     * @param {boolean} showBrowse
     * @private
     */
    _handleButtonsSwap(showBrowse) {
        if (!this.miscConfig.replaceBrowseWithRemove || !this.elements.delete || !this.elements.browse?.length) {
            return;
        }
        for (const browseBtn of this.elements.browse) {
            toggleVis(browseBtn, !!showBrowse);
        }
        toggleVis(this.elements.delete, !showBrowse);
    }

    /**
     * Get file-line container by id
     *
     * @param {string} id
     * @returns {?HTMLElement}
     * @private
     */
    _getFileContainerById(id) {
        return document.getElementById(`${id}-container`);
    }

    /**
     * Get file-line progress node by id
     *
     * @param {string} id
     * @returns {?HTMLElement}
     * @private
     */
    _getProgressTextById(id) {
        return this._getFileContainerById(id).querySelector('.progress-text');
    }

    /**
     * Get file-line delete button by id
     *
     * @param {string} id
     * @returns {?HTMLElement}
     * @private
     */
    _getDeleteButtonById(id) {
        return this._getFileContainerById(id).querySelector('.delete');
    }

    /**
     * Get queued file object by id
     *
     * @param {string} id
     * @returns {?File}
     * @private
     */
    _getFileFromUniqueIdentifier(id) {
        return this.files.find((file) => file.uniqueIdentifier === id);
    }

    /**
     * Upload button click event
     */
    onUploadClick() {
        this.upload();
    }

    /**
     * Upload all files in queue one at a time
     */
    async upload() {
        this._fireEvent('beforeUpload', { files: this.files });
        this.onUploadStart();

        const detail = { filesSuccess: [], filesError: [] };
        for (const file of this.files) {
            await this.uploadFile(file)
                ? detail.filesSuccess.push(file)
                : detail.filesError.push(file);
        }

        if (detail.filesSuccess.length) {
            this._fireEvent('success', { files: detail.filesSuccess });
        }
        this._fireEvent('complete', detail);
        this.files = [];
    }

    /**
     * Upload button is being pressed
     */
    onUploadStart() {
        for (const file of this.files) {
            const id = file.uniqueIdentifier;
            this._getFileContainerById(id).classList.remove('new', 'error');
            this._getFileContainerById(id).classList.add('progress');
            this._getProgressTextById(id).textContent = this._translate('Uploading...');
            this._getDeleteButtonById(id)?.remove();
        }
    }

    /**
     * Upload single file and call complete or error handler
     *
     * @param {File} file
     */
    async uploadFile(file) {
        try {
            const target = setQueryParams(this.uploaderConfig.target, this.uploaderConfig.query);

            const formData = new FormData();
            formData.set(this.uploaderConfig.fileParameterName, file);

            const result = await mahoFetch(target, {
                method: 'POST',
                headers: this.uploaderConfig.headers,
                body: formData,
                loaderArea: false,
            });

            this.onFileSuccess(file, result);
            return true;

        } catch (error) {
            this.onFileError(file, error);
            return false;
        }
    }

    /**
     * Successfully uploaded file, notify about that other components, handle deletion from queue
     *
     * @param {File} file
     * @param {Object} response
     */
    onFileSuccess(file, response) {
        const id = file.uniqueIdentifier;

        this._getFileContainerById(id).classList.remove('progress');
        this._getFileContainerById(id).classList.add('complete');
        this._getProgressTextById(id).textContent = this._translate('Complete');

        setTimeout(() => {
            this._fireEvent('fileSuccess', { file, response });
            this._handleDelete([file]);
        }, this.onCompleteTimeout);
    }

    /**
     * Failed uploaded file, notify about that other components, handle deletion from queue
     *
     * @param {File} file
     * @param {Error} error
     */
    onFileError(file, error) {
        const id = file.uniqueIdentifier;

        this._getFileContainerById(id).classList.remove('progress');
        this._getFileContainerById(id).classList.add('error');
        this._getProgressTextById(id).textContent = error.message;

        setTimeout(() => {
            this._fireEvent('fileError', { file, error, message: error.message });
            this._handleDelete([file]);
        }, this.onErrorTimeout);
    }

    /**
     * Handle delete button click
     *
     * @param {Event} event
     */
    onDeleteClick(event) {
        if (event.target === this.elements.delete) {
            this._handleDelete(this.files);
        } else {
            this._handleDelete([this._getFileFromUniqueIdentifier(event.target.id)]);
        }
    }

    /**
     * Handle deletion of files
     *
     * @param {File[]} files
     * @private
     */
    _handleDelete(files) {
        for (const file of files) {
            this._fireEvent('fileRemoved', { file });
            this._getFileContainerById(file.uniqueIdentifier)?.remove();
        }

        this.files = this.files.filter((file) => !files.includes(file));
        if (this.files.length === 0) {
            this._handleButtonsSwap(true);
        }
    }

    /**
     * Check whenever file size exceeded permitted amount
     *
     * @param {File} file
     * @returns {boolean}
     * @private
     */
    _checkFileSize(file) {
        return this.miscConfig.maxSizeInBytes && file.size > this.miscConfig.maxSizeInBytes;
    }

    /**
     * Show alert when file size exceeds permitted amount
     *
     * @private
     */
    _showFileSizeAlert() {
        const msg = [
            this._translate('Maximum allowed file size for upload is') + ` ${this.miscConfig.maxSizePlural}`,
            this._translate('Please check your server PHP settings.'),
        ];
        alert(msg.join('\n'));
    }

    /**
     * Fire an event with details object and check if preventDefault() was called
     *
     * @param {string} type
     * @param {Object} detail
     * @private
     */
    _fireEvent(type, detail = {}) {
        detail.instance = this;
        detail.containerId = this.elementsIds.container;
        const results = [
            this.dispatchEvent(new CustomEvent(type, { detail }, { cancelable: true })),
            document.dispatchEvent(new CustomEvent(`uploader:${type}`, { detail }, { cancelable: true })),
        ];
        return results.every(Boolean);
    }

    /**
     * Make a translation of string
     *
     * @param {string} text
     * @returns {string}
     * @private
     */
    _translate(text) {
        return typeof Translator !== 'undefined' ? Translator.translate(text) : text;
    }

    /**
     * Render from given template and given variables to assign
     *
     * @param {HTMLElement} template
     * @param {Object} vars
     * @returns {string}
     * @private
     */
    _renderFromTemplate(template, vars) {
        const t = new Template(this._XSSFilter(template.innerHTML), Template.HANDLEBARS_PATTERN);
        return t.evaluate(vars);
    }

    /**
     * Format size with precision
     *
     * @param {number} sizeInBytes
     * @param {number} [precision]
     * @returns {string}
     * @private
     */
    _getPluralSize(sizeInBytes, precision) {
        if (sizeInBytes == 0) {
            return 0 + this.sizesPlural[0];
        }
        const dm = (precision || this.sizePrecisionDefault) + 1;
        const i = Math.floor(Math.log(sizeInBytes) / Math.log(this.sizeUnitType));

        return (sizeInBytes / Math.pow(this.sizeUnitType, i)).toPrecision(dm) + ' ' + this.sizesPlural[i];
    }
}

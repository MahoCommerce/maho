/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

const MediabrowserUtility = {

    dialogWindow: null,
    dialogWindowId: 'browser_window',
    lastSelectedNode: null,

    async openDialog(url, width, height, title, options) {
        if (document.getElementById(this.dialogWindowId)) {
            return;
        }
        try {
            if (!url.match(/\/node\/(.*?)\//)) {
                url = setRouteParams(url, { node: this.lastSelectedNode });
            }
            const result = await mahoFetch(url);

            this.dialogWindow = Dialog.info(result, {
                id: this.dialogWindowId,
                title: title || 'Insert File...',
                className: 'magento',
                windowClassName: 'popup-window',
                width: width || 950,
                height: height || 600,
                onClose: this.closeDialog.bind(this),
                ...options,
            });
        } catch (error) {
            alert(error.message);
        }
    },

    closeDialog(window) {
        window ??= this.dialogWindow;
        window?.close();
    },
};

class Mediabrowser {

    tree = null;
    currentNode = null;
    storeId = null;

    constructor() {
        this.initialize(...arguments);
    }

    initialize(setup) {
        this.targetElementId = setup.targetElementId;
        this.indexUrl = setup.indexUrl;
        this.contentsUrl = setup.contentsUrl;
        this.onInsertUrl = setup.onInsertUrl;
        this.newFolderUrl = setup.newFolderUrl;
        this.deleteFolderUrl = setup.deleteFolderUrl;
        this.deleteFilesUrl = setup.deleteFilesUrl;
        this.editImageUrl = setup.editImageUrl;
        this.getImageUrlAction = setup.getImageUrl;
        this.headerText = setup.headerText;
        this.canInsertImage = setup.canInsertImage;
        this.imageFileType = setup.imageFileType;
        this.imageQuality = setup.imageQuality;
    }

    initializeEventListeners() {
        // Only initialize once
        if (this.eventListenersInitialized) {
            return;
        }
        this.eventListenersInitialized = true;

        document.addEventListener('uploader:filesAdded', (event) => {
            this.deselectFiles();
        });
        document.addEventListener('uploader:beforeUpload', (event) => {
            event.detail.instance.uploaderConfig.target = setRouteParams(event.detail.instance.uploaderConfig.target, {
                node: this.currentNode.id,
            });
        });
        document.addEventListener('uploader:success', (event) => {
            this.handleUploadComplete(event.detail.files);
        });
    }

    setTree(tree) {
        this.tree = tree;
        this.currentNode = tree.getRootNode();
    }

    getTree(tree) {
        return this.tree;
    }

    async selectFolder(node, forceRefresh = false) {
        if (this.currentNode === node && !forceRefresh) {
            return;
        }

        this.currentNode = node;
        this.currentNode.select();

        this.hideFileButtons();
        this.activateBlock('contents');

        if (node.id === 'root') {
            this.hideElement('button_delete_folder');
        } else {
            this.showElement('button_delete_folder');
        }

        this.updateUrl(this.currentNode);
        this.updateHeader(this.currentNode);
        this.drawBreadcrumbs(this.currentNode);

        MediabrowserUtility.lastSelectedNode = node.id;

        return this.updateContent();
    }

    async updateContent() {
        try {
            const html = await mahoFetch(this.contentsUrl, {
                method: 'POST',
                body: new URLSearchParams({
                    node: this.currentNode.id,
                }),
            });

            const contentsEl = document.getElementById('contents');
            if (!contentsEl) {
                return;
            }
            updateElementHtmlAndExecuteScripts(contentsEl, html);

            for (const el of contentsEl.querySelectorAll('div.filecnt')) {
                el.addEventListener('click', this.selectFile.bind(this));
                if (this.canInsertImage) {
                    el.addEventListener('dblclick', this.insert.bind(this));
                } else if (this.isCmsMediaLibrary()) {
                    // In CMS Media Library, double-click on images opens editor
                    el.addEventListener('dblclick', (event) => {
                        this.selectFile(event);
                        if (this.isSelectedFileImage()) {
                            this.editImage();
                        }
                    });
                }
            }

            document.getElementById('contents-uploader')?.prepend(
                document.getElementById('contents-alt-text'),
            );
        } catch(error) {
            alert(error.message);
        }
    }

    selectFolderById(nodeId) {
        this.tree.getNodeById(nodeId)?.select();
    }

    selectFile(event) {
        const div = event.target.closest('div.filecnt');
        const selected = !div.classList.contains('selected');

        this.deselectFiles();
        div.classList.toggle('selected', selected);

        if (selected) {
            this.showFileButtons();
        }
    }

    selectFileById(fileId) {
        document.getElementById(fileId)?.click();
    }

    deselectFiles() {
        for (const el of document.querySelectorAll('div.filecnt.selected')) {
            el.classList.remove('selected')
        }
        this.hideFileButtons();
    }

    showFileButtons() {
        this.showElement('button_delete_files');
        if (this.canInsertImage) {
            this.showElement('button_insert_files');
            this.showElement('contents-alt-text');
        }

        // Show edit button only for image files and only in CMS Media Library
        if (this.isSelectedFileImage() && this.isCmsMediaLibrary()) {
            this.showElement('button_edit_image');
        }
    }

    hideFileButtons() {
        this.hideElement('button_delete_files');
        this.hideElement('button_insert_files');
        this.hideElement('button_edit_image');
        this.hideElement('contents-alt-text');
    }

    handleUploadComplete(files) {
        for (const el of document.querySelectorAll('div[class*="file-row complete"]')) {
            document.getElementById(el.id)?.remove();
        }
        this.updateContent();
    }

    async insert(event) {
        const div = event
              ? event.target.closest('div.filecnt')
              : Array.from(document.querySelectorAll('div.filecnt.selected')).pop();

        if (!div) {
            return false;
        }

        try {
            const params = new URLSearchParams({
                filename: div.id,
                node: this.currentNode.id,
                store: this.storeId,
                alt: document.querySelector('input[name=alt]')?.value,
            });

            const html = await mahoFetch(this.onInsertUrl, {
                method: 'POST',
                body: params,
            });

            // Close the dialog, and send html as dialog.returnValue
            Dialog.close(html);

            const targetEl = document.getElementById(this.targetElementId);
            if (targetEl) {
                if (targetEl.tagName === 'INPUT') {
                    targetEl.value = html;
                } else if (targetEl.tagName === 'TEXTAREA') {
                    updateElementAtCursor(targetEl, html);
                }
            }

        } catch (error) {
            alert(error.message);
        }
    }

    async newFolder() {
        const folderName = prompt(Translator.translate('New Folder Name:'));
        if (!folderName) {
            return false;
        }
        try {
            const result = await mahoFetch(this.newFolderUrl, {
                method: 'POST',
                body: new URLSearchParams({
                    node: this.currentNode.id,
                    name: folderName,
                }),
            });

            const child = new MahoTreeNode(this.tree, {
                text: result.short_name,
                id: result.id,
                children: [],
            });

            this.currentNode.appendChild(child);
            this.currentNode.sortChildren();
            this.tree.expandPath(this.currentNode.getPath()).then((node) => {
                this.selectFolderById(result.id);
            });
        } catch (error) {
            alert(error.message);
        }
    }

    async deleteFolder() {
        const message = Translator.translate('Are you sure you want to delete current folder?');
        if (!confirm(message)) {
            return false;
        }
        try {
            await mahoFetch(this.deleteFolderUrl, {
                method: 'POST',
                body: new URLSearchParams({
                    node: this.currentNode.id,
                }),
            });

            const parent = this.currentNode.parentNode;
            parent.removeChild(this.currentNode);
            this.selectFolder(parent);

        } catch (error) {
            alert(error.message);
        }
    }

    async deleteFiles() {
        const message = Translator.translate('Are you sure you want to delete the selected file?');
        if (!confirm(message)) {
            return false;
        }

        const ids = [];
        for (const el of document.querySelectorAll('div.selected')) {
            ids.push(el.id);
        }

        try {
            await mahoFetch(this.deleteFilesUrl, {
                method: 'POST',
                body: new URLSearchParams({
                    node: this.currentNode.id,
                    files: JSON.stringify(ids),
                }),
            });

            this.updateContent();

        } catch (error) {
            alert(error.message);
        }
    }

    drawBreadcrumbs(node) {
        let breadcrumbsEl = document.getElementById('breadcrumbs');
        if (!breadcrumbsEl) {
            breadcrumbsEl = document.createElement('ul');
            breadcrumbsEl.id = 'breadcrumbs';
            breadcrumbsEl.className = 'breadcrumbs';
            document.getElementById('content_header')?.after(breadcrumbsEl);
        }

        breadcrumbsEl.innerHTML = '';

        if (node.id === 'root') {
            return;
        }

        const crumbs = node.getPath().split('/');
        for (let i = 0; i < crumbs.length; i++) {
            const currNode = this.tree.getNodeById(crumbs[i]);
            const crumbEl = breadcrumbsEl.appendChild(document.createElement('li'));

            if (i < crumbs.length - 1) {
                const linkEl = crumbEl.appendChild(document.createElement('a'));
                linkEl.href = '#';
                linkEl.textContent = currNode.text;
                linkEl.addEventListener('click', (event) => {
                    event.preventDefault();
                    this.selectFolder(currNode);
                });

                const spanEl = breadcrumbsEl.appendChild(document.createElement('span'));
                spanEl.textContent = ' / ';

            } else {
                const spanEl = crumbEl.appendChild(document.createElement('span'));
                spanEl.textContent = currNode.text;
            }
        }
    }

    updateUrl(node) {
        // Don't update URL in modal view
        if (document.getElementById('contents')?.closest('dialog')) {
            return;
        }
        history.replaceState(null, '', setRouteParams(this.indexUrl, { node: node.id }));
    }

    updateHeader(node) {
        const headerEl = document.getElementById('content_header_text');
        if (headerEl) {
            headerEl.textContent = node.id === 'root' ? this.headerText : node.text;
        }
    }

    activateBlock(id) {
        this.showElement(id);
    }

    hideElement(id) {
        document.getElementById(id)?.classList.add('no-display');
    }

    showElement(id) {
        document.getElementById(id)?.classList.remove('no-display');
    }

    isSelectedFileImage() {
        const selectedFile = document.querySelector('div.filecnt.selected');
        if (!selectedFile) {
            return false;
        }

        // Check if the file has an image thumbnail (indication it's an image)
        const img = selectedFile.querySelector('img');
        return !!img;
    }

    isCmsMediaLibrary() {
        // Check if we're in the CMS Media Library (not in popup/modal context)
        return window.location.pathname.includes('cms_wysiwyg_images') &&
               !window.location.search.includes('target_element_id');
    }

    async getImageUrl(fileId) {
        try {
            const response = await mahoFetch(this.getImageUrlAction, {
                method: 'POST',
                body: new URLSearchParams({
                    file_id: fileId,
                    node: this.currentNode.id,
                    form_key: this.getFormKey(),
                }),
            });

            if (response.success && response.url) {
                return response.url;
            } else {
                throw new Error(response.message || 'Failed to get image URL');
            }
        } catch (error) {
            // Fallback to basic construction
            const baseUrl = window.location.origin;
            const mediaPath = '/media/wysiwyg';
            return `${baseUrl}${mediaPath}/${fileId}`;
        }
    }

    async editImage() {
        const selectedFile = document.querySelector('div.filecnt.selected');
        if (!selectedFile || !this.isSelectedFileImage()) {
            return false;
        }

        // Hide the edit button immediately when clicked
        this.hideElement('button_edit_image');

        try {
            // Load filerobot-image-editor if not already loaded
            if (!window.FilerobotImageEditor) {
                await this.loadFilerobotEditor();
            }

            // Get the image source URL - construct full image URL from file info
            const img = selectedFile.querySelector('img');
            let imageUrl = img.src;

            // Always get the full image URL using the file ID
            const fileId = selectedFile.id;
            if (fileId) {
                // Get actual image URL using media browser's storage URL
                imageUrl = await this.getImageUrl(fileId);
            } else {
                // Fallback: if it's a thumbnail URL, convert to full image
                if (imageUrl.includes('.thumbs')) {
                    // Remove .thumbs from path and query parameters
                    imageUrl = imageUrl
                        .replace('/.thumbs', '')
                        .replace('/wysiwyg//', '/wysiwyg/')
                        .split('?')[0]; // Remove query params
                }
            }

            // Additional fallback: if we still have thumbs in URL, try another approach
            if (imageUrl.includes('.thumbs')) {
                const fileName = imageUrl.split('/').pop().split('?')[0];
                imageUrl = `${window.location.origin}/media/wysiwyg/${fileName}`;
            }

            // Create editor container
            const editorContainer = this.createEditorContainer();

            // Wait for container to be properly sized before initializing editor
            await new Promise(resolve => setTimeout(resolve, 100));

            // Preload the image to ensure it's available
            await this.preloadImage(imageUrl);

            // Get original filename without extension for display
            const smallTags = selectedFile.querySelectorAll('small');
            const originalFilename = smallTags[smallTags.length - 1]?.textContent || 'image';
            const filenameWithoutExt = originalFilename.split('.')[0];

            // Initialize the image editor with proper configuration
            const imageEditor = new window.FilerobotImageEditor(editorContainer, {
                source: imageUrl,
                defaultSavedImageName: filenameWithoutExt,
                defaultSavedImageType: this.imageFileType.extension,
                defaultSavedImageQuality: this.imageQuality,
                avoidChangesNotSavedAlertOnLeave: true,
                onSave: (editedImageObject, designState) => {
                    this.saveEditedImage(selectedFile.id, editedImageObject);
                },
                onClose: (closingReason) => {
                    this.closeImageEditor();
                }
            });

            imageEditor.render({
                onClose: () => this.closeImageEditor()
            });

            // Store reference for potential debugging
            this.currentImageEditor = imageEditor;

        } catch (error) {
            alert('Error loading image editor: ' + error.message);
        }
    }

    async loadFilerobotEditor() {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = SKIN_URL + '../../../../js/filerobot-image-editor.min.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    createEditorContainer() {
        // Remove existing editor container if any
        const existingContainer = document.getElementById('image-editor-container');
        if (existingContainer) {
            existingContainer.remove();
        }

        // Create overlay
        const overlay = document.createElement('div');
        overlay.id = 'image-editor-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1200;
            display: flex;
            align-items: center;
            justify-content: center;
        `;

        // Create container
        const container = document.createElement('div');
        container.id = 'image-editor-container';
        container.style.cssText = `
            width: 95vw;
            height: 95vh;
            max-width: 1400px;
            max-height: 800px;
            min-width: 800px;
            min-height: 600px;
            background: white;
            border-radius: 8px;
            position: relative;
            overflow: hidden;
            z-index: 1250;
        `;

        overlay.appendChild(container);
        document.body.appendChild(overlay);

        // Add CSS for editor and hide file type selector
        const style = document.createElement('style');
        style.setAttribute('data-editor-styles', 'true');
        style.textContent = `
            #image-editor-overlay * {
                box-sizing: border-box;
            }

            /* Hide file type selector and quality slider in save dialog since we use configured values */
            .FIE_save-modal .FIE_save-extension-selector,
            .FIE_save-modal .SfxSelect-wrapper[data-testid*="extension"],
            .FIE_save-modal .SfxSelect[data-testid*="extension"],
            [data-testid="save-image-type-selector"],
            [data-testid="save-extension-selector"],
            .FIE_save-modal .FIE_save-quality-wrapper,
            .FIE_save-modal .FIE_save-quality-slider,
            [data-testid="save-quality-slider"],
            [data-testid="save-image-quality-slider"] {
                display: none !important;
            }
        `;
        document.head.appendChild(style);

        return container;
    }

    async saveEditedImage(fileId, editedImageObject) {
        try {
            // Convert edited image to FormData
            const formData = new FormData();
            formData.append('file_id', fileId);
            formData.append('node', this.currentNode.id);
            formData.append('form_key', this.getFormKey());

            // Extract filename from the save dialog input or use default
            let filename = 'edited_image';

            // Try to get filename from save dialog input first
            const filenameInput = document.querySelector('.FIE_save-modal input[type="text"], .SfxModal input[type="text"]');
            if (filenameInput && filenameInput.value.trim()) {
                filename = filenameInput.value.trim();
            } else if (editedImageObject.fullName) {
                filename = editedImageObject.fullName;
            } else if (editedImageObject.name) {
                filename = editedImageObject.name;
            }

            // Let PHP handle extension replacement - just pass the filename as-is
            formData.append('new_filename', filename);

            // Convert image to blob using configured file type
            const mimeType = this.imageFileType.mimeType;
            const quality = this.imageQuality;

            if (editedImageObject.canvas) {
                const blob = await new Promise(resolve => {
                    if (mimeType === 'image/jpeg') {
                        editedImageObject.canvas.toBlob(resolve, mimeType, quality);
                    } else {
                        editedImageObject.canvas.toBlob(resolve, mimeType);
                    }
                });
                formData.append('edited_image', blob);
            } else if (editedImageObject.imageBase64) {
                const response = await fetch(editedImageObject.imageBase64);
                const blob = await response.blob();
                formData.append('edited_image', blob);
            } else if (editedImageObject.file) {
                // If it's a file object directly
                formData.append('edited_image', editedImageObject.file);
            }

            // Save the edited image
            const result = await mahoFetch(this.editImageUrl, {
                method: 'POST',
                body: formData,
            });

            this.closeImageEditor();
            this.updateContent();
        } catch (error) {
            alert('Error saving edited image: ' + error.message);
        }
    }

    closeImageEditor() {
        // Clean up the editor instance
        if (this.currentImageEditor && typeof this.currentImageEditor.terminate === 'function') {
            try {
                this.currentImageEditor.terminate();
            } catch (e) {
                console.warn('Error terminating image editor:', e);
            }
        }
        this.currentImageEditor = null;

        // Remove overlay and styles
        const overlay = document.getElementById('image-editor-overlay');
        if (overlay) {
            overlay.remove();
        }

        // Remove any editor-specific styles
        const editorStyles = document.querySelectorAll('style[data-editor-styles]');
        editorStyles.forEach(style => style.remove());
    }

    getFormKey() {
        // Try multiple methods to get the form key

        // Method 1: Global variable
        if (window.FORM_KEY) {
            return window.FORM_KEY;
        }

        // Method 2: Meta tag
        const metaFormKey = document.querySelector('meta[name="form_key"]');
        if (metaFormKey) {
            return metaFormKey.getAttribute('content');
        }

        // Method 3: Hidden input field
        const inputFormKey = document.querySelector('input[name="form_key"]');
        if (inputFormKey) {
            return inputFormKey.value;
        }

        // Method 4: From any existing form
        const formKeyInput = document.querySelector('form input[name="form_key"]');
        if (formKeyInput) {
            return formKeyInput.value;
        }

        return '';
    }

    preloadImage(url) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = () => reject(new Error('Failed to load image'));
            img.src = url;
        });
    }
};

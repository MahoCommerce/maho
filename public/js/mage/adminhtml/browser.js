/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

const MediabrowserUtility = {

    dialogWindow: null,
    dialogWindowId: 'browser_window',

    async openDialog(url, width, height, title, options) {
        if (document.getElementById(this.dialogWindowId)) {
            return;
        }
        try {
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

    targetElementId = null;
    contentsUrl = null;
    onInsertUrl = null;
    newFolderUrl = null;
    deleteFolderUrl = null;
    deleteFilesUrl = null;
    headerText = null;
    tree = null;
    currentNode = null;
    storeId = null;
    canInsertImage = null;

    constructor() {
        this.initialize(...arguments);
    }

    initialize(setup) {
        this.targetElementId = setup.targetElementId;
        this.contentsUrl = setup.contentsUrl;
        this.onInsertUrl = setup.onInsertUrl;
        this.newFolderUrl = setup.newFolderUrl;
        this.deleteFolderUrl = setup.deleteFolderUrl;
        this.deleteFilesUrl = setup.deleteFilesUrl;
        this.headerText = setup.headerText;
        this.canInsertImage = setup.canInsertImage;
    }

    static {
        document.addEventListener('uploader:filesAdded', (event) => {
            MediabrowserInstance.deselectFiles();
        });
        document.addEventListener('uploader:success', (event) => {
            MediabrowserInstance.handleUploadComplete(event.detail.files);
        });
    }

    setTree(tree) {
        this.tree = tree;
        this.currentNode = tree.getRootNode();
    }

    getTree(tree) {
        return this.tree;
    }

    selectFolder(node) {
        if (this.currentNode === node) {
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

        this.updateHeader(this.currentNode);
        this.drawBreadcrumbs(this.currentNode);

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

            contentsEl.querySelectorAll('div.filecnt').forEach((el) => {
                el.addEventListener('click', this.selectFile.bind(this));
                if (this.canInsertImage) {
                    el.addEventListener('dblclick', this.insert.bind(this));
                }
            });

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
        document.querySelectorAll('div.filecnt.selected').forEach((el) => {
            el.classList.remove('selected')
        });
        this.hideFileButtons();
    }

    showFileButtons() {
        this.showElement('button_delete_files');
        if (this.canInsertImage) {
            this.showElement('button_insert_files');
            this.showElement('contents-alt-text');
        }
    }

    hideFileButtons() {
        this.hideElement('button_delete_files');
        this.hideElement('button_insert_files');
        this.hideElement('contents-alt-text');
    }

    handleUploadComplete(files) {
        document.querySelectorAll('div[class*="file-row complete"]').forEach((el) => {
            document.getElementById(el.id)?.remove();
        });
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
            if (targetEl.tagName === 'INPUT') {
                targetEl.value = html;
            } else if (targetEl.tagName === 'TEXTAREA') {
                updateElementAtCursor(targetEl, html);
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
            await mahoFetch(this.deleteFolderUrl, { method: 'POST' });

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

        if (node.id === 'root') {
            breadcrumbsEl.innerHTML = '';
            return;
        }

        const crumbs = node.getPath().split('/').map((id) => {
            const currNode = this.tree.getNodeById(id);
            return `<li><a href="#" onclick="MediabrowserInstance.selectFolderById('${currNode.id}');">${currNode.text}</a></li>`;
        });

        breadcrumbsEl.innerHTML = crumbs.join(' <span>/</span> ');
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
};

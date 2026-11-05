// SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

/**
 * Class to control the "Edit Attribute Set" page
 */
class EavAttributeSetForm {

    /**
     * @param {Object} [config]
     * @param {string} config.saveUrl - controller action to save set
     * @param {string} config.deleteURL - controller action to delete set
     * @param {string} [config.containerGroups] - id of div to build groups tree
     * @param {string} [config.containerAttributes] - id of div to build unassigned attributes tree
     * @param {string} [config.formId] - id of edit set form
     * @param {string} [config.saveBtn] - id of save set button
     * @param {string} [config.deleteBtn] - id of delete set button
     * @param {string} [config.addGroupBtn] - id of add group button
     * @param {string} [config.deleteGroupBtn] - id of delete group button
     * @param {string} [config.renameGroupBtn] - id of rename group button
     * @param {string} [config.removeDropZone] - id of drop zone div for removing attributes
     * @param {boolean} [config.isReadOnly=false] - set true to disable sorting attributes
     * @param {function():boolean} [config.canDeleteSet] - callback to prompt user before deleting set
     * @param {function(MahoTreeNode):boolean|string} [config.canRemoveAttribute] - callback to determine if an attribute can be removed from set, return string for custom error message
     * @param {function(MahoTreeNode):boolean|string} [config.canDeleteGroup] - callback to determine if a group can be deleted, return string for custom error message
     * @param {Object} [config.groupDialog] - the websites, their store views and the texts of the group dialog
     * @param {{name: string, stores: {id: number, name: string}[]}[]} config.groupDialog.websites - the store views of each website, which can give a group its own label
     * @param {Object<string, string>} config.groupDialog.labels - the texts of the dialog
     */
    constructor(config) {

        this.config = {
            saveUrl: null,
            deleteUrl: null,

            containerGroups: 'tree-div1',
            containerAttributes: 'tree-div2',
            formId: 'set_prop_form',
            saveBtn: 'save-button',
            deleteBtn: 'delete-button',
            addGroupBtn: 'add-group-button',
            deleteGroupBtn: 'delete-group-button',
            renameGroupBtn: 'rename-group-button',
            removeDropZone: 'remove-drop-zone',

            isReadOnly: false,
            canDeleteSet: null,
            canRemoveAttribute: null,
            canDeleteGroup: null,
            groupDialog: { websites: [], labels: {} },

            ...config,
        };

        this.formEl = document.getElementById(this.config.formId);
        if (!this.formEl) {
            throw new Error(`Form with ID ${this.config.formId} not found in DOM`);
        }

        this.removeGroups = [];
        if (typeof this.config.canDeleteSet === 'function') {
            this.canDeleteSet = this.config.canDeleteSet.bind(this);
        }
        if (typeof this.config.canRemoveAttribute === 'function') {
            this.canRemoveAttribute = this.config.canRemoveAttribute.bind(this);
        }
        if (typeof this.config.canDeleteGroup === 'function') {
            this.canDeleteGroup = this.config.canDeleteGroup.bind(this);
        }

        this.ui = {
            saveBtn: document.getElementById(this.config.saveBtn),
            deleteBtn: document.getElementById(this.config.deleteBtn),
            addGroupBtn: document.getElementById(this.config.addGroupBtn),
            deleteGroupBtn: document.getElementById(this.config.deleteGroupBtn),
            renameGroupBtn: document.getElementById(this.config.renameGroupBtn),
        }

        this.tree1 = new MahoTree(this.config.containerGroups, {
            showRootNode: false,
            selectable: this.config.isReadOnly ? false : {
                mode: 'single',
                showInputs: false,
                onSelect: this.onSelectGroup.bind(this),
            },
            sortable: this.config.isReadOnly ? false : {
                group: 'attributes',
                containDepth: true,
                mahoTreeDropZone: {
                    dropZone: document.getElementById(this.config.removeDropZone),
                    onHover: this.onRemoveAttributeHover.bind(this),
                    onDrop: this.onRemoveAttribute.bind(this),
                },
            },
        });
        this.tree2 = new MahoTree(this.config.containerAttributes, {
            showRootNode: false,
            sortable: this.config.isReadOnly ? false : {
                group: {
                    name: 'attributes.2',
                    put: false,
                },
                sort: false,
            },
            cssVars: {
                'indent': '0',
                'line-style': 'none',
            },
        });

        this.bindEventListeners();
    }

    bindEventListeners() {
        this.ui.saveBtn?.addEventListener('click', this.saveSet.bind(this));
        this.ui.deleteBtn?.addEventListener('click', this.deleteSet.bind(this));
        this.ui.addGroupBtn?.addEventListener('click', this.addGroup.bind(this));
        this.ui.deleteGroupBtn?.addEventListener('click', this.deleteGroup.bind(this));
        this.ui.renameGroupBtn?.addEventListener('click', this.renameGroup.bind(this));
    }

    buildGroupTree(data) {
        this.tree1.setRootNode(data);
        this.tree1.expandAll();
    }

    buildAttributeTree(data) {
        this.tree2.setRootNode(data);
    }

    async saveSet() {
        const validator = new Validation(this.formEl, { onSubmit: false });
        if (!validator.validate()) {
            return;
        }

        showLoader();

        try {
            const formData = new FormData(this.formEl);

            const data = {
                attributes: [],
                groups: [],
                not_attributes: [],
            };

            for (const [ field, value ] of formData.entries()) {
                if (field !== 'form_key') {
                    data[field] = value;
                    formData.delete(field);
                }
            }

            const tree1 = this.tree1.rootNode.toObject();
            for (const [ i, group ] of Object.entries(tree1.children)) {
                data.groups.push([ group.id, group.text, parseInt(i) + 1, group.store_labels ?? {} ]);
                for (const [ j, attr ] of Object.entries(group.children)) {
                    data.attributes.push([ attr.id, group.id, parseInt(j) + 1, attr.entity_id ]);
                }
            }

            const tree2 = this.tree2.rootNode.toObject();
            for (const attr of tree2.children) {
                if (attr.entity_id) {
                    data.not_attributes.push(attr.entity_id);
                }
            }

            data.removeGroups = this.removeGroups;
            formData.set('data', JSON.stringify(data));

            const result = await mahoFetch(this.config.saveUrl, {
                method: 'POST',
                body: formData,
            });

            setLocation(result.url);

        } catch (error) {
            setMessagesDiv(error.message, 'error');
            hideLoader();
        }
    }

    deleteSet() {
        if (!this.canDeleteSet()) {
            return;
        }
        showLoader();
        this.formEl.action = this.config.deleteUrl;
        this.formEl.submit();
    }

    canDeleteSet() {
        const response = prompt(Translator.translate('Are you sure you want to delete this set? Type "confirm" to proceed.'));
        return response === 'confirm';
    }

    onSelectGroup([node]) {
        const disabled = node?.type !== 'folder';
        if (this.ui.deleteGroupBtn) {
            this.ui.deleteGroupBtn.disabled = disabled;
            this.ui.deleteGroupBtn.classList.toggle('disabled', disabled);
        }
        if (this.ui.renameGroupBtn) {
            this.ui.renameGroupBtn.disabled = disabled;
            this.ui.renameGroupBtn.classList.toggle('disabled', disabled);
        }
    }

    addGroup() {
        this.openGroupDialog();
    }

    renameGroup() {
        const selected = this.tree1.getChecked()[0];
        if (selected) {
            this.openGroupDialog(selected);
        }
    }

    /**
     * Ask for the name of a group and its label in each store view. Without a node, the dialog adds a group.
     *
     * @param {MahoTreeNode} [node]
     */
    openGroupDialog(node = null) {
        const texts = this.config.groupDialog.labels;
        const row = (id, name, className = '', required = false) => `
            <tr class="${className}">
                <td class="label"><label for="group-label-${id}">${escapeHtml(name)}${required ? ' <span class="required">*</span>' : ''}</label></td>
                <td class="value"><input type="text" id="group-label-${id}" data-store-id="${id}" class="input-text" maxlength="255"></td>
            </tr>`;
        const html = `
            <table class="form-list group-labels"><tbody>
                ${row(0, texts.admin, '', true)}
                ${this.config.groupDialog.websites.map((website) => `
                    <tr class="website"><th colspan="2">${escapeHtml(website.name)}</th></tr>
                    ${website.stores.map((store) => row(store.id, store.name, 'store-view')).join('')}`).join('')}
            </tbody></table>
            <p class="note">${escapeHtml(texts.note)}</p>
            <div class="validation-advice" hidden></div>`;

        Dialog.confirm(html, {
            title: node ? texts.renameTitle : texts.addTitle,
            okLabel: texts.ok,
            width: 640,
            onOpen: (dialog) => {
                const labels = node?.attributes.store_labels ?? {};
                for (const input of dialog.querySelectorAll('input[data-store-id]')) {
                    const storeId = input.dataset.storeId;
                    input.value = storeId === '0' ? unescapeHtml(node?.text ?? '') : (labels[storeId] ?? '');
                }
                dialog.querySelector('#group-label-0').focus();
            },
            onOk: (dialog) => {
                const advice = dialog.querySelector('.validation-advice');
                advice.hidden = true;
                const name = escapeHtml(dialog.querySelector('#group-label-0').value.trim());
                let error = '';
                if (name === '') {
                    error = Translator.translate('This is a required field.');
                } else if (!this.validateGroupName(name, node?.id)) {
                    error = Translator.translate('Attribute group with the "%s" name already exists', unescapeHtml(name));
                }
                if (error) {
                    advice.textContent = error;
                    advice.hidden = false;
                    return false;
                }

                const storeLabels = {};
                for (const input of dialog.querySelectorAll('input[data-store-id]')) {
                    if (input.dataset.storeId !== '0' && input.value.trim() !== '') {
                        storeLabels[input.dataset.storeId] = input.value.trim();
                    }
                }

                if (node) {
                    node.updateAttributes({ text: name, store_labels: storeLabels });
                } else {
                    this.tree1.rootNode.prependChild(new MahoTreeNode(this.tree1, {
                        text: name,
                        type: 'folder',
                        allowDrop: true,
                        allowDrag: true,
                        store_labels: storeLabels,
                    }));
                }
            },
        });
    }

    deleteGroup() {
        const selected = this.tree1.getChecked()[0];
        if (!selected) {
            return;
        }

        const result = this.canDeleteGroup(selected) || Translator.translate('Cannot delete group.');
        if (result !== true) {
            return alert(result);
        }

        const animateSortables = [
            Sortable.get(selected.ui.ctNode),
            Sortable.get(this.tree2.rootNode.ui.ctNode),
        ];
        animateSortables.forEach((sortable) => sortable.captureAnimationState());

        this.removeGroups.push(selected.id);
        for (const child of selected.childNodes) {
            this.tree2.rootNode.appendChild(child);
        }
        selected.remove();
        this.sortUnusedAttributes();

        animateSortables.forEach((sortable) => sortable.animateAll());
    }

    validateGroupName(groupName, exceptNodeId) {
        for (const node of this.tree1.rootNode.childNodes) {
            if (node.id != exceptNodeId && node.text.toLowerCase() === groupName.toLowerCase()) {
                return false;
            }
        }
        return true;
    }

    onRemoveAttributeHover({ dragNode }) {
        if (dragNode.type === 'folder') {
            return { icon: 'invalid', message: Translator.translate('Cannot unassign group') };
        }
        const result = this.canRemoveAttribute(dragNode) || Translator.translate('Cannot unassign attribute');
        if (result !== true) {
            return { icon: 'invalid', message: result };
        }
        return { icon: 'delete', message: Translator.translate('Remove attribute from set') };
    }

    onRemoveAttribute({ dragNode }) {

        if (this.canRemoveAttribute(dragNode) !== true) {
            return;
        }
        return () => {
            const target = this.tree2.rootNode.ui.ctNode;
            let referenceNode = null;
            for (referenceNode of target.children) {
                if (referenceNode.dataset.text > dragNode.text) {
                    break;
                }
            }
            target.insertBefore(dragNode.ui.wrap, referenceNode);
        }
    }

    canRemoveAttribute(node) {
        if (!node.attributes.is_user_defined) {
            return Translator.translate('Cannot unassign system attribute');
        }
        return true;
    }

    canDeleteGroup(group) {
        if (group.attributes.has_hidden_system_attributes || group.childNodes.some((node) => !node.attributes.is_user_defined)) {
            return Translator.translate('Cannot delete group. Please move system attributes to another group and try again.');
        }
        return true;
    }

    sortUnusedAttributes() {
        this.tree2.rootNode.sortChildren();
    }
}

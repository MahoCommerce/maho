/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * Class to control the "Manage Categories" page
 */
class CategoryEditForm {

    /**
     * @param {Object} config
     * @param {string} config.treeDiv - DOM ID of div to build tree
     * @param {string} config.containerEl - DOM ID of main page container
     * @param {string} config.loadTreeUrl - URL to load tree nodes
     * @param {string} config.editUrl - URL for edit page action
     * @param {string} config.moveUrl - URL for category move action
     * @param {string} [config.switchTreeUrl] - URL for AJAX store switch
     * @param {string} [config.addRootCategoryBtn] - DOM ID of "Add Root Category" button
     * @param {string} [config.addSubCategoryBtn] - DOM ID of "Add Subcategory" button
     * @param {string} [config.categoryProductsEl] - DOM ID of "Category Products" grid container
     * @param {boolean} [config.useAjax] - Whether to load the main content via AJAX
     * @param {string} [config.tabsJsObjectName] - varien tabs JS variable name
     */
    constructor(config) {
        this.containerEl = document.getElementById(config.containerDiv);
        if (!this.containerEl) {
            throw new Error(`Container with ID ${config.containerDiv} not found in DOM`);
        }

        this.config = {
            treeDiv: null,
            loadTreeUrl: null,
            editUrl: null,
            moveUrl: null,
            switchTreeUrl: null,
            addRootCategoryBtn: null,
            addSubCategoryBtn: null,
            categoryProductsEl: null,
            useAjax: true,
            tabsJsObjectName: null,
            ...config,
        };

        this.ui = {
            addRootCategoryBtn: document.getElementById(this.config.addRootCategoryBtn),
            addSubCategoryBtn: document.getElementById(this.config.addSubCategoryBtn),
        };

        this.initVarienForm();
        this.initProductsGrid();

        this.tree = new MahoTree(this.config.treeDiv, {
            showRootNode: true,
            treatAllNodesAsFolders: true,
            selectable: {
                mode: 'radio',
                showInputs: false,
                onSelect: this.changeCategory.bind(this),
            },
            sortable: {
                onEnd: this.moveCategory.bind(this),
            },
            lazyload: {
                nodeParameter: 'id',
                dataUrl: this.config.loadTreeUrl,
                onBeforeLoad: (node, params) => {
                    if (this.wasExpanded) {
                        params.append('expand_all', '1');
                    }
                },
                onLoadException: (node, error) => {
                    setMessagesDiv(Translator.translate('Error loading children: %s', error), 'error');
                }
            }
        });
    }

    getEditUrl() {
        return setRouteParams(this.config.editUrl, {
            active_tab: window[this.config.tabsJsObjectName]?.activeTab?.name,
        });
    }

    initVarienForm() {
        this.formEl = this.containerEl.querySelector('form');
        if (!this.formEl) {
            throw new Error(`Container with ID ${config.containerDiv} does not contain a form element.`);
        }
        this.varienForm = new varienForm(this.formEl.id);
        if (this.config.useAjax) {
            this.varienForm._submit = this.submitCategory.bind(this);
        }
    }

    initProductsGrid() {
        const { gridJsObjectName, products } = window.productsInfo ?? {};
        const gridObj = window[gridJsObjectName];
        const inputEl = document.getElementById(this.config.categoryProductsEl);

        if (!gridObj || !products || !inputEl) {
            return
        }

        gridObj.updateSelected = () => {
            gridObj.reloadParams = { 'selected_products[]': Object.keys(products) };
            inputEl.value = new URLSearchParams(products).toString();
        }

        gridObj.initRowCallback = (gridObj, row) => {
            const checkboxEl = row.querySelector('.checkbox');
            const positionEl = row.querySelector('.input-text');
            if (checkboxEl && positionEl) {
                positionEl.disabled = !checkboxEl.checked;
                positionEl.addEventListener('change', (event) => {
                    if (checkboxEl.checked) {
                        products[checkboxEl.value] = positionEl.value;
                        gridObj.updateSelected();
                    }
                });
            }
        }

        gridObj.rowClickCallback = (gridObj, event) => {
            if (event.target.closest('td').querySelector('a, input:not([type=checkbox])')) {
                return;
            }
            const checkboxEl = event.target.closest('tr').querySelector('input[type=checkbox]');
            if (checkboxEl) {
                const checked = event.target === checkboxEl ? checkboxEl.checked : !checkboxEl.checked;
                gridObj.setCheckboxChecked(checkboxEl, checked);
            }
        }

        gridObj.checkboxCheckCallback = (gridObj, element, checked) => {
            const positionEl = event.target.closest('tr')?.querySelector('input[name=position]');
            if (positionEl) {
                positionEl.disabled = !checked;
            }
            if (checked) {
                products[element.value] = positionEl?.value ?? 0;
            } else {
                delete products[element.value];
            }
            gridObj.updateSelected();
        }

        gridObj.rows.forEach((row) => gridObj.initRowCallback(gridObj, row));
        gridObj.updateSelected();
    }

    renderTree(config) {
        const { root_visible, can_add_root, category_id, store_id, expanded, ...rest } = config.parameters;

        this.storeId = parseInt(store_id) || 0;
        this.ui.addRootCategoryBtn?.classList.toggle('no-display', !can_add_root);

        this.tree.setRootVisible(root_visible);
        this.tree.setRootNode({
            ...rest,
            children: config.data,
            expanded: true,
        });

        if (expanded) {
            this.expandTree();
        }

        this.tree.getNodeById(category_id)?.select();
    }

    collapseTree() {
        this.wasExpanded = false;
        this.tree.collapseAll();
    }

    expandTree() {
        this.wasExpanded = true;
        this.tree.expandAll();
    }

    getSelectedCategory() {
        return this.tree.getChecked().pop();
    }

    changeCategory() {
        const category = this.getSelectedCategory();
        if (category && (category.id != window.categoryInfo?.category_id || this.storeId != window.categoryInfo?.store_id)) {
            this.updateContent(
                setRouteParams(this.getEditUrl(), {
                    store: this.storeId > 0 ? this.storeId : null,
                    id: category.id,
                })
            );
        }
    }

    resetCategory(url) {
        this.updateContent(
            setRouteParams(url, {
                active_tab: null,
            })
        );
    }

    saveCategory(url) {
        this.varienForm.submit();
    }

    async submitCategory() {
        try {
            const result = await mahoFetch(this.formEl.action, {
                method: 'POST',
                body: new FormData(this.formEl),
            });

            this.updateContent(
                setRouteParams(this.getEditUrl(), {
                    store: this.storeId > 0 ? this.storeId : null,
                    id: result.category_id,
                })
            );
        } catch (error) {
            setMessagesDiv(error.message, 'error');
        }
    }

    async deleteCategory(url) {
        const confirmed = confirm(Translator.translate('Are you sure you want to delete this category?'));
        if (!confirmed) {
            return;
        }
        if (!this.config.useAjax) {
            return setLocation(setRouteParams(url, { form_key: FORM_KEY }));
        }
        try {
            const result = await mahoFetch(url, { method: 'POST' });

            this.tree.getNodeById(result.category_id)?.remove();
            this.tree.getNodeById(result.parent_id)?.select();

        } catch (error) {
            setMessagesDiv(error.message, 'error');
        }
    }

    addCategory(url, isRoot) {
        const parent = isRoot ? { id: 1 } : this.getSelectedCategory();
        if (!parent) {
            alert(Translator.translate('Please select a parent category before adding a new one.'));
            return;
        }
        if (parent.id === 1) {
            this.tree.deselectAll();
        }
        this.updateContent(
            setRouteParams(url, {
                active_tab: null,
                store: this.storeId > 0 ? this.storeId : null,
                parent: parent.id,
            })
        );
    }

    async updateContent(url) {
        if (!this.config.useAjax) {
            return setLocation(url);
        }
        try {
            const result = await mahoFetch(url, { method: 'POST' });

            clearMessagesDiv();

            if (result.title) {
                document.title = result.title;
            }
            if (result.content) {
                updateElementHtmlAndExecuteScripts(this.containerEl, result.content);
            }
            if (result.messages) {
                setMessagesDivHtml(result.messages);
            }

            if (window.categoryInfo) {
                let node = this.tree.rootNode;
                for (const breadcrumb of window.categoryInfo.breadcrumbs) {
                    if (!this.tree.getNodeById(breadcrumb.id)) {
                        await this.tree.expandPath(node.getPath());
                    }
                    if (!this.tree.getNodeById(breadcrumb.id)) {
                        node.appendChild(new MahoTreeNode(this.tree, breadcrumb));
                    }
                    node = this.tree.getNodeById(breadcrumb.id);
                    node.updateAttributes(breadcrumb);
                }
                if (this.ui.addSubCategoryBtn) {
                    this.ui.addSubCategoryBtn.disabled = !window.categoryInfo.can_add_sub;
                }
            }

            window[this.config.tabsJsObjectName]?.moveTabContentInDest();
            this.initVarienForm();
            this.initProductsGrid();

            history.replaceState(null, '', setQueryParams(url, { isAjax: null }));

        } catch (error) {
            setMessagesDiv(error.message, 'error');
        }
    }

    async switchStore(event, switcher) {
        if (switcher.useConfirm) {
            const confirmed = confirm(Translator.translate('Please confirm site switching. All data that hasn\'t been saved will be lost.'));
            if (!confirmed) {
                event.target.value = this.storeId === 0 ? '' : this.storeId;
                return;
            }
        }

        const storeId = parseInt(event.target.value) || 0;
        const category = this.getSelectedCategory();

        if (!this.config.useAjax) {
            return setLocation(
                setRouteParams(this.getEditUrl(), {
                    store: storeId > 0 ? storeId: null,
                    id: category?.id,
                })
            );
        }
        try {
            const url = setRouteParams(this.config.switchTreeUrl, {
                store: storeId > 0 ? storeId: null,
                id: category?.id,
            });

            const result = await mahoFetch(url, { method: 'POST' });
            this.renderTree(result);
            this.changeCategory();

        } catch (error) {
            setMessagesDiv(error.message, 'error');
        }
    }

    async moveCategory(event) {
        if (event.from === event.to && event.oldIndex === event.newIndex) {
            return;
        }
        try {
            const node = this.tree.getNodeByEl(event.item);
            const result = await mahoFetch(this.config.moveUrl, {
                method: 'POST',
                body: new URLSearchParams({
                    id: node.id,
                    pid: node.parentNode.id,
                    aid: node.previousNode?.id ?? 0,
                }),
            });
        } catch (error) {
            setMessagesDiv(error.message, 'error');
        }
    }
}

(function() {
    if (typeof Windows === 'undefined') {
        Windows = {};
    }

    if (typeof Dialog === 'undefined') {
        Dialog = {};
    }

    // Insert CSS for backdrop and dialog layout
    const style = document.createElement('style');
    style.textContent = `
        dialog::backdrop {
            background-color: rgba(0, 0, 0, 0.7);
        }
        dialog {
            border: none;
            border-radius: 6px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
            padding: 0;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 90vw;
            max-height: 90vh;
            width: 80vw;
            height: 80vh;
            display: flex;
            flex-direction: column;
        }
        .dialog-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        .dialog-header h2 {
            margin: 0;
            font-size: 1.25em;
        }
        .dialog-content {
            flex-grow: 1;
            overflow-y: auto;
            padding: 20px;
        }
        .dialog-buttons {
            display: flex;
            justify-content: flex-end;
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
        }
        .dialog-buttons button {
            margin-left: 10px;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            background-color: #f0f0f0;
            cursor: pointer;
        }
        .dialog-buttons button:hover {
            background-color: #e0e0e0;
        }
        #dialog-ok {
            background-color: #4CAF50;
            color: white;
        }
        #dialog-ok:hover {
            background-color: #45a049;
        }
        dialog .x-tree-node>div {height:auto!important}
        dialog .x-tree-node-ct {position:relative !important}
    `;
    document.head.appendChild(style);

    function createDialog(options) {
        const dialog = document.createElement('dialog');
        dialog.innerHTML = `
            <div class="dialog-header popup-window" id="browser_window">
                <h2>${options.title || ''}</h2>
            </div>
            <div class="dialog-content" id="modal_dialog_message">${options.content || ''}</div>
        `;
        if (options.ok || options.cancel) {
            dialog.innerHTML = dialog.innerHTML + `
            <div class="dialog-buttons">
                ${options.cancel ? '<button id="dialog-cancel">Cancel</button>' : ''}
                ${options.ok ? `<button id="dialog-ok">${options.okLabel || "OK"}</button>` : ''}
            </div>
        `;
        }
        document.body.appendChild(dialog);

        // Set width and height if provided
        if (options.width) {
            dialog.style.width = `${options.width}px`;
        }
        if (options.height) {
            dialog.style.height = `${options.height}px`;
        }

        function closeDialog(action) {
            if (action === 'ok' && typeof options.ok === 'function') {
                options.ok();
            } else if (action === 'cancel' && typeof options.cancel === 'function') {
                options.cancel();
            }

            dialog.close();
            dialog.remove();

            if (typeof options.onClose === 'function') {
                options.onClose();
            }
        }

        if (options.ok) {
            dialog.querySelector('#dialog-ok').addEventListener('click', () => closeDialog('ok'));
        }
        if (options.cancel) {
            dialog.querySelector('#dialog-cancel').addEventListener('click', () => closeDialog('cancel'));
        }

        dialog.addEventListener('close', () => {
            if (!dialog.returnValue) {
                closeDialog('cancel');
            }
        });

        dialog.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDialog('cancel');
            }
        });

        dialog.showModal();
        return dialog;
    }

    Dialog.info = function(content, options = {}) {
        return createDialog({ ...options, content, cancel: false });
    };

    Dialog.alert = function(content, options = {}) {
        return createDialog({ ...options, content, cancel: false });
    };

    Dialog.confirm = function(content, options = {}) {
        return createDialog({ ...options, content });
    };

    Windows.close = Dialog.close = function() {
        const openDialog = document.querySelector('dialog[open]');
        if (openDialog) {
            openDialog.close();
            openDialog.remove();
        }
    };
})();
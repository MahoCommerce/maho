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
        body:has(dialog) {
            overflow: hidden;
        }
        dialog::backdrop {
            background: none;
        }
        dialog:last-of-type::backdrop {
            background: rgba(0, 0, 0, 0.7);
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
            overflow: visible;
        }
        .dialog-header {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        .dialog-header h2 {
            flex-grow: 1;
            margin: 0;
            font-size: 1.25em;
        }
        .dialog-content {
            flex-grow: 1;
            overflow-y: auto;
            padding: 20px;
        }
        .dialog-content:has(.wrapper-popup) {
            padding: 0;
        }
        .dialog-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
        }
        .dialog-buttons button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            color: black;
            background-color: #f0f0f0;
            cursor: pointer;
        }
        .dialog-buttons button:hover {
            background-color: #e0e0e0;
        }
        .dialog-buttons button[id$=-ok] {
            background-color: #0090ff;
            color: white;
        }
        .dialog-buttons button[id$=-ok]:hover {
            background-color: #1a9bff;
        }
    `;
    document.head.appendChild(style);

    function createDialog(options) {
        const dialogCount = document.querySelectorAll('dialog').length;
        const dialog = document.createElement('dialog');
        dialog.id = options.id ?? `dialog-${dialogCount + 1}`;
        dialog.options = options;

        dialog.innerHTML = `
            <div class="dialog-header">
                <h2>${options.title || ''}</h2>
                <button title="Close">&times;</button>
            </div>
            <div class="dialog-content"></div>
        `;
        if (options.ok || options.cancel) {
            dialog.innerHTML += `
            <div class="dialog-buttons">
                ${options.cancel ? `<button id="${dialog.id}-cancel">Cancel</button>` : ''}
                ${options.ok ? `<button id="${dialog.id}-ok">${options.okLabel || "OK"}</button>` : ''}
            </div>
        `;
        }

        if (options.content) {
            updateElementHtmlAndExecuteScripts(dialog.querySelector('.dialog-content'), options.content);
        }

        // Set width and height if provided
        if (options.width) {
            dialog.style.width = `${options.width}px`;
        }
        if (options.height) {
            dialog.style.height = `${options.height}px`;
        }

        document.body.appendChild(dialog);

        function closeDialog(action) {
            if (action === 'ok' && typeof options.ok === 'function') {
                options.ok(dialog);
            } else if (action === 'cancel' && typeof options.cancel === 'function') {
                options.cancel(dialog);
            }

            dialog.close();
            dialog.remove();

            if (typeof options.onClose === 'function') {
                options.onClose(dialog);
            }
        }

        if (options.ok) {
            dialog.querySelector(`#${dialog.id}-ok`).addEventListener('click', () => closeDialog('ok'));
        }
        if (options.cancel) {
            dialog.querySelector(`#${dialog.id}-cancel`).addEventListener('click', () => closeDialog('cancel'));
        }

        dialog.querySelector('.dialog-header button').addEventListener('click', () => closeDialog('cancel'));
        dialog.addEventListener('close', () => {
            if (!dialog.returnValue) {
                closeDialog('cancel');
            }
        });

        dialog.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                const openDialogs = document.querySelectorAll('dialog[open]');
                if (openDialogs.length > 0) {
                    const dialog = openDialogs[openDialogs.length - 1];
                    dialog.close();
                    dialog.remove();
                }
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

    Windows.focus = Dialog.focus = function() {};
    Windows.close = Dialog.close = function() {
        const openDialogs = document.querySelectorAll('dialog[open]');
        if (openDialogs.length > 0) {
            const dialog = openDialogs[openDialogs.length - 1];
            dialog.close();
            dialog.remove();
        }
    };
})();

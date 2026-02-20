/**
 * Maho
 *
 * @package     js
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

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
        .dialog-buttons:empty {
            display: none;
        }
    `;
    document.head.appendChild(style);

    function createDialog(options) {
        if (typeof options.onOk === 'function') {
            options.ok = true;
        }
        if (typeof options.onCancel === 'function') {
            options.cancel = true;
        }
        if (typeof options.ok === 'function') {
            options.onOk = options.ok;
            options.ok = true;
        }
        if (typeof options.cancel === 'function') {
            options.onCancel = options.cancel;
            options.cancel = true;
        }

        const dialogCount = document.querySelectorAll('dialog').length;
        const dialog = document.createElement('dialog');
        dialog.id = options.id ?? `dialog-${dialogCount + 1}`;
        dialog.className = options.className ?? 'maho-dialog';
        dialog.options = options;

        dialog.innerHTML = `
            <div class="dialog-header">
                <h2>${options.title || ''}</h2>
                <button title="Close">&times;</button>
            </div>
            <div class="dialog-content" tabindex="-1"></div>
            <div class="dialog-buttons"></div>
        `;

        const buttons = Array.from(options.extraButtons ?? []);
        if (options.cancel) {
            buttons.push({ id: `${dialog.id}-cancel`, class: 'cancel', label: 'Cancel' });
        }
        if (options.ok) {
            buttons.push({ id: `${dialog.id}-ok`, class: 'ok', label: options.okLabel ?? 'OK' });
        }
        for (const button of buttons) {
            const { label, ...attrs } = button;
            const buttonEl = dialog.querySelector('.dialog-buttons').appendChild(document.createElement('button'));
            buttonEl.type = 'button';
            buttonEl.textContent = label;
            for (const [key, val] of Object.entries(attrs)) {
                buttonEl.setAttribute(key, val);
            }
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

        async function closeDialog(action) {
            if (action === 'ok' && typeof options.onOk === 'function') {
                const result = await options.onOk(dialog);
                if (result === false) {
                    return;
                }
            } else if (action === 'cancel' && typeof options.onCancel === 'function') {
                options.onCancel(dialog);
            }

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
            if (dialog.returnValue) {
                closeDialog('ok');
            } else {
                closeDialog('cancel');
            }
        });

        dialog.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeDialog('cancel');
            }
        });

        dialog.showModal();
        dialog.querySelector('.dialog-content')?.focus();


        if (typeof options.onOpen === 'function') {
            queueMicrotask(() => {
                options.onOpen(dialog);
            });
        }

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

    Windows.focus = Dialog.focus = function() {
        const dialog = [...document.querySelectorAll('dialog[open]')].pop();
        dialog?.querySelector('.dialog-content')?.focus();
    };
    Windows.close = Dialog.close = function(returnValue) {
        const dialog = [...document.querySelectorAll('dialog[open]')].pop();
        dialog?.close(returnValue);
    };
})();

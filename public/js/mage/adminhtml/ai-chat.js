// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

/**
 * The admin assistant panel: a deep-chat surface wired to the streaming chat endpoint.
 *
 * Text answers stream into a text bubble. Tool calls become small step cards. A write
 * tool pauses the turn: the server sends a `confirm` event, the panel shows a card with
 * the planned changes, and the administrator's decision starts a second stream.
 */
class MahoAiAssistant {
    static STORAGE_OPEN = 'maho_ai_chat_open';
    static STORAGE_PREFILL = 'maho_ai_chat_prefill';
    static STORAGE_CONVERSATION = 'maho_ai_chat_conversation';

    constructor(config) {
        this.config = config;
        this.labels = config.labels;
        this.panel = document.getElementById('ai-chat-panel');
        this.toggle = document.getElementById('ai-chat-toggle');
        this.chat = document.getElementById('ai-chat');
        this.picker = document.getElementById('ai-chat-conversations');
        this.deleteButton = document.getElementById('ai-chat-delete');
        this.conversationId = null;
        this.abortController = null;
        this.pendingCard = null;
        this.setupChat();
        this.bindEvents();
        this.restoreState();
        this.applyPrefill();
    }

    setupChat() {
        const chat = this.chat;
        // deep-chat renders inside a shadow root, so the page stylesheet does not reach
        // the step and confirmation cards; load it inside the shadow root as well.
        chat.onComponentRender = () => {
            if (chat.shadowRoot && !chat.shadowRoot.querySelector('link[data-ai-chat]')) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = this.config.cssUrl;
                link.dataset.aiChat = '1';
                chat.shadowRoot.appendChild(link);
            }
        };
        chat.textInput = { placeholder: { text: this.labels.placeholder } };
        chat.introMessage = { html: this.renderIntro() };
        chat.messageStyles = {
            default: {
                ai: { bubble: { maxWidth: '94%', padding: '.6em .8em' } },
                user: { bubble: { maxWidth: '85%' } },
            },
            html: { shared: { bubble: { backgroundColor: 'transparent', padding: '0', maxWidth: '100%', width: '100%' } } },
        };
        chat.htmlClassUtilities = {
            'ai-chat-approve': { events: { click: (event) => this.onApprove(event) } },
            'ai-chat-deny': { events: { click: (event) => this.onDeny(event) } },
            'ai-chat-example': { events: { click: (event) => this.chat.submitUserMessage({ text: event.currentTarget.dataset.text }) } },
        };
        chat.connect = {
            stream: true,
            handler: (body, signals) => this.handleSubmit(body, signals),
        };
        chat.onError = (error) => console.error('[ai-chat]', error);
    }

    bindEvents() {
        this.toggle.addEventListener('click', () => this.setOpen(this.panel.hidden));
        document.getElementById('ai-chat-close').addEventListener('click', () => this.setOpen(false));
        document.getElementById('ai-chat-new').addEventListener('click', () => this.newConversation());
        this.deleteButton.addEventListener('click', () => this.deleteConversation());
        this.picker.addEventListener('change', () => {
            const id = parseInt(this.picker.value, 10);
            if (id > 0) {
                this.loadConversation(id);
            } else {
                this.newConversation();
            }
        });
        document.addEventListener('keydown', (event) => {
            // event.code names the physical key: on a Mac, Option+A reports the key as "å".
            if (event.altKey && !event.ctrlKey && !event.metaKey && event.code === 'KeyA') {
                event.preventDefault();
                this.setOpen(this.panel.hidden);
            }
            if (event.key === 'Escape' && !this.panel.hidden) {
                this.setOpen(false);
            }
        });
    }

    restoreState() {
        let open = false;
        let conversationId = 0;
        try {
            open = localStorage.getItem(MahoAiAssistant.STORAGE_OPEN) === '1';
            conversationId = parseInt(sessionStorage.getItem(MahoAiAssistant.STORAGE_CONVERSATION) ?? '0', 10);
        } catch (e) {
            // storage can be unavailable; the panel starts closed
        }
        this.loadConversations().then(() => {
            if (conversationId > 0 && [...this.picker.options].some((o) => parseInt(o.value, 10) === conversationId)) {
                this.loadConversation(conversationId);
            }
        });
        if (open) {
            this.setOpen(true);
        }
    }

    setOpen(open) {
        this.panel.hidden = !open;
        this.toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.classList.toggle('ai-chat-open', open);
        try {
            localStorage.setItem(MahoAiAssistant.STORAGE_OPEN, open ? '1' : '0');
        } catch (e) {
            // ignore
        }
        if (open) {
            this.chat.focusInput();
        }
    }

    rememberConversation(id) {
        this.conversationId = id;
        this.deleteButton.hidden = !id;
        try {
            if (id) {
                sessionStorage.setItem(MahoAiAssistant.STORAGE_CONVERSATION, String(id));
            } else {
                sessionStorage.removeItem(MahoAiAssistant.STORAGE_CONVERSATION);
            }
        } catch (e) {
            // ignore
        }
        if (this.picker.value !== String(id ?? '')) {
            this.picker.value = id ? String(id) : '';
        }
    }

    // --- conversations -------------------------------------------------------------

    async loadConversations() {
        try {
            const data = await mahoFetch(this.config.listUrl, { loaderArea: false });
            const current = this.conversationId;
            this.picker.replaceChildren();
            const first = document.createElement('option');
            first.value = '';
            first.textContent = this.labels.newChat;
            this.picker.appendChild(first);
            for (const conversation of data.conversations ?? []) {
                const option = document.createElement('option');
                option.value = String(conversation.id);
                option.textContent = (conversation.pending ? '⏳ ' : '') + (conversation.title || this.labels.untitled);
                this.picker.appendChild(option);
            }
            this.picker.value = current ? String(current) : '';
        } catch (error) {
            console.error('[ai-chat]', error);
        }
    }

    newConversation() {
        if (this.abortController) {
            this.abortController.abort();
        }
        this.rememberConversation(null);
        this.pendingCard = null;
        this.chat.clearMessages(true);
        this.chat.focusInput();
        setTimeout(() => this.updateIntro(false), 100);
    }

    /** The greeting card shrinks to one line once the conversation has messages. */
    updateIntro(hasMessages) {
        this.chat.shadowRoot?.querySelector('.ai-chat-intro')?.classList.toggle('ai-chat-intro-compact', hasMessages);
    }

    async loadConversation(id) {
        try {
            const url = new URL(this.config.messagesUrl, window.location.href);
            url.searchParams.set('id', String(id));
            const data = await mahoFetch(url, { loaderArea: false });
            this.rememberConversation(data.conversation.id);
            this.pendingCard = null;
            this.chat.clearMessages(true);
            const pending = [];
            const history = [];
            for (const message of data.messages) {
                if (message.role === 'user') {
                    history.push({ role: 'user', text: message.content });
                } else if (message.role === 'assistant') {
                    if (message.content) {
                        history.push({ role: 'ai', text: message.content });
                    }
                } else if (message.role === 'tool') {
                    const tool = message.tool;
                    if (tool.status === 'pending') {
                        pending.push({ id: tool.id, name: tool.name, title: tool.name, arguments: tool.arguments, destructive: false });
                    } else {
                        history.push({ role: 'ai', html: this.renderStep({ id: tool.id, name: tool.name, title: tool.name, arguments: tool.arguments, read_only: !tool.is_write }, { status: tool.status }) });
                    }
                }
            }
            if (pending.length > 0) {
                history.push({ role: 'ai', html: this.renderConfirmCard(pending) });
                this.pendingCard = { index: history.length - 1, calls: pending };
            }
            this.chat.history = history;
            setTimeout(() => this.updateIntro(history.length > 0), 100);
            setTimeout(() => this.chat.scrollToBottom(), 50);
        } catch (error) {
            console.error('[ai-chat]', error);
        }
    }

    async deleteConversation() {
        if (!this.conversationId || !window.confirm(this.labels.deleteConfirm)) {
            return;
        }
        try {
            const body = new URLSearchParams({ id: String(this.conversationId) });
            await mahoFetch(this.config.deleteUrl, { method: 'POST', body, loaderArea: false });
            this.newConversation();
            await this.loadConversations();
        } catch (error) {
            console.error('[ai-chat]', error);
        }
    }

    // --- one turn ------------------------------------------------------------------

    /**
     * deep-chat calls this for every submitted message. The stream is driven by hand: text
     * and cards are added with addMessage/updateMessage, and the final onResponse only tells
     * deep-chat that the turn is over.
     */
    async handleSubmit(body, signals) {
        const text = body.messages?.at(-1)?.text ?? '';
        signals.onOpen();
        this.abortController = new AbortController();
        signals.stopClicked.listener = () => this.abortController?.abort();
        this.updateIntro(true);

        const outcome = await this.streamTurn(this.config.chatUrl, {
            conversation_id: this.conversationId,
            message: text,
            context: { ...this.config.context, screen: this.screenDigest() },
            form_key: this.config.formKey,
        });

        if (outcome.error) {
            await signals.onResponse({ html: this.renderError(outcome.error) });
        } else {
            await signals.onResponse({ html: '<span class="ai-chat-turn-end"></span>' });
        }
        signals.onClose();
        this.abortController = null;
        if (outcome.newConversation) {
            await this.loadConversations();
        }
    }

    async confirm(decisions) {
        if (!this.conversationId || this.abortController) {
            return;
        }
        this.abortController = new AbortController();
        this.chat.disableSubmitButton(true);
        const outcome = await this.streamTurn(this.config.confirmUrl, {
            conversation_id: this.conversationId,
            decisions,
            context: this.config.context,
            form_key: this.config.formKey,
        });
        if (outcome.error) {
            this.chat.addMessage({ html: this.renderError(outcome.error), role: 'ai' });
        }
        this.chat.disableSubmitButton(false);
        this.abortController = null;
        this.loadConversations();
    }

    /**
     * POST the payload and render the server-sent events as they arrive.
     * Native fetch on purpose: mahoFetch reads the whole body, and this one is a stream.
     */
    async streamTurn(url, payload) {
        const state = { textIndex: null, text: '', steps: new Map(), error: null, done: false, navigateTo: null, pageAction: null, thinkingIndex: null, newConversation: !this.conversationId };
        let response;
        try {
            response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream' },
                body: JSON.stringify(payload),
                signal: this.abortController?.signal,
            });
        } catch (error) {
            state.error = error.name === 'AbortError' ? this.labels.stopped : this.labels.error;
            return state;
        }

        if (!response.ok) {
            let message = this.labels.error;
            try {
                const data = await response.json();
                message = data.message || data.error || data.detail || message;
            } catch (e) {
                // keep the generic message
            }
            state.error = message;
            return state;
        }

        try {
            await this.readEvents(response.body, (event, data) => this.onEvent(state, event, data));
            if (!state.done && !state.error) {
                // The server stopped without a final event: a crashed or killed request.
                state.error = this.labels.error;
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                this.addText(state, '\n\n_' + this.labels.stopped + '_');
            } else {
                console.error('[ai-chat]', error);
                state.error = this.labels.error;
            }
        }
        this.hideThinking(state);
        return state;
    }

    async readEvents(body, onEvent) {
        const reader = body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        while (true) {
            const { value, done } = await reader.read();
            if (done) {
                break;
            }
            buffer += decoder.decode(value, { stream: true });
            let separator;
            while ((separator = buffer.indexOf('\n\n')) !== -1) {
                const chunk = buffer.slice(0, separator);
                buffer = buffer.slice(separator + 2);
                this.dispatchChunk(chunk, onEvent);
            }
        }
        if (buffer.trim() !== '') {
            this.dispatchChunk(buffer, onEvent);
        }
    }

    dispatchChunk(chunk, onEvent) {
        let event = 'message';
        const dataLines = [];
        for (const line of chunk.split('\n')) {
            if (line.startsWith('event:')) {
                event = line.slice(6).trim();
            } else if (line.startsWith('data:')) {
                dataLines.push(line.slice(5).replace(/^ /, ''));
            }
        }
        if (dataLines.length === 0) {
            return;
        }
        let data = {};
        try {
            data = JSON.parse(dataLines.join('\n'));
        } catch (e) {
            return;
        }
        onEvent(event, data);
    }

    onEvent(state, event, data) {
        if (event !== 'tool_result' && event !== 'navigate') {
            this.hideThinking(state);
        }
        switch (event) {
            case 'delta':
                this.addText(state, data.text ?? '');
                break;
            case 'replace':
                if (state.textIndex !== null) {
                    state.text = data.text ?? '';
                    this.chat.updateMessage({ text: state.text }, state.textIndex);
                }
                break;
            case 'tool_call': {
                state.textIndex = null;
                state.text = '';
                this.chat.addMessage({ html: this.renderStep(data, { status: 'running' }), role: 'ai' });
                state.steps.set(data.id, { index: this.lastIndex(), call: data });
                break;
            }
            case 'tool_result': {
                const step = state.steps.get(data.id);
                if (step) {
                    const status = data.denied ? 'denied' : (data.ok ? 'done' : 'error');
                    this.chat.updateMessage({ html: this.renderStep(step.call, { status, preview: data.preview }) }, step.index);
                } else if (data.denied) {
                    this.chat.addMessage({ html: this.renderStep({ id: data.id, name: '', title: '', arguments: {} }, { status: 'denied' }), role: 'ai' });
                }
                this.showThinking(state);
                break;
            }
            case 'confirm':
                state.textIndex = null;
                this.showConfirmCard(data.calls ?? []);
                break;
            case 'error':
                state.error = data.message ?? this.labels.error;
                break;
            case 'navigate':
                state.navigateTo = data.url ? { url: data.url, fields: data.fields ?? null } : null;
                break;
            case 'page_action':
                state.pageAction = data.steps ?? null;
                break;
            case 'done':
                state.done = true;
                if (data.conversation_id) {
                    this.rememberConversation(data.conversation_id);
                }
                if (state.navigateTo && data.state === 'complete') {
                    const { url, fields } = state.navigateTo;
                    if (fields) {
                        this.rememberPrefill(url, fields);
                    }
                    setTimeout(() => window.location.assign(url), 800);
                } else if (state.pageAction && data.state === 'complete') {
                    const steps = state.pageAction;
                    setTimeout(() => this.performPageActions(steps), 800);
                }
                break;
        }
    }

    addText(state, text) {
        if (text === '') {
            return;
        }
        state.text += text;
        if (state.textIndex === null) {
            this.chat.addMessage({ text: state.text, role: 'ai' });
            state.textIndex = this.lastIndex();
        } else {
            this.chat.updateMessage({ text: state.text }, state.textIndex);
        }
    }

    showThinking(state) {
        if (state.thinkingIndex !== null) {
            return;
        }
        this.chat.addMessage({ html: `<div class="ai-chat-thinking">${this.escape(this.labels.thinking)}</div>`, role: 'ai' });
        state.thinkingIndex = this.lastIndex();
        setTimeout(() => this.chat.scrollToBottom(), 50);
    }

    hideThinking(state) {
        if (state.thinkingIndex === null) {
            return;
        }
        this.chat.updateMessage({ html: '<span class="ai-chat-turn-end"></span>' }, state.thinkingIndex);
        state.thinkingIndex = null;
    }

    // --- screen digest ----------------------------------------------------------------
    // A text description of the page the administrator sees, sent with each message, so
    // the model can answer "where is the slideshow button" for this page, this tab.

    screenDigest() {
        const lines = [];
        const text = (node) => (node?.textContent ?? '').replace(/\s+/g, ' ').trim();
        const clip = (value, max) => (value.length > max ? value.slice(0, max - 1) + '…' : value);
        const visible = (node) => !!node && node.getClientRects().length > 0 && !node.closest('#ai-chat-panel, .ai-chat-toggle, [hidden]');
        const heading = document.querySelector('.content-header h3, .content-header h1, h1');
        lines.push('Page: ' + clip(text(heading) || document.title, 120));

        const messages = [...document.querySelectorAll('#messages li, .message-popup .message')].filter(visible).map((m) => clip(text(m), 200));
        if (messages.length > 0) {
            lines.push('Messages: ' + messages.slice(0, 4).join(' | '));
        }

        const tabs = [...document.querySelectorAll('a.tab-item-link')].filter(visible);
        let scope = document;
        if (tabs.length > 0) {
            const active = tabs.find((t) => t.classList.contains('active'));
            lines.push('Tabs: ' + tabs.map((t) => (t === active ? '[' + text(t) + ']' : text(t))).join(', ') + (active ? ' (the one in brackets is open)' : ''));
            scope = (active && document.getElementById(active.id + '_content')) || document;
        }

        const fields = [];
        for (const element of scope.querySelectorAll('input, select, textarea')) {
            if (fields.length >= 40 || !visible(element) || ['hidden', 'password', 'submit', 'button', 'file'].includes(element.type) || element.closest('.grid, #ai-chat-panel')) {
                continue;
            }
            const label = text(element.id ? document.querySelector(`label[for="${CSS.escape(element.id)}"]`) : null) || text(element.closest('tr')?.querySelector('td.label, th')) || element.name || element.id;
            let value;
            if (element.tagName === 'SELECT') {
                value = [...element.selectedOptions].map((o) => text(o)).join(', ');
            } else if (element.type === 'checkbox' || element.type === 'radio') {
                if (!element.checked) {
                    continue;
                }
                value = 'checked';
            } else {
                value = element.value;
            }
            const editor = element.tagName === 'TEXTAREA' ? window.tiptapEditors?.get(element.id) : null;
            fields.push(`${clip(label, 40)} = ${clip(String(value ?? ''), editor ? 400 : 80)}${editor ? ' (rich text editor)' : ''}`);
        }
        if (fields.length > 0) {
            lines.push('Fields: ' + fields.join('; '));
        }

        const toolbar = [...scope.querySelectorAll('.tiptap-toolbar button[title], .tiptap-toolbar select[title]')].filter(visible).map((b) => b.title);
        if (toolbar.length > 0) {
            lines.push('Editor toolbar buttons: ' + [...new Set(toolbar)].join(', '));
        }

        const buttons = [...document.querySelectorAll('button, a.button, input[type="submit"]')]
            .filter((b) => visible(b) && !b.closest('.tiptap-toolbar, .grid, #ai-chat-panel'))
            .map((b) => text(b) || b.title || b.value).filter(Boolean);
        if (buttons.length > 0) {
            lines.push('Page buttons: ' + [...new Set(buttons)].slice(0, 40).join(', '));
        }

        const grid = document.querySelector('.grid table');
        if (grid && visible(grid)) {
            const columns = [...grid.querySelectorAll('thead th')].map(text).filter(Boolean);
            const rows = grid.querySelectorAll('tbody tr').length;
            lines.push(`Grid: ${rows} rows on this page, columns: ${columns.join(', ')}`);
            const total = text(document.querySelector('.grid-widget .pager, .pager'));
            if (total) {
                lines.push('Grid paging: ' + clip(total, 120));
            }
        }

        return clip(lines.join('\n'), 4000);
    }

    // --- page actions -----------------------------------------------------------------
    // The model names a button, a tab or a field as the screen digest listed it. Only
    // visible elements qualify, so the model cannot reach what the administrator does not see.

    /** Steps run in order. A click is always the last one: the page may reload after it. */
    performPageActions(steps) {
        const done = [];
        const missing = [];
        let clicked = null;
        for (const step of steps) {
            const outcome = this.performPageAction(step);
            (outcome.ok ? done : missing).push(step.target);
            if (outcome.click) {
                clicked = outcome.click;
                break;
            }
        }
        const notes = [];
        if (done.length > 0) {
            notes.push(this.labels.actionDone.replace('%s', done.join(', ')));
        }
        if (missing.length > 0) {
            notes.push(this.labels.actionNotFound.replace('%s', missing.join(', ')));
        }
        this.chat.addMessage({ html: `<div class="ai-chat-note">${this.escape(notes.join(' '))}</div>`, role: 'ai' });
        if (clicked) {
            this.flash(clicked);
            setTimeout(() => clicked.click(), 300);
        }
    }

    /** @return {{ok: boolean, click?: Element}} */
    performPageAction(action) {
        const target = String(action.target ?? '').trim();
        const normalize = (text) => String(text ?? '').replace(/\s+/g, ' ').trim().toLowerCase();
        const visible = (node) => !!node && node.getClientRects().length > 0 && !node.closest('#ai-chat-panel, .ai-chat-toggle, [hidden]');
        const matches = (candidates, label) => {
            const wanted = normalize(target);
            const exact = candidates.find((c) => normalize(label(c)) === wanted);
            return exact ?? candidates.find((c) => normalize(label(c)).includes(wanted));
        };
        if (action.action === 'click') {
            const buttons = [...document.querySelectorAll('button, a.button, input[type="submit"], .tiptap-toolbar button[title]')].filter(visible);
            const button = matches(buttons, (b) => b.textContent || b.title || b.value);
            return button ? { ok: true, click: button } : { ok: false };
        }
        if (action.action === 'open_tab') {
            const tabs = [...document.querySelectorAll('a.tab-item-link')].filter(visible);
            const tab = matches(tabs, (t) => t.textContent);
            if (tab) {
                tab.click();
                this.flash(tab);
            }
            return { ok: !!tab };
        }
        if (action.action === 'set_field') {
            const field = this.findField(target) || this.findFieldByLabel(target);
            if (field) {
                this.showTabOf(field);
                this.setFieldValue(field, action.value ?? '');
                field.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
            return { ok: !!field };
        }
        return { ok: false };
    }

    findFieldByLabel(text) {
        const wanted = String(text).replace(/\s+/g, ' ').trim().toLowerCase().replace(/\s*\*$/, '');
        for (const label of document.querySelectorAll('label[for]')) {
            if (label.textContent.replace(/\s+/g, ' ').trim().toLowerCase().replace(/\s*\*$/, '') === wanted) {
                const field = document.getElementById(label.htmlFor);
                if (field && ['INPUT', 'TEXTAREA', 'SELECT'].includes(field.tagName)) {
                    return field;
                }
            }
        }
        return null;
    }

    flash(element) {
        element.classList.add('ai-chat-prefilled');
        setTimeout(() => element.classList.remove('ai-chat-prefilled'), 1500);
    }

    // --- form prefill -----------------------------------------------------------------
    // A fill tool hands the panel a page URL and field values. The values wait in session
    // storage across the navigation, and the panel fills the form once the page is loaded.

    rememberPrefill(url, fields) {
        try {
            sessionStorage.setItem(MahoAiAssistant.STORAGE_PREFILL, JSON.stringify({ url, fields }));
        } catch (e) {
            // without storage the page opens empty
        }
    }

    applyPrefill() {
        let prefill = null;
        try {
            prefill = JSON.parse(sessionStorage.getItem(MahoAiAssistant.STORAGE_PREFILL) ?? 'null');
            sessionStorage.removeItem(MahoAiAssistant.STORAGE_PREFILL);
        } catch (e) {
            return;
        }
        if (!prefill || !prefill.fields || !this.samePage(prefill.url, window.location.href)) {
            return;
        }
        const run = () => this.fillForm(prefill.fields, 0);
        if (document.readyState === 'complete') {
            setTimeout(run, 300);
        } else {
            window.addEventListener('load', () => setTimeout(run, 300), { once: true });
        }
    }

    /** Same admin page: path without the secret key segment and without the trailing slash. */
    samePage(a, b) {
        const strip = (href) => {
            try {
                return new URL(href, window.location.href).pathname.replace(/\/key\/[^/]+/, '').replace(/\/+$/, '');
            } catch (e) {
                return '';
            }
        };
        return strip(a) !== '' && strip(a) === strip(b);
    }

    fillForm(fields, attempt) {
        const filled = [];
        const missing = [];
        const retry = [];
        for (const [name, value] of Object.entries(fields)) {
            const element = this.findField(name);
            if (!element) {
                missing.push(name);
                continue;
            }
            if (element.tagName === 'TEXTAREA' && element.id && window.tiptapEditors === undefined && attempt < 10) {
                retry.push(name);
                continue;
            }
            this.setFieldValue(element, value);
            filled.push(name);
        }
        if (retry.length > 0) {
            const pending = Object.fromEntries(retry.map((n) => [n, fields[n]]));
            setTimeout(() => this.fillForm(pending, attempt + 1), 300);
        }
        if (filled.length === 0 && missing.length === 0) {
            return;
        }
        this.setOpen(true);
        const notes = [];
        if (filled.length > 0) {
            notes.push(this.labels.formFilled.replace('%s', filled.join(', ')));
        }
        if (missing.length > 0) {
            notes.push(this.labels.formFieldsMissing.replace('%s', missing.join(', ')));
        }
        const show = () => this.chat.addMessage({ html: `<div class="ai-chat-note">${this.escape(notes.join(' '))}</div>`, role: 'ai' });
        customElements.whenDefined('deep-chat').then(() => setTimeout(show, 400));
        if (filled.length > 0) {
            const first = this.findField(filled[0]);
            this.showTabOf(first);
            setTimeout(() => first?.scrollIntoView({ block: 'center', behavior: 'smooth' }), 150);
        }
    }

    /** Admin edit forms keep every tab in the page; the tab link of the field's content pane opens it. */
    showTabOf(element) {
        // A field id can end in "_content" too (page_content), so every ancestor is a candidate.
        for (let pane = element?.parentElement; pane; pane = pane.parentElement) {
            if (!pane.id || !pane.id.endsWith('_content')) {
                continue;
            }
            const link = document.getElementById(pane.id.slice(0, -'_content'.length));
            if (link?.classList.contains('tab-item-link')) {
                if (!link.classList.contains('active')) {
                    link.click();
                }
                return;
            }
        }
    }

    /** The form control for an API field name: "contentHeading" matches name="content_heading", "product[content_heading]" or "content_heading[]". */
    findField(name) {
        const snake = name.replace(/([a-z0-9])([A-Z])/g, '$1_$2').toLowerCase();
        const candidates = [...new Set([name, snake])];
        for (const field of candidates) {
            const escaped = CSS.escape(field);
            const selectors = [`[name="${escaped}"]`, `[name$="[${escaped}]"]`, `[name="${escaped}[]"]`, `[name$="[${escaped}][]"]`];
            for (const selector of selectors) {
                const element = [...document.querySelectorAll(selector)].find((e) => ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.tagName) && e.type !== 'hidden' && !e.disabled);
                if (element) {
                    return element;
                }
            }
        }
        return null;
    }

    setFieldValue(element, value) {
        // {prepend} or {append} adds to the current value, so a long field is never resent.
        if (value && typeof value === 'object' && !Array.isArray(value) && ('prepend' in value || 'append' in value)) {
            const current = element.value ?? '';
            value = String(value.prepend ?? '') + current + String(value.append ?? '');
        }
        const asList = Array.isArray(value) ? value.map(String) : String(value ?? '').split(',').map((v) => v.trim());
        if (element.tagName === 'SELECT' && element.multiple) {
            for (const option of element.options) {
                option.selected = asList.includes(option.value);
            }
        } else if (element.type === 'checkbox') {
            element.checked = value === true || value === 1 || value === '1' || value === 'true';
        } else if (element.type === 'radio') {
            const group = document.querySelectorAll(`input[type="radio"][name="${CSS.escape(element.name)}"]`);
            for (const radio of group) {
                radio.checked = radio.value === String(value);
            }
        } else {
            const text = typeof value === 'boolean' ? (value ? '1' : '0') : (value === null ? '' : (typeof value === 'object' ? JSON.stringify(value) : String(value)));
            element.value = text;
            const editor = element.tagName === 'TEXTAREA' ? window.tiptapEditors?.get(element.id) : null;
            if (editor) {
                editor.syncPlainToWysiwyg();
                editor.updateContentNotice?.();
                editor.wrapper?.classList.add('ai-chat-prefilled');
            }
        }
        element.dispatchEvent(new Event('input', { bubbles: true }));
        element.dispatchEvent(new Event('change', { bubbles: true }));
        element.classList.add('ai-chat-prefilled');
    }

    lastIndex() {
        return this.chat.getMessages().length - 1;
    }

    shortcutLabel() {
        const mac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
        return mac ? '\u2325 Option+A' : 'Alt+A';
    }

    renderIntro() {
        const greeting = this.config.adminName
            ? this.labels.introGreeting.replace('%s', this.escape(this.config.adminName))
            : this.labels.introGreetingAnonymous;
        const examples = (this.config.examples ?? [])
            .map((text) => `<button type="button" class="ai-chat-example" data-text="${this.escape(text)}">${this.escape(text)}</button>`)
            .join('');
        return `<div class="ai-chat-intro">`
            + `<div class="ai-chat-intro-icon">${this.config.introIcon ?? ''}</div>`
            + `<h3 class="ai-chat-intro-title">${greeting}</h3>`
            + `<p class="ai-chat-intro-text">${this.escape(this.labels.intro)}</p>`
            + (examples ? `<p class="ai-chat-intro-label">${this.escape(this.labels.introExamples)}</p><div class="ai-chat-intro-examples">${examples}</div>` : '')
            + `<p class="ai-chat-intro-hint">${this.escape(this.labels.introHint.replace('%s', this.shortcutLabel()))}</p>`
            + `</div>`;
    }

    // --- cards ---------------------------------------------------------------------

    renderStep(call, result) {
        const labels = {
            running: this.labels.running,
            done: this.labels.done,
            error: this.labels.failed,
            denied: this.labels.denied,
            cancelled: this.labels.cancelled,
            pending: this.labels.pending,
        };
        const status = result.status ?? 'done';
        const title = call.title && call.title !== call.name ? call.title : this.humanizeTool(call.name);
        const args = this.renderArguments(call.arguments);
        const preview = result.preview ? `<pre class="ai-chat-step-preview">${this.escape(this.prettyPreview(result.preview))}</pre>` : '';
        return `<details class="ai-chat-step ai-chat-step-${this.escape(status)}">`
            + `<summary><span class="ai-chat-step-status">${this.escape(labels[status] ?? status)}</span>`
            + `<span class="ai-chat-step-title">${this.escape(title)}</span>`
            + (call.destructive ? `<span class="ai-chat-badge">${this.escape(this.labels.destructive)}</span>` : '')
            + `</summary>`
            + args
            + preview
            + `</details>`;
    }

    showConfirmCard(calls) {
        this.chat.addMessage({ html: this.renderConfirmCard(calls), role: 'ai' });
        this.pendingCard = { index: this.lastIndex(), calls };
        setTimeout(() => this.chat.scrollToBottom(), 50);
    }

    renderConfirmCard(calls) {
        const rows = calls.map((call) => {
            const title = call.title && call.title !== call.name ? call.title : this.humanizeTool(call.name);
            return `<li class="ai-chat-confirm-row">`
                + `<label><input type="checkbox" class="ai-chat-confirm-check" data-id="${this.escape(call.id)}" checked> `
                + `<strong>${this.escape(title)}</strong>`
                + (call.destructive ? ` <span class="ai-chat-badge">${this.escape(this.labels.destructive)}</span>` : '')
                + `</label>`
                + this.renderArguments(call.arguments)
                + `</li>`;
        }).join('');
        const html = `<div class="ai-chat-confirm" data-ids="${this.escape(calls.map((c) => c.id).join(','))}">`
            + `<p class="ai-chat-confirm-title">${this.escape(this.labels.confirmTitle)}</p>`
            + `<ul class="ai-chat-confirm-list">${rows}</ul>`
            + `<div class="ai-chat-confirm-actions">`
            + `<button type="button" class="ai-chat-approve">${this.escape(calls.length > 1 ? this.labels.approveSelected : this.labels.approve)}</button>`
            + `<button type="button" class="ai-chat-deny">${this.escape(this.labels.deny)}</button>`
            + `</div></div>`;
        return html;
    }

    onApprove(event) {
        const card = event.target.closest('.ai-chat-confirm');
        if (!card || !this.pendingCard) {
            return;
        }
        const decisions = {};
        for (const call of this.pendingCard.calls) {
            const check = card.querySelector(`.ai-chat-confirm-check[data-id="${CSS.escape(call.id)}"]`);
            decisions[call.id] = check ? check.checked : true;
        }
        this.settleCard(decisions);
    }

    onDeny() {
        if (!this.pendingCard) {
            return;
        }
        const decisions = {};
        for (const call of this.pendingCard.calls) {
            decisions[call.id] = false;
        }
        this.settleCard(decisions);
    }

    settleCard(decisions) {
        const card = this.pendingCard;
        this.pendingCard = null;
        const summary = card.calls.map((call) => {
            const title = call.title && call.title !== call.name ? call.title : this.humanizeTool(call.name);
            const verdict = decisions[call.id] ? this.labels.approved : this.labels.denied;
            return `<li>${this.escape(verdict)}: ${this.escape(title)}</li>`;
        }).join('');
        this.chat.updateMessage({ html: `<div class="ai-chat-confirm ai-chat-confirm-settled"><ul>${summary}</ul></div>` }, card.index);
        this.confirm(decisions);
    }

    renderError(message) {
        return `<div class="ai-chat-error">${this.escape(message)}</div>`;
    }

    // --- helpers -------------------------------------------------------------------

    /** "content_cms_pages_update" reads as "Update cms pages": the verb first, without the section. */
    humanizeTool(name) {
        const local = { admin_open_page: this.labels.openPage, admin_fill_form: this.labels.fillForm, admin_page_action: this.labels.pageAction, enable_tools: this.labels.loadTools };
        if (local[name]) {
            return local[name];
        }
        const parts = String(name ?? '').split('_').filter(Boolean);
        const verbs = { list: this.labels.verbList, get: this.labels.verbGet, create: this.labels.verbCreate, update: this.labels.verbUpdate, delete: this.labels.verbDelete };
        const verb = verbs[parts[parts.length - 1]];
        if (parts.length > 2 && verb) {
            return `${verb} ${parts.slice(1, -1).join(' ')}`;
        }
        return parts.join(' ');
    }

    humanizeKey(key) {
        const words = String(key).replace(/([a-z0-9])([A-Z])/g, '$1 $2').replace(/_/g, ' ').toLowerCase();
        return words.charAt(0).toUpperCase() + words.slice(1);
    }

    /**
     * One row per argument. A short value sits next to its label. A long value takes the
     * full width below it. HTML is shown as the page would render it, with the source folded.
     */
    renderArguments(args) {
        if (!args || typeof args !== 'object' || Object.keys(args).length === 0) {
            return '';
        }
        const rows = Object.entries(args).map(([key, value]) => {
            const label = `<dt>${this.escape(this.humanizeKey(key))}</dt>`;
            if (typeof value === 'string' && /<[a-z][^>]*>/i.test(value)) {
                return `<div class="ai-chat-arg ai-chat-arg-block">${label}<dd>`
                    + `<div class="ai-chat-html-preview">${this.sanitizeHtml(value)}</div>`
                    + `<details class="ai-chat-arg-long"><summary>${this.escape(this.labels.showSource)}</summary><pre>${this.escape(value)}</pre></details>`
                    + `</dd></div>`;
            }
            const text = typeof value === 'string' ? value : JSON.stringify(value, null, 2);
            if (text.length > 80 || text.includes('\n')) {
                return `<div class="ai-chat-arg ai-chat-arg-block">${label}<dd><pre>${this.escape(text)}</pre></dd></div>`;
            }
            return `<div class="ai-chat-arg">${label}<dd>${this.escape(text)}</dd></div>`;
        });
        return `<dl class="ai-chat-args">${rows.join('')}</dl>`;
    }

    /** Markup for a preview inside the panel: no scripts, no styles, no handlers, no active links. */
    sanitizeHtml(html) {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        doc.querySelectorAll('script, style, link, meta, iframe, object, embed, form, input, button, textarea, select').forEach((node) => node.remove());
        doc.querySelectorAll('*').forEach((node) => {
            for (const attribute of Array.from(node.attributes)) {
                const name = attribute.name.toLowerCase();
                const value = attribute.value.trim().toLowerCase();
                if (name.startsWith('on') || name === 'srcdoc' || ((name === 'href' || name === 'src' || name === 'xlink:href') && value.startsWith('javascript:'))) {
                    node.removeAttribute(attribute.name);
                }
            }
            if (node.tagName === 'A') {
                node.removeAttribute('href');
            }
        });
        return doc.body.innerHTML;
    }

    prettyPreview(text) {
        try {
            return JSON.stringify(JSON.parse(text), null, 2);
        } catch {
            return text;
        }
    }

    escape(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
}

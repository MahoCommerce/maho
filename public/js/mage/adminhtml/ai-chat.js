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
        chat.introMessage = { text: this.labels.intro };
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
            if (event.altKey && !event.ctrlKey && !event.metaKey && event.key.toLowerCase() === 'a') {
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

        const outcome = await this.streamTurn(this.config.chatUrl, {
            conversation_id: this.conversationId,
            message: text,
            context: this.config.context,
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
        const state = { textIndex: null, text: '', steps: new Map(), error: null, navigateTo: null, thinkingIndex: null, newConversation: !this.conversationId };
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
        } catch (error) {
            if (error.name === 'AbortError') {
                this.addText(state, '\n\n_' + this.labels.stopped + '_');
            } else {
                console.error('[ai-chat]', error);
                state.error = this.labels.error;
            }
        }
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
                state.navigateTo = data.url ?? null;
                break;
            case 'done':
                if (data.conversation_id) {
                    this.rememberConversation(data.conversation_id);
                }
                if (state.navigateTo && data.state === 'complete') {
                    const url = state.navigateTo;
                    setTimeout(() => window.location.assign(url), 800);
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

    lastIndex() {
        return this.chat.getMessages().length - 1;
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

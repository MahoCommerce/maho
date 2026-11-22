// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

/**
 * Reports > Explorer: pick a report or build one, run it on the server, and show its numbers, chart and table.
 *
 * Two values make the page, and the URL keeps both, so a link opens the same page:
 * - the state: the report request that the server runs;
 * - the view: what the page shows of it (the tab, the chart metric and type, the open panel, the drill steps).
 */
class MahoReportExplorer {
    static RANGES = ['today', 'yesterday', 'last_7_days', 'last_30_days', 'last_90_days', 'this_month', 'last_month',
        'this_year', 'last_year', 'last_12_months'];
    static RANGE_DAYS = { today: 1, yesterday: 1, last_7_days: 7, last_30_days: 30, last_90_days: 90, this_month: 31,
        last_month: 31, this_year: 366, last_year: 366, last_12_months: 366 };
    static OPERATORS = {
        string: ['eq', 'neq', 'in', 'nin', 'contains', 'empty', 'not_empty'],
        id: ['eq', 'neq', 'in', 'nin', 'empty', 'not_empty'],
        boolean: ['eq'],
        number: ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'nin', 'empty', 'not_empty'],
    };
    static MAX_DIMENSIONS = 3;
    static CARDS = 5;
    static CYCLES = ['hour_of_day', 'day_of_week'];
    static CHART_TOP = 15;
    static SERIES_TOP = 5;
    static FINER = { year: 'month', month: 'day', week: 'day', day: 'hour' };

    constructor(root, config) {
        this.root = root;
        this.config = config;
        this.labels = config.labels;
        this.datasets = new Map(config.datasets.map((dataset) => [dataset.code, dataset]));
        this.saved = config.saved;
        this.dashboard = config.dashboard ?? [];
        this.subscriptions = config.subscriptions ?? {};
        this.savedId = null;
        this.presetChart = null;
        this.result = null;
        this.chart = null;
        this.charts = [];
        this.runToken = 0;
        this.chartToken = 0;
        this.money = new Intl.NumberFormat(config.locale, { style: 'currency', currency: config.currency });
        this.number = new Intl.NumberFormat(config.locale, { maximumFractionDigits: 2 });
        this.dateFormat = new Intl.DateTimeFormat(config.locale, { dateStyle: 'medium', timeStyle: 'short', timeZone: config.timezone });
        this.dayFormat = new Intl.DateTimeFormat(config.locale, { dateStyle: 'medium', timeZone: 'UTC' });

        if (this.datasets.size === 0) {
            this.root.textContent = '';
            return;
        }
        if (config.mode === 'dashboard') {
            this.root.innerHTML = '<div class="contents" data-role="messages"></div><div class="contents" data-role="dashboard"></div>';
            this.parts = Object.fromEntries([...this.root.querySelectorAll('[data-role]')].map((el) => [el.dataset.role, el]));
            this.renderDashboard();
            this.watchColorScheme();
            return;
        }
        this.state = this.initialState();
        this.view = this.initialView();
        this.savedId ??= this.view.savedId ?? null;
        this.presetChart ??= this.view.presetChart ?? null;
        this.renderSkeleton();
        this.renderControls();
        this.run();
        this.watchColorScheme();
        document.addEventListener('click', (event) => {
            if (this.popover && !this.popover.contains(event.target)) {
                this.closePopover();
            }
        });
        document.addEventListener('keydown', (event) => event.key === 'Escape' && this.closePopover());
    }

    t(key, ...args) {
        let text = this.labels[key] ?? key;
        args.forEach((arg, index) => {
            text = text.replace(`%${index + 1}$s`, arg);
        });
        args.forEach((arg) => {
            text = text.replace(/%[sd]/, arg);
        });
        return text;
    }

    escape(value) {
        const span = document.createElement('span');
        span.textContent = String(value ?? '');
        return span.innerHTML;
    }

    element(tag, className = '', text = null) {
        const element = document.createElement(tag);
        if (className) {
            element.className = className;
        }
        if (text !== null) {
            element.textContent = text;
        }
        return element;
    }

    button(label, className, onClick) {
        const button = this.element('button', className, label);
        button.type = 'button';
        button.addEventListener('click', onClick);
        return button;
    }

    /**
     * A link that acts as a button. The old admin styles give every button element a fixed height and a border,
     * so a control that must look like text or like a card uses this.
     */
    action(label, className, onClick) {
        const link = this.element('a', className, label);
        link.href = '#';
        link.setAttribute('role', 'button');
        link.addEventListener('click', (event) => {
            event.preventDefault();
            onClick(event);
        });
        link.addEventListener('keydown', (event) => {
            if (event.key === ' ') {
                event.preventDefault();
                link.click();
            }
        });
        return link;
    }

    async post(url, params) {
        try {
            return await mahoFetch(url, { method: 'POST', body: new URLSearchParams(params), rejectOnError: false });
        } catch (error) {
            return { error: true, message: error.message };
        }
    }

    runRequest(request) {
        return this.post(this.config.urls.data, { request: JSON.stringify(request) });
    }

    initialState() {
        if (this.config.state) {
            try {
                const state = JSON.parse(this.decode(this.config.state));
                if (this.datasets.has(state.dataset)) {
                    return state;
                }
            } catch (error) {
                console.warn('The report state in the URL is not valid.', error);
            }
        }
        const saved = this.saved.find((report) => report.id === this.config.savedId);
        if (saved && this.datasets.has(saved.request.dataset)) {
            this.savedId = saved.id;
            return structuredClone(saved.request);
        }
        const presets = this.allPresets();
        const preset = presets.find((p) => p.code === this.config.preset)
            ?? presets.find((p) => p.code === 'sales_overview')
            ?? presets[0];
        if (preset) {
            this.presetChart = preset.chart;
            return structuredClone(preset.request);
        }
        return this.defaultState(this.datasets.values().next().value);
    }

    initialView() {
        const view = { chartMetric: null, chartType: 'auto', panel: null, drill: [], valueLabels: {} };
        const encoded = new URL(window.location.href).searchParams.get('view');
        if (encoded) {
            try {
                return { ...view, ...JSON.parse(this.decode(encoded)) };
            } catch (error) {
                console.warn('The view in the URL is not valid.', error);
            }
        }
        return view;
    }

    defaultState(dataset) {
        return {
            dataset: dataset.code,
            metrics: [dataset.metrics[0]?.code].filter(Boolean),
            dimensions: dataset.dated ? ['period:day'] : [],
            range: dataset.dated ? 'last_30_days' : undefined,
        };
    }

    allPresets() {
        return [...this.datasets.values()].flatMap((dataset) => dataset.presets.map((preset) => ({ ...preset, dataset: dataset.code })));
    }

    dataset(code = this.state.dataset) {
        return this.datasets.get(code);
    }

    encode(text) {
        return btoa(String.fromCharCode(...new TextEncoder().encode(text))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    decode(text) {
        const binary = atob(text.replace(/-/g, '+').replace(/_/g, '/'));
        return new TextDecoder().decode(Uint8Array.from(binary, (c) => c.charCodeAt(0)));
    }

    /** Change the request. A change by the controls ends the drill steps, because they no longer lead here. */
    setState(changes, { resetPage = true, keepDrill = false } = {}) {
        this.state = { ...this.state, ...changes };
        if (resetPage) {
            delete this.state.offset;
        }
        if (!keepDrill) {
            this.view.drill = [];
        }
        this.view.panel = null;
        this.renderControls();
        this.run();
    }

    /** Open a whole new report: a preset, a saved report or another dataset. */
    open(state, { savedId = null, chart = null } = {}) {
        this.savedId = savedId;
        this.presetChart = chart;
        this.state = structuredClone(state);
        this.view = { ...this.view, chartMetric: null, chartType: 'auto', panel: null, drill: [] };
        this.renderControls();
        this.run();
    }

    setView(changes) {
        this.view = { ...this.view, ...changes };
        this.updateUrl();
    }

    updateUrl() {
        const url = new URL(window.location.href);
        url.searchParams.set('state', this.encode(JSON.stringify(this.state)));
        url.searchParams.set('view', this.encode(JSON.stringify({ ...this.view, savedId: this.savedId, presetChart: this.presetChart })));
        url.searchParams.delete('preset');
        url.searchParams.delete('saved');
        window.history.replaceState(null, '', url);
    }

    renderSkeleton() {
        this.root.innerHTML = `
            <section class="report-explorer-layout min-w-0" data-role="explore">
                <div class="d-card d-card-border bg-base-100"><div class="d-card-body gap-3 p-4" data-role="controls"></div></div>
                <nav class="d-breadcrumbs text-sm py-0" data-role="breadcrumb" aria-label="${this.escape(this.t('drillSteps'))}"></nav>
                <div class="flex flex-col gap-2" data-role="messages"></div>
                <div class="report-explorer-totals" data-role="totals"></div>
                <div class="d-card d-card-border bg-base-100" data-role="chart-card">
                    <div class="d-card-body p-4 gap-3">
                        <div class="flex flex-wrap items-center gap-2" data-role="chart-header"></div>
                        <div class="relative h-72" data-role="chart-box"><canvas data-role="chart"></canvas></div>
                        <div class="flex flex-wrap gap-2" data-role="notes"></div>
                    </div>
                </div>
                <div data-role="panel"></div>
                <div class="d-card d-card-border bg-base-100" data-role="table-card">
                    <div class="d-card-body p-0 gap-0">
                        <div class="overflow-x-auto" data-role="table"></div>
                        <div class="flex items-center justify-end gap-2 p-3" data-role="pager"></div>
                    </div>
                </div>
            </section>`;
        this.parts = Object.fromEntries([...this.root.querySelectorAll('[data-role]')].map((el) => [el.dataset.role, el]));
    }

    /** The Report menu: the ready-made reports by group, then the saved reports. */
    reportSelect() {
        const current = this.saved.find((report) => report.id === this.savedId);
        const changed = current && !this.sameRequest(current.request, this.state);
        const preset = current ? null : this.allPresets().find((p) => this.isPreset(p));
        const value = current && !changed ? `saved:${current.id}` : preset ? `preset:${preset.code}` : '';
        const wrapper = this.element('label', 'flex flex-col gap-1 text-sm');
        wrapper.append(this.element('span', 'font-semibold', this.t('report')));
        const select = this.element('select', 'd-select d-select-sm');
        const option = (optionValue, label) => {
            const element = this.element('option', '', label);
            element.value = optionValue;
            element.selected = optionValue === value;
            return element;
        };
        if (!value) {
            select.append(option('', changed ? this.t('changedReport', current.name) : this.t('customReport')));
        }
        const groups = new Map();
        for (const entry of this.allPresets()) {
            if (!groups.has(entry.group)) {
                groups.set(entry.group, this.element('optgroup'));
                groups.get(entry.group).label = entry.group;
            }
            const element = option(`preset:${entry.code}`, entry.label);
            if (entry.description) {
                element.title = entry.description;
            }
            groups.get(entry.group).append(element);
        }
        select.append(...groups.values());
        if (this.saved.length) {
            const group = this.element('optgroup');
            group.label = this.t('saved');
            for (const report of this.saved) {
                group.append(option(`saved:${report.id}`, report.shared ? `${report.name} (${this.t('shared')})` : report.name));
            }
            select.append(group);
        }
        select.addEventListener('change', () => {
            const [kind, code] = select.value.split(':');
            const report = kind === 'saved' ? this.saved.find((r) => String(r.id) === code) : null;
            const entry = kind === 'preset' ? this.allPresets().find((p) => p.code === code) : null;
            if (report) {
                this.open(report.request, { savedId: report.id });
            } else if (entry) {
                this.open(entry.request, { chart: entry.chart });
            }
        });
        wrapper.append(select);
        return wrapper;
    }

    isPreset(preset) {
        return this.savedId === null && this.view.drill.length === 0 && this.sameRequest(preset.request, this.state);
    }

    /** Compare two requests without the page, the order of the keys and of the metrics, and the empty values. */
    sameRequest(a, b) {
        const normal = (request) => JSON.stringify(Object.entries({ ...request, offset: undefined, metrics: [...(request.metrics ?? [])].sort() })
            .filter(([, value]) => value !== undefined && value !== null && !(Array.isArray(value) && value.length === 0))
            .sort(([x], [y]) => x.localeCompare(y)));
        return normal(a) === normal(b);
    }

    /** The request for another dataset. It keeps each value of the current request that the dataset also has. */
    carryState(dataset) {
        const next = this.defaultState(dataset);
        const old = this.state;
        const metrics = (old.metrics ?? []).filter((code) => dataset.metrics.some((m) => m.code === code));
        const dimensions = (old.dimensions ?? []).filter((entry) => {
            const [code, granularity] = entry.split(':');
            const dimension = dataset.dimensions.find((d) => d.code === code);
            return dimension && (!granularity || dimension.granularities?.includes(granularity));
        });
        const filters = (old.filters ?? []).filter((filter) => {
            const field = dataset.fields.find((f) => f.code === filter.field);
            return field && this.operatorsFor(field).includes(filter.operator);
        });
        if (metrics.length) {
            next.metrics = metrics;
        }
        if (dimensions.length) {
            next.dimensions = dimensions;
        }
        if (filters.length) {
            next.filters = filters;
        }
        if (old.sort && (next.metrics.includes(old.sort.key) || (next.dimensions ?? []).some((entry) => entry.split(':')[0] === old.sort.key))) {
            next.sort = old.sort;
        }
        if (dataset.dated) {
            Object.assign(next, { range: old.range, from: old.from, to: old.to, compare: old.compare });
            if (!next.range && !(next.from && next.to)) {
                next.range = 'last_30_days';
            }
            if (!dimensions.length && next.dimensions) {
                next.dimensions = [`period:${this.stepFor(this.periodDays(next), dataset)}`];
            }
            if (dataset.dateFields.some((f) => f.code === old.dateField)) {
                next.dateField = old.dateField;
            }
        }
        if (dataset.hasStores && old.storeIds) {
            next.storeIds = old.storeIds;
        }
        if (dataset.scopes.some((scope) => scope.code === old.scope)) {
            next.scope = old.scope;
        }
        return next;
    }

    renderControls() {
        const dataset = this.dataset();
        const controls = this.parts.controls;
        controls.textContent = '';

        const row = this.element('div', 'flex flex-wrap items-end gap-3');
        row.append(this.reportSelect());
        row.append(this.selectField(this.t('dataset'), [...this.datasets.values()].map((d) => [d.code, d.label]), this.state.dataset,
            (value) => this.open(this.carryState(this.datasets.get(value)))));

        if (dataset.dated) {
            const range = this.state.range ?? (this.state.from ? 'custom' : 'last_30_days');
            const ranges = MahoReportExplorer.RANGES.map((code) => [code, this.t(code)]).concat([['custom', this.t('custom')]]);
            row.append(this.selectField(this.t('period'), ranges, range, (value) => {
                if (value === 'custom') {
                    const today = this.today();
                    this.setPeriod({ range: undefined, from: this.state.from ?? today, to: this.state.to ?? today });
                } else {
                    this.setPeriod({ range: value, from: undefined, to: undefined });
                }
            }));
            if (range === 'custom') {
                row.append(this.dateField(this.t('from'), this.state.from, (value) => this.setPeriod({ from: value })));
                row.append(this.dateField(this.t('to'), this.state.to, (value) => this.setPeriod({ to: value })));
            }
            row.append(this.selectField(this.t('compare'), [
                ['', this.t('none')], ['previous_period', this.t('previousPeriod')], ['previous_year', this.t('previousYear')],
            ], this.state.compare ?? '', (value) => this.setState({ compare: value || undefined }, { keepDrill: true })));
            if (dataset.dateFields.length > 1) {
                row.append(this.selectField(this.t('dateField'), dataset.dateFields.map((f) => [f.code, f.label]),
                    this.state.dateField ?? dataset.dateFields[0].code, (value) => this.setState({ dateField: value })));
            }
        }
        if (dataset.hasStores && this.config.stores.length > 1) {
            const stores = [['', this.t('allStores')], ...this.config.stores.map((s) => [String(s.id), s.label])];
            row.append(this.selectField(this.t('stores'), stores, String(this.state.storeIds?.[0] ?? ''),
                (value) => this.setState({ storeIds: value ? [Number(value)] : undefined })));
        }
        if (dataset.scopes.length > 1) {
            row.append(this.selectField(this.t('scope'), dataset.scopes.map((s) => [s.code, s.label]),
                this.state.scope ?? dataset.defaultScope, (value) => this.setState({ scope: value })));
        }

        const actions = this.element('div', 'flex gap-2 ms-auto');
        actions.append(this.button(this.t('export'), 'd-btn d-btn-sm', () => this.export()));
        actions.append(this.button(this.t('save'), 'd-btn d-btn-sm d-btn-primary', () => this.openSaveDialog()));
        const current = this.saved.find((report) => report.id === this.savedId);
        if (current) {
            const pinned = this.dashboard.includes(current.id);
            const pin = this.button(pinned ? '★' : '☆', 'd-btn d-btn-sm d-btn-ghost', () => this.togglePin(current, !pinned));
            pin.setAttribute('aria-label', pinned ? this.t('unpin') : this.t('pin'));
            pin.setAttribute('aria-pressed', String(pinned));
            pin.title = pinned ? this.t('unpin') : this.t('pin');
            actions.append(pin);
            const subscription = this.subscriptions[current.id];
            const email = this.button(subscription ? this.t('emailOn', this.t(subscription.frequency)) : this.t('email'),
                'd-btn d-btn-sm d-btn-ghost', () => this.openEmailDialog(current));
            email.setAttribute('aria-pressed', String(Boolean(subscription)));
            actions.append(email);
        }
        if (current?.mine) {
            const deleteButton = this.button(this.t('delete'), 'd-btn d-btn-sm d-btn-ghost text-error', () => this.deleteSaved(current));
            deleteButton.setAttribute('aria-label', this.t('deleteReport'));
            actions.append(deleteButton);
        }
        row.append(actions);
        controls.append(row);

        controls.append(this.metricPicker(dataset));
        controls.append(this.dimensionPicker(dataset));
        controls.append(this.filterPicker(dataset));
        this.renderBreadcrumb();
    }

    /**
     * Change the period. When the report groups by the period, the step follows the length of the period, as people
     * read it best: hours for a day or two, days up to two months, weeks up to half a year, then months.
     */
    setPeriod(changes) {
        const next = { ...this.state, ...changes };
        const index = (next.dimensions ?? []).findIndex((entry) => entry.startsWith('period'));
        if (index >= 0) {
            const current = next.dimensions[index].split(':')[1] ?? 'day';
            if (!MahoReportExplorer.CYCLES.includes(current)) {
                const step = this.stepFor(this.periodDays(next));
                if (step !== current) {
                    changes.dimensions = next.dimensions.map((entry, i) => (i === index ? `period:${step}` : entry));
                }
            }
        }
        this.setState(changes);
    }

    periodDays(state) {
        if (!state.range && state.from && state.to) {
            return Math.abs(Date.parse(state.to) - Date.parse(state.from)) / 86400000 + 1;
        }
        return MahoReportExplorer.RANGE_DAYS[state.range ?? 'last_30_days'] ?? 30;
    }

    stepFor(days, dataset = this.dataset()) {
        const period = dataset.dimensions.find((d) => d.granularities);
        const allowed = period?.granularities ?? ['day'];
        const wanted = days <= 2 ? 'hour' : days <= 62 ? 'day' : days <= 183 ? 'week' : 'month';
        return allowed.includes(wanted) ? wanted : 'day';
    }

    selectField(label, options, value, onChange) {
        const wrapper = this.element('label', 'flex flex-col gap-1 text-sm');
        wrapper.append(this.element('span', 'font-semibold', label));
        const select = this.element('select', 'd-select d-select-sm');
        for (const [optionValue, optionLabel] of options) {
            const option = this.element('option', '', optionLabel);
            option.value = optionValue;
            option.selected = optionValue === value;
            select.append(option);
        }
        select.addEventListener('change', () => onChange(select.value));
        wrapper.append(select);
        return wrapper;
    }

    dateField(label, value, onChange) {
        const wrapper = this.element('label', 'flex flex-col gap-1 text-sm');
        wrapper.append(this.element('span', 'font-semibold', label));
        const input = this.element('input', 'd-input d-input-sm');
        input.type = 'date';
        input.value = value ?? '';
        input.addEventListener('change', () => input.value && onChange(input.value));
        wrapper.append(input);
        return wrapper;
    }

    metricPicker(dataset) {
        const row = this.element('div', 'flex flex-wrap items-center gap-2');
        row.append(this.element('span', 'text-sm font-semibold', this.t('metrics')));
        const details = this.element('details', 'd-dropdown');
        details.append(this.element('summary', 'd-btn d-btn-sm', `${this.state.metrics?.length ?? 0} / ${dataset.metrics.length}`));
        const list = this.element('ul', 'd-dropdown-content d-menu d-menu-sm bg-base-100 rounded-box z-10 w-72 max-h-96 overflow-y-auto shadow');
        for (const metric of dataset.metrics) {
            const label = this.element('label', 'flex items-center gap-2');
            const checkbox = this.element('input', 'd-checkbox d-checkbox-sm');
            checkbox.type = 'checkbox';
            checkbox.checked = (this.state.metrics ?? []).includes(metric.code);
            checkbox.addEventListener('change', () => {
                const metrics = dataset.metrics.map((m) => m.code)
                    .filter((code) => (code === metric.code ? checkbox.checked : (this.state.metrics ?? []).includes(code)));
                if (metrics.length > 0) {
                    const sort = this.state.sort && !metrics.includes(this.state.sort.key) && !this.hasDimension(this.state.sort.key) ? undefined : this.state.sort;
                    this.setState({ metrics, sort }, { keepDrill: true });
                } else {
                    checkbox.checked = true;
                }
            });
            label.append(checkbox, this.element('span', '', metric.label));
            if (metric.description) {
                label.title = metric.description;
            }
            const li = this.element('li');
            li.append(label);
            list.append(li);
        }
        details.append(list);
        row.append(details);
        return row;
    }

    hasDimension(code) {
        return (this.state.dimensions ?? []).some((dimension) => dimension.split(':')[0] === code);
    }

    dimensionPicker(dataset) {
        const row = this.element('div', 'flex flex-wrap items-center gap-2');
        row.append(this.element('span', 'text-sm font-semibold', this.t('dimensions')));
        const dimensions = this.state.dimensions ?? [];
        dimensions.forEach((entry, index) => {
            const [code, granularity] = entry.split(':');
            const dimension = dataset.dimensions.find((d) => d.code === code);
            if (!dimension) {
                return;
            }
            const chip = this.element('span', 'd-badge d-badge-lg h-[calc(var(--size-field)*8)] border-base-content/20 gap-1 whitespace-nowrap text-xs');
            chip.append(this.element('span', '', dimension.label));
            if (dimension.granularities) {
                const select = this.element('select', 'd-select d-select-xs d-select-ghost');
                select.setAttribute('aria-label', dimension.label);
                for (const value of dimension.granularities) {
                    const option = this.element('option', '', this.t(value));
                    option.value = value;
                    option.selected = value === (granularity ?? 'day');
                    select.append(option);
                }
                select.addEventListener('change', () => {
                    const next = [...dimensions];
                    next[index] = `${code}:${select.value}`;
                    this.setState({ dimensions: next }, { keepDrill: true });
                });
                chip.append(select);
            }
            chip.append(this.removeButton(dimension.label, () => {
                const sort = this.state.sort?.key === code ? undefined : this.state.sort;
                this.setState({ dimensions: dimensions.filter((_, i) => i !== index), sort }, { keepDrill: true });
            }));
            row.append(chip);
        });
        if (dimensions.length < MahoReportExplorer.MAX_DIMENSIONS) {
            const unused = dataset.dimensions.filter((d) => !this.hasDimension(d.code));
            if (unused.length) {
                const select = this.element('select', 'd-select d-select-sm w-auto');
                select.setAttribute('aria-label', this.t('addDimension'));
                select.append(this.element('option', '', this.t('addDimension')));
                for (const dimension of unused) {
                    const option = this.element('option', '', dimension.label);
                    option.value = dimension.granularities ? `${dimension.code}:${this.stepFor(this.periodDays(this.state))}` : dimension.code;
                    select.append(option);
                }
                select.addEventListener('change', () => select.value && this.setState({ dimensions: [...dimensions, select.value] }, { keepDrill: true }));
                row.append(select);
            }
        }
        return row;
    }

    removeButton(label, onClick) {
        const button = this.button('×', 'd-btn d-btn-ghost d-btn-xs d-btn-circle', onClick);
        button.setAttribute('aria-label', `${this.t('remove')} ${label}`);
        return button;
    }

    filterPicker(dataset) {
        const row = this.element('div', 'flex flex-wrap items-center gap-2');
        row.append(this.element('span', 'text-sm font-semibold', this.t('filters')));
        const filters = this.state.filters ?? [];
        filters.forEach((filter, index) => {
            const field = dataset.fields.find((f) => f.code === filter.field);
            const chip = this.element('span', 'd-badge d-badge-lg h-[calc(var(--size-field)*8)] border-base-content/20 gap-1 whitespace-nowrap text-xs');
            chip.append(this.element('span', '', `${field?.label ?? filter.field} ${this.t(filter.operator)} ${this.filterValueLabel(field, filter.value)}`));
            chip.append(this.removeButton(field?.label ?? filter.field, () => this.setState({ filters: filters.filter((_, i) => i !== index) }, { keepDrill: true })));
            row.append(chip);
        });

        const details = this.element('details', 'd-dropdown');
        details.append(this.element('summary', 'd-btn d-btn-sm', this.t('addFilter')));
        const form = this.element('form', 'd-dropdown-content bg-base-100 rounded-box z-10 w-80 p-3 shadow flex flex-col gap-2');
        const fieldSelect = this.element('select', 'd-select d-select-sm w-full');
        fieldSelect.setAttribute('aria-label', this.t('field'));
        for (const field of dataset.fields) {
            const option = this.element('option', '', field.label);
            option.value = field.code;
            fieldSelect.append(option);
        }
        const operatorSelect = this.element('select', 'd-select d-select-sm w-full');
        operatorSelect.setAttribute('aria-label', this.t('operator'));
        const valueHolder = this.element('div');
        const fillValue = () => {
            const field = dataset.fields.find((f) => f.code === fieldSelect.value);
            const operator = operatorSelect.value;
            valueHolder.textContent = '';
            if (operator === 'empty' || operator === 'not_empty') {
                return;
            }
            const options = field.type === 'boolean'
                ? [{ value: 1, label: this.t('yes') }, { value: 0, label: this.t('no') }]
                : field.options;
            if (options && operator !== 'in' && operator !== 'nin') {
                const select = this.element('select', 'd-select d-select-sm w-full');
                select.name = 'value';
                select.setAttribute('aria-label', this.t('value'));
                for (const item of options) {
                    const option = this.element('option', '', item.label);
                    option.value = String(item.value);
                    select.append(option);
                }
                valueHolder.append(select);
                return;
            }
            const input = this.element('input', 'd-input d-input-sm w-full');
            input.name = 'value';
            input.required = true;
            input.setAttribute('aria-label', this.t('value'));
            input.type = ['integer', 'decimal', 'money'].includes(field.type) && operator !== 'in' && operator !== 'nin' ? 'number' : 'text';
            input.step = 'any';
            if (operator === 'in' || operator === 'nin') {
                input.placeholder = this.t('listHint');
            }
            valueHolder.append(input);
        };
        const fillOperators = () => {
            const field = dataset.fields.find((f) => f.code === fieldSelect.value);
            operatorSelect.textContent = '';
            for (const operator of this.operatorsFor(field)) {
                const option = this.element('option', '', this.t(operator));
                option.value = operator;
                operatorSelect.append(option);
            }
            fillValue();
        };
        fieldSelect.addEventListener('change', fillOperators);
        operatorSelect.addEventListener('change', fillValue);
        fillOperators();

        const add = this.element('button', 'd-btn d-btn-sm d-btn-primary', this.t('add'));
        add.type = 'submit';
        form.append(fieldSelect, operatorSelect, valueHolder, add);
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const field = dataset.fields.find((f) => f.code === fieldSelect.value);
            const operator = operatorSelect.value;
            const input = valueHolder.querySelector('[name=value]');
            let value = input ? input.value : null;
            if (operator === 'in' || operator === 'nin') {
                value = value.split(',').map((v) => v.trim()).filter(Boolean);
            } else if (value !== null && ['integer', 'decimal', 'money', 'boolean', 'id'].includes(field.type) && value !== '' && !isNaN(value)) {
                value = Number(value);
            }
            details.open = false;
            this.setState({ filters: [...filters, { field: field.code, operator, value }] }, { keepDrill: true });
        });
        details.append(form);
        row.append(details);
        return row;
    }

    operatorsFor(field) {
        if (field.type === 'boolean') {
            return MahoReportExplorer.OPERATORS.boolean;
        }
        if (field.type === 'string') {
            return MahoReportExplorer.OPERATORS.string;
        }
        if (field.type === 'id' || field.options) {
            return MahoReportExplorer.OPERATORS.id;
        }
        return MahoReportExplorer.OPERATORS.number;
    }

    filterValueLabel(field, value) {
        if (value === null || value === undefined) {
            return '';
        }
        const values = Array.isArray(value) ? value : [value];
        const options = field?.type === 'boolean'
            ? [{ value: 1, label: this.t('yes') }, { value: 0, label: this.t('no') }]
            : field?.options ?? [];
        return values.map((v) => options.find((o) => String(o.value) === String(v))?.label
            ?? this.view.valueLabels?.[`${field?.code}|${v}`] ?? v).join(', ');
    }

    /** Keep the label of a value that a drill filters on, such as the name of a customer group for its ID. */
    rememberLabel(field, value, label) {
        if (label !== undefined && String(label) !== String(value)) {
            this.view.valueLabels = { ...(this.view.valueLabels ?? {}), [`${field}|${value}`]: label };
        }
    }

    renderBreadcrumb() {
        const nav = this.parts.breadcrumb;
        nav.textContent = '';
        nav.hidden = this.view.drill.length === 0;
        if (nav.hidden) {
            return;
        }
        const list = this.element('ul');
        const steps = [...this.view.drill.map((step) => step.label)];
        this.view.drill.forEach((step, index) => {
            const li = this.element('li');
            const back = this.action(index === 0 ? this.t('allData') : steps[index - 1], 'd-link', () => {
                this.state = structuredClone(step.state);
                this.view.drill = this.view.drill.slice(0, index);
                this.view.panel = null;
                this.renderControls();
                this.run();
            });
            li.append(back);
            list.append(li);
        });
        const last = this.element('li');
        last.append(this.element('span', 'font-semibold', steps[steps.length - 1]));
        list.append(last);
        nav.append(list);
    }

    /** Apply a drill: remember the request before it, so the breadcrumb can go back. */
    drillTo(label, changes) {
        this.view.drill = [...this.view.drill, { label, state: structuredClone(this.state) }];
        this.setState(changes, { keepDrill: true });
    }

    /** The date of today in the time zone of the store, as YYYY-MM-DD. */
    today() {
        return new Intl.DateTimeFormat('en-CA', { timeZone: this.config.timezone }).format(new Date());
    }

    /** A copy of the rows in the order of the period, oldest first. A cycle keeps the order of the server, which starts the week on its first day. */
    byPeriod(rows, column) {
        if (MahoReportExplorer.CYCLES.includes(column.granularity)) {
            return rows;
        }
        return [...rows].sort((a, b) => {
            const x = a.dimensions[column.key];
            const y = b.dimensions[column.key];
            return typeof x === 'number' ? x - y : (x < y ? -1 : (x > y ? 1 : 0));
        });
    }

    /** The first and the last local day of a bucket of the period, or null for an hour or a cycle such as the hour of the day. */
    bucketRange(granularity, key) {
        const day = (date) => date.toISOString().slice(0, 10);
        switch (granularity) {
            case 'day':
                return [String(key).slice(0, 10), String(key).slice(0, 10)];
            case 'month': {
                const [year, month] = String(key).split('-').map(Number);
                return [`${key}-01`, day(new Date(Date.UTC(year, month, 0)))];
            }
            case 'year':
                return [`${key}-01-01`, `${key}-12-31`];
            case 'week': {
                const start = new Date(Date.UTC(1970, 0, 4 + this.config.firstDayOfWeek + Number(key)));
                return [day(start), day(new Date(start.getTime() + 6 * 86400000))];
            }
            default:
                return null;
        }
    }

    /**
     * The request changes that keep only the values of a row: a filter for each grouping by a field, and the days of
     * the bucket for the period. A grouping that the drill keeps one value of is removed, and the period gets a finer
     * step, so the report shows what is inside the value.
     */
    drillChanges(row, { keepDimensions = false } = {}) {
        const dataset = this.dataset();
        const filters = [...(this.state.filters ?? [])];
        let dimensions = [...(this.state.dimensions ?? [])];
        const changes = {};
        const labels = [];
        for (const column of this.dimensionColumns()) {
            if (!(column.key in row.dimensions)) {
                continue;
            }
            const value = row.dimensions[column.key];
            const label = row.labels?.[column.key] ?? String(value ?? '');
            if (column.granularity) {
                const range = this.bucketRange(column.granularity, value);
                if (!range) {
                    continue;
                }
                const period = this.result?.period;
                Object.assign(changes, {
                    range: undefined,
                    from: period?.from && period.from > range[0] ? period.from : range[0],
                    to: period?.to && period.to < range[1] ? period.to : range[1],
                });
                const finer = MahoReportExplorer.FINER[column.granularity];
                const allowed = dataset.dimensions.find((d) => d.code === column.key)?.granularities ?? [];
                dimensions = dimensions.map((entry) => (entry.split(':')[0] === column.key
                    ? (finer && allowed.includes(finer) ? `${column.key}:${finer}` : entry) : entry));
                labels.push(label);
                continue;
            }
            const field = dataset.dimensions.find((d) => d.code === column.key)?.field;
            if (!field) {
                continue;
            }
            filters.push(value === null || value === '' ? { field, operator: 'empty', value: null } : { field, operator: 'eq', value });
            this.rememberLabel(field, value, label);
            if (!keepDimensions) {
                dimensions = dimensions.filter((entry) => entry.split(':')[0] !== column.key);
            }
            labels.push(`${column.label}: ${label}`);
        }
        changes.filters = filters;
        changes.dimensions = dimensions;
        return { changes, label: labels.join(' · ') };
    }

    /** The menu of a row of the report or of a bar or point of the chart. */
    openDrillMenu(row, anchor) {
        this.closePopover();
        const dataset = this.dataset();
        const { changes, label } = this.drillChanges(row);
        if (!label) {
            return;
        }
        const menu = this.element('ul', 'd-menu d-menu-sm bg-base-100 rounded-box shadow-lg z-50 w-72 absolute');
        menu.setAttribute('role', 'menu');
        const item = (text, onClick, className = '') => {
            const li = this.element('li');
            const button = this.action(text, className, () => {
                this.closePopover();
                onClick();
            });
            button.setAttribute('role', 'menuitem');
            li.append(button);
            menu.append(li);
            return button;
        };
        const title = this.element('li', 'd-menu-title truncate', label);
        menu.append(title);
        item(this.t('onlyThis'), () => this.drillTo(label, changes));

        const used = new Set((changes.dimensions ?? []).map((entry) => entry.split(':')[0]));
        const others = dataset.dimensions.filter((d) => !d.granularities && !used.has(d.code) && !this.hasDimension(d.code));
        if (others.length) {
            const li = this.element('li');
            const details = this.element('details');
            details.append(this.element('summary', '', this.t('breakDownBy')));
            const sub = this.element('ul', 'max-h-60 overflow-y-auto');
            for (const dimension of others) {
                const subItem = this.element('li');
                subItem.append(this.action(dimension.label, '', () => {
                    this.closePopover();
                    this.drillTo(label, { ...changes, dimensions: [dimension.code, ...(changes.dimensions ?? [])].slice(0, MahoReportExplorer.MAX_DIMENSIONS) });
                }));
                sub.append(subItem);
            }
            details.append(sub);
            li.append(details);
            menu.append(li);
        }
        if (dataset.records) {
            item(this.t('showRecords'), () => this.openRecords({ ...this.state, ...changes, dimensions: [] }, label));
        }
        if (dataset.dated && this.chartMetric()) {
            item(this.t('explainChange'), () => this.openExplain({ ...this.state, ...changes, dimensions: [] }, label));
        }
        if (dataset.dated && changes.from) {
            item(this.t('addNote'), () => this.openNoteDialog(changes.from));
        }

        document.body.append(menu);
        const box = anchor.getBoundingClientRect?.() ?? { left: anchor.x, bottom: anchor.y, top: anchor.y };
        menu.style.left = `${Math.min(window.scrollX + box.left, window.scrollX + document.documentElement.clientWidth - 300)}px`;
        menu.style.top = `${window.scrollY + box.bottom + 4}px`;
        this.popover = menu;
        menu.querySelector('[role=menuitem]')?.focus();
    }

    closePopover() {
        this.popover?.remove();
        this.popover = null;
    }

    async run() {
        const token = ++this.runToken;
        this.updateUrl();
        this.parts.messages.textContent = '';
        const result = await this.runRequest(this.state);
        if (token !== this.runToken) {
            return;
        }
        if (result.error) {
            this.showErrors(result.message, result.errors);
            return;
        }
        this.result = result;
        const metrics = this.metricColumns().map((column) => column.key);
        if (!metrics.includes(this.view.chartMetric)) {
            this.view.chartMetric = metrics.find((key) => this.metricColumn(key)?.format !== 'date') ?? null;
        }
        this.updateUrl();
        this.renderMessages();
        this.renderTotals();
        this.renderTable();
        this.renderPager();
        this.renderPanel();
        await this.renderChart(token);
    }

    showErrors(message, errors = [], target = this.parts.messages) {
        const alert = this.element('div', 'd-alert d-alert-error flex-col items-start');
        alert.setAttribute('role', 'alert');
        alert.append(this.element('strong', '', message));
        if (errors.length) {
            const list = this.element('ul', 'list-disc ps-5');
            for (const error of errors) {
                list.append(this.element('li', '', error.message));
            }
            alert.append(list);
        }
        target.append(alert);
    }

    renderMessages() {
        for (const warning of this.result.warnings ?? []) {
            const alert = this.element('div', 'd-alert d-alert-warning', warning);
            alert.setAttribute('role', 'status');
            this.parts.messages.append(alert);
        }
    }

    metricColumns(result = this.result) {
        return result.columns.filter((column) => column.kind === 'metric');
    }

    dimensionColumns(result = this.result) {
        return result.columns.filter((column) => column.kind === 'dimension');
    }

    metricColumn(key, result = this.result) {
        return result.columns.find((column) => column.key === key);
    }

    chartMetric() {
        return this.view.chartMetric ? this.metricColumn(this.view.chartMetric) : null;
    }

    format(column, value) {
        if (value === null || value === undefined) {
            return '–';
        }
        switch (column.format) {
            case 'money':
                return this.money.format(value);
            case 'percent':
                return `${this.number.format(value)} %`;
            case 'date':
                return this.dateFormat.format(new Date(String(value).replace(' ', 'T') + 'Z'));
            case 'boolean':
                return Number(value) ? this.t('yes') : this.t('no');
            case 'text':
                return String(value);
            default:
                return this.number.format(value);
        }
    }

    change(column, change) {
        if (!change || change.percent === null || change.percent === undefined) {
            return null;
        }
        const better = change.percent === 0 ? null : (change.percent > 0) === column.higherIsBetter;
        const badge = this.element('span', `text-xs whitespace-nowrap ${better === null ? 'opacity-70' : better ? 'text-success' : 'text-error'}`,
            `${change.percent > 0 ? '▲' : change.percent < 0 ? '▼' : ''} ${this.number.format(Math.abs(change.percent))} %`);
        badge.title = this.t('change');
        return badge;
    }

    /** The values of a metric by bucket, when the report groups by the period only. */
    series(key) {
        const dimensions = this.dimensionColumns();
        if (dimensions.length !== 1 || !dimensions[0].granularity || MahoReportExplorer.CYCLES.includes(dimensions[0].granularity)) {
            return null;
        }
        return this.byPeriod(this.result.rows, dimensions[0]).map((row) => row.values[key] ?? 0);
    }

    sparkline(values) {
        if (!values || values.length < 2) {
            return null;
        }
        const max = Math.max(...values);
        const min = Math.min(...values);
        const span = max - min || 1;
        const points = values.map((value, index) => `${(index / (values.length - 1)) * 100},${22 - ((value - min) / span) * 20}`).join(' ');
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 100 24');
        svg.setAttribute('preserveAspectRatio', 'none');
        svg.setAttribute('aria-hidden', 'true');
        svg.classList.add('w-full', 'h-6', 'text-primary');
        const line = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
        line.setAttribute('points', points);
        line.setAttribute('fill', 'none');
        line.setAttribute('stroke', 'currentColor');
        line.setAttribute('stroke-width', '1.5');
        line.setAttribute('vector-effect', 'non-scaling-stroke');
        svg.append(line);
        return svg;
    }

    /** One tile for each metric. A tile picks the metric of the chart. */
    renderTotals() {
        const totals = this.parts.totals;
        totals.textContent = '';
        const columns = this.metricColumns();
        const folded = !this.view.allCards && columns.length > MahoReportExplorer.CARDS + 1;
        const shown = folded ? columns.filter((column, index) => index < MahoReportExplorer.CARDS || column.key === this.view.chartMetric) : columns;
        for (const column of shown) {
            const selected = column.key === this.view.chartMetric;
            const pick = () => {
                this.setView({ chartMetric: column.key });
                this.renderTotals();
                this.renderTable();
                this.renderChart(this.runToken);
            };
            const tile = column.format === 'date'
                ? this.element('div', 'd-card d-card-border bg-base-100')
                : this.action('', `d-card d-card-border bg-base-100 no-underline text-base-content ${selected ? 'border-primary ring-2 ring-primary' : 'hover:border-primary'}`, pick);
            tile.setAttribute('aria-pressed', String(selected));
            tile.title = this.t('chartThis');
            const body = this.element('div', 'd-card-body p-3 gap-1');
            body.append(this.element('span', 'text-xs opacity-70', column.label));
            body.append(this.element('strong', 'text-lg', this.format(column, this.result.totals.values[column.key])));
            if (this.result.totals.compare) {
                const line = this.element('span', 'flex flex-wrap gap-x-2 items-baseline text-xs opacity-80',
                    `${this.t('earlier')}: ${this.format(column, this.result.totals.compare[column.key])}`);
                const badge = this.change(column, this.result.totals.change?.[column.key]);
                if (badge) {
                    line.append(badge);
                }
                body.append(line);
            }
            const target = this.result.targets?.[column.key];
            if (target && target.total) {
                const share = (this.result.totals.values[column.key] ?? 0) / target.total * 100;
                body.append(this.element('span', 'text-xs opacity-80', this.t('ofTarget', this.number.format(share), this.format(column, target.total))));
            }
            const spark = this.sparkline(this.series(column.key));
            if (spark) {
                body.append(spark);
            }
            tile.append(body);
            totals.append(tile);
        }
        if (columns.length > MahoReportExplorer.CARDS + 1) {
            const more = this.button(folded ? this.t('moreMetrics', columns.length - shown.length) : this.t('fewerMetrics'), 'd-card border-2 border-dashed border-base-300 bg-transparent min-h-24 font-semibold hover:border-primary', () => {
                this.setView({ allCards: !this.view.allCards });
                this.renderTotals();
            });
            more.setAttribute('aria-expanded', String(!folded));
            totals.append(more);
        }
    }

    renderTable() {
        const holder = this.parts.table;
        holder.textContent = '';
        const table = this.element('table', 'd-table d-table-sm d-table-zebra');
        const head = this.element('thead');
        const headRow = this.element('tr');
        const sort = this.result.request.sort;
        const chartMetric = this.view.chartMetric;
        const compared = Boolean(this.result.comparePeriod);
        const dimensions = this.dimensionColumns();
        for (const column of this.result.columns) {
            const th = this.element('th', column.kind === 'metric' ? 'text-end' : '');
            const button = this.button(column.label, 'd-btn d-btn-ghost d-btn-xs', () => {
                const direction = sort?.key === column.key && sort.direction === 'desc' ? 'asc' : 'desc';
                this.setState({ sort: { key: column.key, direction } }, { keepDrill: true });
            });
            if (sort?.key === column.key) {
                button.textContent += sort.direction === 'asc' ? ' ▲' : ' ▼';
                th.setAttribute('aria-sort', sort.direction === 'asc' ? 'ascending' : 'descending');
            }
            th.append(button);
            headRow.append(th);
            if (compared && column.key === chartMetric) {
                headRow.append(this.element('th', 'text-end text-xs', this.t('earlier')));
                headRow.append(this.element('th', 'text-end text-xs', this.t('change')));
            }
        }
        head.append(headRow);
        table.append(head);

        const body = this.element('tbody');
        if (this.result.rows.length === 0) {
            const tr = this.element('tr');
            const td = this.element('td', 'text-center opacity-70', this.t('noRows'));
            td.colSpan = this.result.columns.length + (compared ? 2 : 0);
            tr.append(td);
            body.append(tr);
        }
        for (const row of this.result.rows) {
            const tr = this.element('tr', dimensions.length ? 'd-hover cursor-pointer' : 'd-hover');
            if (dimensions.length) {
                tr.addEventListener('click', (event) => {
                    if (!event.target.closest('a, button, input, select')) {
                        event.stopPropagation();
                        this.openDrillMenu(row, { x: event.clientX, y: event.clientY });
                    }
                });
            }
            dimensions.forEach((column, index) => {
                const td = this.element('td', 'whitespace-nowrap');
                const text = row.labels[column.key] ?? row.dimensions[column.key] ?? '';
                if (index === 0) {
                    const open = this.action(text, 'd-link d-link-hover', (event) => {
                        event.stopPropagation();
                        this.openDrillMenu(row, event.currentTarget);
                    });
                    open.setAttribute('aria-haspopup', 'menu');
                    td.append(open);
                } else {
                    td.textContent = text;
                }
                tr.append(td);
            });
            for (const column of this.metricColumns()) {
                tr.append(this.element('td', 'text-end whitespace-nowrap', this.format(column, row.values[column.key])));
                if (compared && column.key === chartMetric) {
                    tr.append(this.element('td', 'text-end whitespace-nowrap text-xs opacity-80', this.format(column, row.compare?.[column.key])));
                    const td = this.element('td', 'text-end');
                    const badge = this.change(column, row.change?.[column.key]);
                    if (badge) {
                        td.append(badge);
                    }
                    tr.append(td);
                }
            }
            body.append(tr);
        }
        table.append(body);

        const foot = this.element('tfoot');
        const footRow = this.element('tr');
        if (dimensions.length > 0) {
            const label = this.element('th', '', this.t('total'));
            label.colSpan = dimensions.length;
            footRow.append(label);
        }
        for (const column of this.metricColumns()) {
            footRow.append(this.element('th', 'text-end whitespace-nowrap', this.format(column, this.result.totals.values[column.key])));
            if (compared && column.key === chartMetric) {
                footRow.append(this.element('th', 'text-end whitespace-nowrap text-xs', this.format(column, this.result.totals.compare?.[column.key])));
                const th = this.element('th', 'text-end');
                const badge = this.change(column, this.result.totals.change?.[column.key]);
                if (badge) {
                    th.append(badge);
                }
                footRow.append(th);
            }
        }
        foot.append(footRow);
        table.append(foot);
        holder.append(table);
        this.parts['table-card'].hidden = dimensions.length === 0;
    }

    renderPager() {
        const pager = this.parts.pager;
        pager.textContent = '';
        const offset = this.state.offset ?? 0;
        const total = this.result.totalRows;
        const shown = this.result.rows.length;
        if (total <= shown && offset === 0) {
            return;
        }
        const limit = this.state.limit ?? 100;
        pager.append(this.element('span', 'text-sm opacity-80', this.t('rowsOf', offset + 1, offset + shown, total)));
        const previous = this.button(this.t('previous'), 'd-btn d-btn-sm', () => this.setState({ offset: Math.max(0, offset - limit) || undefined }, { resetPage: false, keepDrill: true }));
        previous.disabled = offset === 0;
        const next = this.button(this.t('next'), 'd-btn d-btn-sm', () => this.setState({ offset: offset + limit }, { resetPage: false, keepDrill: true }));
        next.disabled = offset + shown >= total;
        pager.append(previous, next);
    }

    async loadChartJs() {
        if (window.Chart) {
            return;
        }
        await new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = this.config.urls.chart;
            script.onload = resolve;
            script.onerror = () => reject(new Error('Chart.js did not load.'));
            document.head.append(script);
        });
    }

    /**
     * The color of a theme variable as rgba(). The theme writes its colors with light-dark(), which Chart.js cannot
     * read, so the browser resolves the color on a hidden element.
     */
    color(name, alpha = 1) {
        const probe = this.element('span');
        probe.hidden = true;
        probe.style.color = `var(${name})`;
        this.root.append(probe);
        const [r, g, b] = (getComputedStyle(probe).color.match(/[\d.]+/g) ?? ['0', '0', '0']).map(Number);
        probe.remove();
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    palette() {
        return ['--color-primary', '--color-info', '--color-warning', '--color-success', '--color-error'].map((name) => this.color(name));
    }

    watchColorScheme() {
        const redraw = () => (this.config.mode === 'dashboard' ? this.renderDashboard() : this.renderChart(this.runToken));
        new MutationObserver(redraw).observe(document.documentElement, { attributes: true, attributeFilter: ['data-scheme'] });
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', redraw);
    }

    /**
     * The chart type for the shape of the report: a line over time, vertical bars for the hours of the day and the
     * days of the week, horizontal bars for anything else. The switch on the chart card overrides it.
     */
    chartShape() {
        const dimensions = this.dimensionColumns();
        if (dimensions.length === 0 || !this.chartMetric() || this.view.chartType === 'table') {
            return 'none';
        }
        const first = dimensions[0];
        const overTime = Boolean(first.granularity) && !MahoReportExplorer.CYCLES.includes(first.granularity);
        if (this.view.chartType === 'line') {
            return 'line';
        }
        if (this.view.chartType === 'bar') {
            return overTime || first.granularity ? 'bar' : 'hbar';
        }
        if (this.presetChart === 'none') {
            return 'none';
        }
        return overTime ? 'line' : first.granularity ? 'bar' : 'hbar';
    }

    renderChartHeader(shape) {
        const header = this.parts['chart-header'];
        header.textContent = '';
        const metric = this.chartMetric();
        header.append(this.element('h3', 'text-base font-semibold me-auto', metric ? metric.label : ''));
        if (!metric) {
            return;
        }
        const dataset = this.dataset();
        const overTime = this.dimensionColumns()[0]?.granularity && !MahoReportExplorer.CYCLES.includes(this.dimensionColumns()[0].granularity);
        if (dataset.dated && overTime) {
            header.append(this.button(this.t('addNote'), 'd-btn d-btn-xs', () => this.openNoteDialog()));
        }
        const datasetMetric = dataset.metrics.find((m) => m.code === metric.key);
        if (datasetMetric?.additive && dataset.dated) {
            header.append(this.button(this.t('targets'), 'd-btn d-btn-xs', () => this.openTargetsDialog(metric)));
        }
        if (dataset.dated) {
            header.append(this.button(this.t('explainChange'), 'd-btn d-btn-xs', () => this.openExplain(this.state, null)));
        }
        if (dataset.records) {
            header.append(this.button(this.t('showRecords'), 'd-btn d-btn-xs', () => this.openRecords({ ...this.state, dimensions: [] }, null)));
        }
        const group = this.element('div', 'd-join');
        group.setAttribute('role', 'group');
        group.setAttribute('aria-label', this.t('chartType'));
        for (const type of ['auto', 'line', 'bar', 'table']) {
            const option = this.button(this.t(type), `d-btn d-btn-xs d-join-item ${this.view.chartType === type ? 'd-btn-active' : ''}`, () => {
                this.setView({ chartType: type });
                this.renderChart(this.runToken);
            });
            option.setAttribute('aria-pressed', String(this.view.chartType === type));
            group.append(option);
        }
        header.append(group);
        this.parts['chart-box'].hidden = shape === 'none';
    }

    /**
     * The rows that the chart draws. A report by the period only draws its own rows, which have every bucket. Other
     * shapes run their own requests, because the table shows one page of rows in its own order.
     */
    async chartData(shape) {
        const metric = this.view.chartMetric;
        const dimensions = this.dimensionColumns();
        const first = dimensions[0];
        const request = { ...this.state, metrics: [metric], sort: undefined, offset: undefined };
        if (dimensions.length === 1 && first.granularity) {
            return { kind: 'single', rows: this.byPeriod(this.result.rows, first), compare: Boolean(this.result.comparePeriod) };
        }
        if (dimensions.length === 1) {
            const result = await this.runRequest({ ...request, sort: { key: metric, direction: 'desc' }, limit: MahoReportExplorer.CHART_TOP });
            return result.error ? null : { kind: 'single', rows: result.rows, compare: Boolean(result.comparePeriod) };
        }

        // Two groupings: the first one on the axis, and the top values of the second one as the series
        const dataset = this.dataset();
        const second = dimensions[1];
        const secondField = dataset.dimensions.find((d) => d.code === second.key)?.field;
        const top = await this.runRequest({ ...request, compare: undefined, dimensions: [this.state.dimensions[1]], sort: { key: metric, direction: 'desc' }, limit: MahoReportExplorer.SERIES_TOP });
        if (top.error) {
            return null;
        }
        const topRows = top.rows.slice(0, MahoReportExplorer.SERIES_TOP);
        const seriesValues = topRows.map((row) => row.dimensions[second.key]);
        const axisRequest = { ...request, compare: undefined, dimensions: [this.state.dimensions[0]] };
        if (!first.granularity) {
            Object.assign(axisRequest, { sort: { key: metric, direction: 'desc' }, limit: MahoReportExplorer.CHART_TOP });
        }
        const axis = await this.runRequest(axisRequest);
        if (axis.error) {
            return null;
        }
        const filters = [...(this.state.filters ?? [])];
        if (secondField && !seriesValues.includes(null)) {
            filters.push({ field: secondField, operator: 'in', value: seriesValues });
        }
        const firstField = dataset.dimensions.find((d) => d.code === first.key)?.field;
        if (!first.granularity && firstField && !axis.rows.some((row) => row.dimensions[first.key] === null)) {
            filters.push({ field: firstField, operator: 'in', value: axis.rows.map((row) => row.dimensions[first.key]) });
        }
        const grid = await this.runRequest({ ...request, compare: undefined, dimensions: this.state.dimensions.slice(0, 2), filters, limit: this.config.maxRows });
        if (grid.error) {
            return null;
        }
        const axisRows = first.granularity ? this.byPeriod(axis.rows, first) : axis.rows;
        return { kind: 'series', axis: axisRows, series: topRows, grid: grid.rows, first: first.key, second: second.key };
    }

    async renderChart(token) {
        const card = this.parts['chart-card'];
        if (!this.result) {
            return;
        }
        const own = ++this.chartToken;
        const shape = this.chartShape();
        this.renderChartHeader(shape);
        this.renderNotes();
        card.hidden = !this.chartMetric() || this.dimensionColumns().length === 0;
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
        if (shape === 'none' || card.hidden) {
            return;
        }
        let data;
        try {
            await this.loadChartJs();
            data = await this.chartData(shape);
        } catch (error) {
            console.error(error);
            return;
        }
        if (token !== this.runToken || own !== this.chartToken || !data) {
            return;
        }
        if (this.chart) {
            this.chart.destroy();
        }
        this.chart = this.drawChart(this.parts.chart, shape, data, this.chartMetric(), this.result);
    }

    drawChart(canvas, shape, data, metric, result, { compact = false } = {}) {
        const palette = this.palette();
        const text = this.color('--color-base-content');
        const grid = this.color('--color-base-300');
        const type = shape === 'line' ? 'line' : 'bar';
        const datasets = [];
        let labels;
        let rows;
        if (data.kind === 'single') {
            rows = data.rows;
            const firstKey = result.columns.find((c) => c.kind === 'dimension')?.key;
            labels = rows.map((row) => row.labels?.[firstKey] ?? row.dimensions[firstKey]);
            datasets.push({
                label: metric.label,
                data: rows.map((row) => row.values[metric.key]),
                borderColor: palette[0],
                backgroundColor: type === 'line' ? this.color('--color-primary', 0.12) : palette[0],
                fill: type === 'line',
                cubicInterpolationMode: 'monotone',
                pointRadius: compact || rows.length > 40 ? 0 : 3,
            });
            if (data.compare && !compact) {
                datasets.push({
                    label: `${metric.label} (${this.t('earlier')})`,
                    data: rows.map((row) => row.compare?.[metric.key] ?? null),
                    borderColor: this.color('--color-base-content', 0.4),
                    backgroundColor: this.color('--color-base-content', 0.15),
                    borderDash: [5, 4],
                    cubicInterpolationMode: 'monotone',
                    pointRadius: 0,
                });
            }
            const target = result.targets?.[metric.key];
            if (target && type === 'line' && !compact) {
                datasets.push({
                    label: this.t('target'),
                    data: rows.map((row) => target.buckets[String(row.dimensions[firstKey])] ?? null),
                    borderColor: this.color('--color-success'),
                    borderDash: [2, 3],
                    pointRadius: 0,
                    fill: false,
                });
            }
        } else {
            rows = data.axis;
            labels = rows.map((row) => row.labels?.[data.first] ?? row.dimensions[data.first]);
            data.series.forEach((series, index) => {
                const value = series.dimensions[data.second];
                const values = rows.map((axisRow) => {
                    const match = data.grid.find((row) => row.dimensions[data.first] === axisRow.dimensions[data.first] && row.dimensions[data.second] === value);
                    return match ? match.values[metric.key] : (type === 'line' ? 0 : null);
                });
                datasets.push({
                    label: series.labels?.[data.second] ?? String(value),
                    data: values,
                    borderColor: palette[index % palette.length],
                    backgroundColor: palette[index % palette.length],
                    cubicInterpolationMode: 'monotone',
                    pointRadius: rows.length > 40 ? 0 : 2,
                    seriesValue: value,
                });
            });
        }

        const horizontal = shape === 'hbar';
        const valueAxis = { ticks: { color: text, callback: (value) => this.format(metric, value) }, grid: { color: grid }, beginAtZero: true };
        const categoryAxis = { ticks: { color: text, autoSkip: true, maxRotation: 0, maxTicksLimit: compact ? 4 : undefined }, grid: { display: false } };
        const notes = compact || horizontal ? [] : (result.annotations ?? []);
        const legend = !compact && datasets.length > 1;
        const firstKey = result.columns.find((c) => c.kind === 'dimension')?.key;
        const self = this;
        return new Chart(canvas, {
            type,
            data: { labels, datasets },
            options: {
                maintainAspectRatio: false,
                animation: false,
                // A free row above the plot for the text of the notes
                layout: { padding: { top: notes.length && !legend ? 18 : 0 } },
                indexAxis: horizontal ? 'y' : 'x',
                interaction: { mode: horizontal ? 'nearest' : 'index', intersect: horizontal },
                scales: horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis },
                plugins: {
                    legend: { display: legend, labels: { color: text, usePointStyle: true } },
                    tooltip: {
                        enabled: !compact,
                        callbacks: {
                            label: (context) => `${context.dataset.label}: ${this.format(metric, horizontal ? context.parsed.x : context.parsed.y)}`,
                            footer: (items) => notes.filter((note) => String(note.key) === String(rows[items[0]?.dataIndex]?.dimensions[firstKey])).map((note) => note.title),
                        },
                    },
                },
                onClick: compact ? undefined : (event, elements, chart) => {
                    const element = data.kind === 'series'
                        ? chart.getElementsAtEventForMode(event, 'nearest', { intersect: type !== 'line' }, true)[0]
                        : elements[0];
                    if (!element) {
                        return;
                    }
                    const row = structuredClone(rows[element.index]);
                    if (data.kind === 'series') {
                        const series = datasets[element.datasetIndex];
                        row.dimensions = { ...row.dimensions, [data.second]: series.seriesValue };
                        row.labels = { ...row.labels, [data.second]: series.label };
                    }
                    const rect = canvas.getBoundingClientRect();
                    // After the click reaches the document, which closes any open menu
                    setTimeout(() => self.openDrillMenu(row, { x: rect.left + event.x, y: rect.top + event.y }));
                },
            },
            plugins: [{
                id: 'reportNotes',
                beforeInit(chart) {
                    if (notes.length && legend) {
                        const fit = chart.legend.fit;
                        chart.legend.fit = function () {
                            fit.call(this);
                            this.height += 18;
                        };
                    }
                },
                afterDatasetsDraw(chart) {
                    if (!notes.length) {
                        return;
                    }
                    const { ctx, chartArea } = chart;
                    const meta = chart.getDatasetMeta(0);
                    const marks = new Map();
                    for (const note of notes) {
                        const point = meta.data[rows.findIndex((row) => String(row.dimensions[firstKey]) === String(note.key))];
                        if (point) {
                            marks.set(point.x, [...(marks.get(point.x) ?? []), note.title]);
                        }
                    }
                    const xs = [...marks.keys()].sort((a, b) => a - b);
                    ctx.save();
                    ctx.strokeStyle = self.color('--color-warning', 0.9);
                    ctx.fillStyle = self.color('--color-warning');
                    ctx.font = `600 11px ${getComputedStyle(canvas).fontFamily}`;
                    ctx.textBaseline = 'middle';
                    ctx.textAlign = 'left';
                    xs.forEach((x, i) => {
                        ctx.setLineDash([3, 3]);
                        ctx.beginPath();
                        ctx.moveTo(x, chartArea.top);
                        ctx.lineTo(x, chartArea.bottom);
                        ctx.stroke();
                        ctx.beginPath();
                        ctx.arc(x, chartArea.top, 4, 0, Math.PI * 2);
                        ctx.fill();
                        // Centered above the dot, inside half the space to each neighbor note, shortened when it does not fit
                        const from = i > 0 ? (xs[i - 1] + x) / 2 + 3 : chartArea.left;
                        const to = i < xs.length - 1 ? (x + xs[i + 1]) / 2 - 3 : chartArea.right;
                        let text = marks.get(x).join(', ');
                        while (text.length > 1 && ctx.measureText(text).width > to - from) {
                            text = `${text.slice(0, -2)}…`;
                        }
                        const width = ctx.measureText(text).width;
                        if (width > to - from) {
                            return;
                        }
                        ctx.fillText(text, Math.min(Math.max(x - width / 2, from), to - width), chartArea.top - 12);
                    });
                    ctx.restore();
                },
            }],
        });
    }

    renderNotes() {
        const holder = this.parts.notes;
        holder.textContent = '';
        for (const note of this.result?.annotations ?? []) {
            const chip = this.element('span', 'd-badge d-badge-outline d-badge-warning h-auto gap-1 whitespace-normal');
            chip.append(this.element('span', 'font-semibold', this.dayFormat.format(new Date(`${note.date}T00:00:00Z`))));
            chip.append(this.element('span', '', note.title));
            if (note.source === 'note' && (note.adminUserId === null || note.adminUserId === this.config.adminUserId)) {
                chip.append(this.removeButton(note.title, async () => {
                    const result = await this.post(this.config.urls.annotationDelete, { id: String(note.id) });
                    if (result.error) {
                        this.showErrors(result.message);
                        return;
                    }
                    this.run();
                }));
            }
            holder.append(chip);
        }
    }

    // Panels: records and explanation

    renderPanel() {
        const panel = this.parts.panel;
        if (!this.view.panel) {
            panel.textContent = '';
        }
    }

    panelCard(title, onClose) {
        const panel = this.parts.panel;
        panel.textContent = '';
        const card = this.element('section', 'd-card d-card-border bg-base-100');
        const body = this.element('div', 'd-card-body p-4 gap-3');
        const header = this.element('div', 'flex items-center gap-2');
        header.append(this.element('h3', 'text-base font-semibold me-auto', title));
        const close = this.button(this.t('close'), 'd-btn d-btn-ghost d-btn-xs', () => {
            this.setView({ panel: null });
            panel.textContent = '';
            onClose?.();
        });
        header.append(close);
        body.append(header);
        card.append(body);
        panel.append(card);
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return body;
    }

    async openRecords(request, label, offset = 0, sort = null) {
        this.setView({ panel: 'records' });
        const body = this.panelCard(label ? `${this.t('records')} · ${label}` : this.t('records'));
        body.append(this.element('p', 'text-sm opacity-70', this.t('loading')));
        const result = await this.post(this.config.urls.records, {
            request: JSON.stringify({ ...request, offset: undefined, sort: undefined }),
            offset: String(offset),
            sort: sort ?? '',
        });
        body.lastChild.remove();
        if (result.error) {
            this.showErrors(result.message, result.errors, body);
            return;
        }
        if (result.rows.length === 0) {
            body.append(this.element('p', 'text-sm opacity-70', this.t('noRecords')));
            return;
        }
        const scroller = this.element('div', 'overflow-x-auto');
        const table = this.element('table', 'd-table d-table-sm d-table-zebra');
        const head = this.element('tr');
        for (const column of result.columns) {
            const th = this.element('th', ['money', 'number'].includes(column.format) ? 'text-end' : '');
            const current = sort === column.key ? 'asc' : sort === `-${column.key}` ? 'desc' : null;
            const button = this.button(column.label + (current === 'asc' ? ' ▲' : current === 'desc' ? ' ▼' : ''), 'd-btn d-btn-ghost d-btn-xs',
                () => this.openRecords(request, label, 0, current === 'desc' ? column.key : `-${column.key}`));
            th.append(button);
            head.append(th);
        }
        const thead = this.element('thead');
        thead.append(head);
        table.append(thead);
        const tbody = this.element('tbody');
        for (const row of result.rows) {
            const tr = this.element('tr');
            result.columns.forEach((column, index) => {
                const value = row.labels?.[column.key] ?? this.format(column, row.values[column.key]);
                const td = this.element('td', `whitespace-nowrap ${['money', 'number'].includes(column.format) ? 'text-end' : ''}`);
                // The first cell opens the record, so the link stays in view when the table scrolls
                if (index === 0 && row.url) {
                    const link = this.element('a', 'd-link', value);
                    link.href = row.url;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.title = result.routeTitle ?? this.t('open');
                    td.append(link);
                } else {
                    td.textContent = value;
                }
                tr.append(td);
            });
            tbody.append(tr);
        }
        table.append(tbody);
        scroller.append(table);
        body.append(scroller);
        const pager = this.element('div', 'flex items-center justify-end gap-2');
        pager.append(this.element('span', 'text-sm opacity-80', this.t('rowsOf', offset + 1, offset + result.rows.length, result.totalRows)));
        const previous = this.button(this.t('previous'), 'd-btn d-btn-sm', () => this.openRecords(request, label, Math.max(0, offset - result.limit), sort));
        previous.disabled = offset === 0;
        const next = this.button(this.t('next'), 'd-btn d-btn-sm', () => this.openRecords(request, label, offset + result.limit, sort));
        next.disabled = offset + result.rows.length >= result.totalRows;
        pager.append(previous, next);
        body.append(pager);
    }

    async openExplain(request, label) {
        const metric = this.chartMetric();
        if (!metric) {
            return;
        }
        this.setView({ panel: 'explain' });
        const body = this.panelCard(label ? `${this.t('explainTitle', metric.label)} · ${label}` : this.t('explainTitle', metric.label));
        body.append(this.element('p', 'text-sm opacity-70', this.t('loading')));
        const result = await this.post(this.config.urls.explain, {
            request: JSON.stringify({ ...request, metrics: [metric.key], dimensions: [], sort: undefined, offset: undefined }),
            metric: metric.key,
        });
        body.lastChild.remove();
        if (result.error) {
            this.showErrors(result.message, result.errors, body);
            return;
        }
        const column = { ...result.metric, key: result.metric.code };
        const total = result.total;
        const summary = this.element('p', 'text-sm');
        summary.textContent = this.t('explainSummary',
            this.format(column, total.current),
            this.format(column, total.earlier),
            `${result.comparePeriod.from} – ${result.comparePeriod.to}`);
        const badge = this.change(column, { percent: total.percent });
        if (badge) {
            summary.append(' ', badge);
        }
        body.append(summary);
        if (result.dimensions.length === 0) {
            body.append(this.element('p', 'text-sm opacity-70', this.t('noDrivers')));
            return;
        }
        const max = Math.max(...result.dimensions.flatMap((d) => d.drivers.map((driver) => Math.abs(driver.change))), 1);
        const grid = this.element('div', 'report-explorer-explain');
        for (const dimension of result.dimensions) {
            const block = this.element('div', 'flex flex-col gap-1');
            block.append(this.element('h4', 'text-sm font-semibold', dimension.label));
            const list = this.element('ul', 'flex flex-col gap-1');
            for (const driver of dimension.drivers) {
                const better = (driver.change > 0) === column.higherIsBetter;
                const li = this.element('li');
                const button = this.action('', 'report-explorer-driver w-full no-underline text-base-content rounded px-1 py-0.5 hover:bg-base-200', () => {
                    const filter = driver.value === '' ? { field: dimension.field, operator: 'empty', value: null } : { field: dimension.field, operator: 'eq', value: driver.value };
                    this.rememberLabel(dimension.field, driver.value, driver.label);
                    this.drillTo(`${dimension.label}: ${driver.label}`, { filters: [...(request.filters ?? []), filter], range: request.range, from: request.from, to: request.to });
                });
                button.title = driver.share === null ? this.t('onlyThis') : `${this.t('shareOfChange', this.number.format(driver.share))} · ${this.t('onlyThis')}`;
                button.append(this.element('span', 'truncate text-sm', driver.label));
                // No percent on a small earlier value, where it tells less than the change itself
                const percent = !driver.earlier ? ` (${this.t('newValue')})` : driver.percent === null ? '' : ` (${driver.percent > 0 ? '+' : ''}${this.number.format(driver.percent)} %)`;
                button.append(this.element('span', `text-xs whitespace-nowrap ${better ? 'text-success' : 'text-error'}`,
                    `${driver.change > 0 ? '+' : ''}${this.format(column, driver.change)}${percent}`));
                const bar = this.element('span', `block h-1.5 rounded ${better ? 'bg-success' : 'bg-error'}`);
                bar.style.width = `${Math.max(2, Math.abs(driver.change) / max * 100)}%`;
                button.append(bar);
                li.append(button);
                list.append(li);
            }
            block.append(list);
            grid.append(block);
        }
        body.append(grid);
        body.append(this.element('p', 'text-xs opacity-70', this.t('explainHint')));
    }

    openNoteDialog(day = null) {
        const today = this.today();
        const content = `
            <form class="flex flex-col gap-3" onsubmit="return false">
                <label class="flex flex-col gap-1">
                    <span class="font-semibold">${this.escape(this.t('date'))}</span>
                    <input class="d-input w-full" type="date" name="date" required value="${this.escape(day ?? this.state.to ?? today)}">
                </label>
                <label class="flex flex-col gap-1">
                    <span class="font-semibold">${this.escape(this.t('note'))}</span>
                    <input class="d-input w-full" name="title" maxlength="255" required placeholder="${this.escape(this.t('noteHint'))}">
                </label>
                <p class="text-error text-sm" data-role="dialog-error" hidden></p>
            </form>`;
        Dialog.confirm(content, {
            title: this.t('addNote'),
            okLabel: this.t('save'),
            onOpen: (dialog) => dialog.querySelector('[name=title]').focus(),
            onOk: async (dialog) => {
                const result = await this.post(this.config.urls.annotationSave, {
                    date: dialog.querySelector('[name=date]').value,
                    title: dialog.querySelector('[name=title]').value,
                });
                if (result.error) {
                    const error = dialog.querySelector('[data-role=dialog-error]');
                    error.textContent = [result.message, ...(result.errors ?? []).map((e) => e.message)].join(' ');
                    error.hidden = false;
                    return false;
                }
                this.run();
                return true;
            },
        });
    }

    async openTargetsDialog(metric) {
        const list = await this.post(this.config.urls.targets, { dataset: this.state.dataset, metric: metric.key });
        if (list.error) {
            this.showErrors(list.message);
            return;
        }
        const storeId = this.state.storeIds?.length === 1 ? Number(this.state.storeIds[0]) : 0;
        const rows = list.targets.filter((target) => target.storeId === storeId)
            .map((target) => `<li class="flex items-center gap-2"><span class="w-24">${this.escape(target.month)}</span>`
                + `<span class="flex-1 text-end">${this.escape(this.format(metric, target.value))}</span>`
                + `<button type="button" class="d-btn d-btn-ghost d-btn-xs" data-delete="${target.id}" aria-label="${this.escape(`${this.t('delete')} ${target.month}`)}">×</button></li>`)
            .join('');
        const month = this.today().slice(0, 7);
        const content = `
            <form class="flex flex-col gap-3" onsubmit="return false">
                <p class="text-sm">${this.escape(this.t('targetsHint'))}</p>
                <ul class="flex flex-col gap-1 max-h-60 overflow-y-auto">${rows || `<li class="text-sm opacity-70">${this.escape(this.t('noTargets'))}</li>`}</ul>
                <div class="flex items-end gap-2">
                    <label class="flex flex-col gap-1">
                        <span class="font-semibold text-sm">${this.escape(this.t('month'))}</span>
                        <input class="d-input d-input-sm" type="month" name="month" required value="${month}">
                    </label>
                    <label class="flex flex-col gap-1 flex-1">
                        <span class="font-semibold text-sm">${this.escape(this.t('targetValue'))}</span>
                        <input class="d-input d-input-sm w-full" type="number" step="any" name="value" required>
                    </label>
                </div>
                <p class="text-error text-sm" data-role="dialog-error" hidden></p>
            </form>`;
        Dialog.confirm(content, {
            title: `${this.t('targets')}: ${metric.label}`,
            okLabel: this.t('save'),
            onOpen: (dialog) => {
                dialog.querySelectorAll('[data-delete]').forEach((button) => button.addEventListener('click', async () => {
                    const result = await this.post(this.config.urls.targetDelete, { id: button.dataset.delete });
                    if (!result.error) {
                        button.closest('li').remove();
                        this.run();
                    }
                }));
                dialog.querySelector('[name=value]').focus();
            },
            onOk: async (dialog) => {
                const result = await this.post(this.config.urls.targetSave, {
                    dataset: this.state.dataset,
                    metric: metric.key,
                    month: dialog.querySelector('[name=month]').value,
                    value: dialog.querySelector('[name=value]').value,
                    store_id: String(storeId),
                });
                if (result.error) {
                    const error = dialog.querySelector('[data-role=dialog-error]');
                    error.textContent = [result.message, ...(result.errors ?? []).map((e) => e.message)].join(' ');
                    error.hidden = false;
                    return false;
                }
                this.run();
                return true;
            },
        });
    }

    async togglePin(report, pinned) {
        const result = await this.post(this.config.urls.pin, { id: String(report.id), pinned: pinned ? '1' : '' });
        if (result.error) {
            this.showErrors(result.message);
            return;
        }
        this.saved = result.saved;
        this.dashboard = result.dashboard;
        if (this.config.mode === 'dashboard') {
            this.renderDashboard();
        } else {
            this.renderControls();
        }
    }

    async renderDashboard() {
        this.charts.forEach((chart) => chart.destroy());
        this.charts = [];
        const holder = this.parts.dashboard;
        holder.textContent = '';
        const reports = this.dashboard.map((id) => this.saved.find((report) => report.id === id)).filter(Boolean);
        await this.loadChartJs().catch(() => null);
        await Promise.all(reports.map(async (report) => {
            // A box of the admin dashboard, as Visitors and Sales Overview are
            const card = this.element('div', 'entry-edit');
            const header = this.element('div', 'entry-edit-head flex items-baseline gap-2');
            const heading = this.element('h4', 'me-auto');
            const title = this.element('a', 'report-explorer-title hover:underline', report.name);
            title.href = `${this.config.urls.index}?saved=${report.id}`;
            heading.append(title);
            const unpin = this.button('★', 'd-btn d-btn-ghost d-btn-xs', () => this.togglePin(report, false));
            unpin.setAttribute('aria-label', `${this.t('unpin')}: ${report.name}`);
            unpin.title = this.t('unpin');
            header.append(heading, unpin);
            const body = this.element('fieldset', 'flex flex-col gap-2 pt-1');
            card.append(header, body);
            holder.append(card);

            const result = await this.runRequest(report.request);
            if (result.error) {
                this.showErrors(result.message, [], body);
                return;
            }
            const metric = result.columns.find((column) => column.kind === 'metric' && column.format !== 'date');
            if (!metric) {
                return;
            }
            const kpi = this.element('div', 'flex items-baseline gap-2 flex-wrap');
            kpi.append(this.element('span', 'text-xs opacity-70', metric.label));
            kpi.append(this.element('strong', 'text-2xl', this.format(metric, result.totals.values[metric.key])));
            const badge = this.change(metric, result.totals.change?.[metric.key]);
            if (badge) {
                kpi.append(badge);
            }
            body.append(kpi);
            const dimensions = result.columns.filter((column) => column.kind === 'dimension');
            const dimension = dimensions.length === 1 ? dimensions[0] : null;
            if (dimension && window.Chart && result.rows.length > 0) {
                const box = this.element('div', 'relative h-36');
                const canvas = this.element('canvas');
                box.append(canvas);
                body.append(box);
                const overTime = dimension.granularity && !MahoReportExplorer.CYCLES.includes(dimension.granularity);
                const rows = overTime ? this.byPeriod(result.rows, dimension) : [...result.rows].sort((a, b) => (b.values[metric.key] ?? 0) - (a.values[metric.key] ?? 0)).slice(0, 5);
                this.charts.push(this.drawChart(canvas, overTime ? 'line' : (dimension.granularity ? 'bar' : 'hbar'), { kind: 'single', rows, compare: false }, metric, result, { compact: true }));
            }
        }));
    }

    export() {
        const form = this.element('form');
        form.method = 'post';
        form.action = this.config.urls.export;
        form.hidden = true;
        const fields = {
            form_key: getFormKey(),
            request: JSON.stringify({ ...this.state, offset: undefined }),
            state: this.encode(JSON.stringify(this.state)),
        };
        for (const [name, value] of Object.entries(fields)) {
            const input = this.element('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value ?? '';
            form.append(input);
        }
        document.body.append(form);
        form.submit();
        form.remove();
    }

    openSaveDialog() {
        const current = this.saved.find((report) => report.id === this.savedId);
        const own = current?.mine ? current : null;
        const content = `
            <form class="flex flex-col gap-3" onsubmit="return false">
                <label class="flex flex-col gap-1">
                    <span class="font-semibold">${this.escape(this.t('name'))}</span>
                    <input class="d-input w-full" name="name" maxlength="255" required value="${this.escape(own?.name ?? '')}">
                </label>
                <label class="flex items-center gap-2">
                    <input class="d-checkbox" type="checkbox" name="shared" ${own?.shared ? 'checked' : ''}>
                    <span>${this.escape(this.t('shareReport'))}</span>
                </label>
                <label class="flex items-center gap-2">
                    <input class="d-checkbox" type="checkbox" name="pinned" ${own && this.dashboard.includes(own.id) ? 'checked' : ''}>
                    <span>${this.escape(this.t('pin'))}</span>
                </label>
                <p class="text-error text-sm" data-role="dialog-error" hidden></p>
            </form>`;
        Dialog.confirm(content, {
            title: this.t('save'),
            okLabel: this.t('save'),
            onOpen: (dialog) => dialog.querySelector('[name=name]').focus(),
            onOk: async (dialog) => {
                // The metric of the chart goes first, because the dashboard card shows the first metric
                const chartMetric = this.view.chartMetric;
                const metrics = chartMetric ? [chartMetric, ...(this.state.metrics ?? []).filter((code) => code !== chartMetric)] : this.state.metrics;
                const params = {
                    name: dialog.querySelector('[name=name]').value.trim(),
                    shared: dialog.querySelector('[name=shared]').checked ? '1' : '0',
                    request: JSON.stringify({ ...this.state, metrics, offset: undefined }),
                };
                if (own) {
                    params.id = String(own.id);
                }
                const result = await this.post(this.config.urls.savedSave, params);
                if (result.error) {
                    const error = dialog.querySelector('[data-role=dialog-error]');
                    error.textContent = [result.message, ...(result.errors ?? []).map((e) => e.message)].join(' ');
                    error.hidden = false;
                    return false;
                }
                this.saved = result.saved;
                this.savedId = result.report.id;
                const pinned = dialog.querySelector('[name=pinned]').checked;
                if (pinned !== this.dashboard.includes(result.report.id)) {
                    await this.togglePin(result.report, pinned);
                }
                this.renderControls();
                return true;
            },
        });
    }

    openEmailDialog(report) {
        const subscription = this.subscriptions[report.id] ?? null;
        const dataset = this.dataset(report.request.dataset);
        const option = (value, label, selected) => `<option value="${this.escape(value)}" ${selected ? 'selected' : ''}>${this.escape(label)}</option>`;
        const frequencies = [['', this.t('emailNever')], ['daily', this.t('daily')], ['weekly', this.t('weekly')], ['monthly', this.t('monthly')]]
            .map(([value, label]) => option(value, label, (subscription?.frequency ?? '') === value)).join('');
        const metrics = (dataset?.metrics ?? []).map((metric) => option(metric.code, metric.label,
            (subscription?.alertMetric ?? report.request.metrics?.[0]) === metric.code)).join('');
        const directions = [['down', this.t('alertDown')], ['up', this.t('alertUp')], ['any', this.t('alertAny')]]
            .map(([value, label]) => option(value, label, (subscription?.alertDirection ?? 'down') === value)).join('');
        const alert = dataset?.dated ? `
                <label class="flex items-center gap-2">
                    <input class="d-checkbox" type="checkbox" name="alert" ${subscription?.alertMetric ? 'checked' : ''}>
                    <span>${this.escape(this.t('alertOnly'))}</span>
                </label>
                <div class="flex flex-wrap items-center gap-2" data-role="alert">
                    <select class="d-select d-select-sm w-auto" name="alert_metric" aria-label="${this.escape(this.t('alertMetric'))}">${metrics}</select>
                    <select class="d-select d-select-sm w-auto" name="alert_direction" aria-label="${this.escape(this.t('alertDirection'))}">${directions}</select>
                    <input class="d-input d-input-sm w-24" type="number" name="alert_threshold" min="0.01" max="1000" step="0.01"
                        value="${this.escape(subscription?.alertThreshold ?? 20)}" aria-label="${this.escape(this.t('alertThreshold'))}">
                    <span>%</span>
                </div>` : '';
        const content = `
            <form class="flex flex-col gap-3" onsubmit="return false">
                <label class="flex flex-col gap-1">
                    <span class="font-semibold">${this.escape(this.t('emailFrequency'))}</span>
                    <select class="d-select w-full" name="frequency">${frequencies}</select>
                </label>
                ${alert}
                <p class="text-sm opacity-70">${this.escape(this.t('emailHelp'))}</p>
                <p class="text-error text-sm" data-role="dialog-error" hidden></p>
            </form>`;
        Dialog.confirm(content, {
            title: this.t('emailReport', report.name),
            okLabel: this.t('save'),
            onOpen: (dialog) => {
                const checkbox = dialog.querySelector('[name=alert]');
                const sync = () => dialog.querySelectorAll('[data-role=alert] :is(select, input)').forEach((field) => {
                    field.disabled = !checkbox.checked;
                });
                checkbox?.addEventListener('change', sync);
                if (checkbox) {
                    sync();
                }
            },
            onOk: async (dialog) => {
                const frequency = dialog.querySelector('[name=frequency]').value;
                const params = { id: String(report.id) };
                let url = this.config.urls.subscriptionDelete;
                if (frequency) {
                    url = this.config.urls.subscriptionSave;
                    params.frequency = frequency;
                    if (dialog.querySelector('[name=alert]')?.checked) {
                        for (const name of ['alert_metric', 'alert_direction', 'alert_threshold']) {
                            params[name] = dialog.querySelector(`[name=${name}]`).value;
                        }
                    }
                }
                const result = await this.post(url, params);
                if (result.error) {
                    const error = dialog.querySelector('[data-role=dialog-error]');
                    error.textContent = [result.message, ...(result.errors ?? []).map((e) => e.message)].join(' ');
                    error.hidden = false;
                    return false;
                }
                this.subscriptions = result.subscriptions;
                this.renderControls();
                return true;
            },
        });
    }

    deleteSaved(report) {
        Dialog.confirm(`<p>${this.escape(this.t('confirmDelete', report.name))}</p>`, {
            title: this.t('deleteReport'),
            okLabel: this.t('delete'),
            onOk: async () => {
                const result = await this.post(this.config.urls.savedDelete, { id: String(report.id) });
                if (result.error) {
                    this.showErrors(result.message);
                    return true;
                }
                this.saved = result.saved;
                this.dashboard = this.dashboard.filter((id) => id !== report.id);
                this.savedId = null;
                this.renderControls();
                return true;
            },
        });
    }
}

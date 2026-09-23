// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

// Admin CRUD driver for AdminCrudTest.php. The test evaluates this file on a logged-in admin page,
// sets window.__crud.password and productId, and awaits the functions below. Each one returns plain data.
(() => {
  const C = window.__crud = {};
  C.adminBase = location.href.replace(/^(https?:\/\/[^/]+\/[^/]+\/).*$/, '$1');
  C.password = '';
  C.productId = '';
  C.FATAL = /TypeError|must be of type|Fatal error|Stack trace|There has been an error|Uncaught |(Deprecated functionality|Warning|Notice): [^<]{0,300}? in \/[^\s<]+\.php/;

  const quote = s => s.replace(/[.*+?^${}()|[\]\\/]/g, '\\$&');
  const base = () => quote(C.adminBase);
  const parse = html => new DOMParser().parseFromString(html, 'text/html');
  const snip = (t, m) => t.slice(Math.max(0, m.index - 80), m.index + 1500).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ');
  const short = u => (u || '').replace(C.adminBase, '').replace(/key\/[0-9a-f]+\/?/, '');
  const get = async u => { const r = await fetch(u, {credentials: 'same-origin'}); return {r, t: await r.text()}; };
  const post = async (u, fd) => { const r = await fetch(u, {method: 'POST', body: fd, credentials: 'same-origin'}); return {r, t: await r.text()}; };
  const ajax = u => fetch(u, {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}}).then(r => r.text());
  const messages = t => {
    const d = parse(t);
    const read = sel => [...d.querySelectorAll(sel)].map(e => e.innerText.trim()).filter(Boolean);
    return {ok: read('.success-msg li, li.success-msg'), err: read('.error-msg li, li.error-msg')};
  };
  const outcome = t => { const m = messages(t); return (m.ok[0] || '-') + (m.err.length ? ' ERR: ' + m.err.join(' | ') : ''); };

  C.menu = () => [...new Set([...document.querySelectorAll('.nav-bar a[href], #nav a[href]')]
    .map(a => a.href).filter(h => h.startsWith(C.adminBase) && !/logout|\/ai\/reindex\//.test(h) && !h.endsWith('#')))];

  C.configSections = async () => {
    const cfg = C.menu().find(h => h.includes('/system_config/index/'));
    return [...new Set([...parse((await get(cfg)).t).querySelectorAll('a[href*="system_config/edit/section/"]')].map(a => a.href))];
  };

  // Load every menu page and every config section. Returns the pages that fail.
  C.crawl = async () => {
    const failed = [];
    for (const u of [...new Set(C.menu().concat(await C.configSections()))]) {
      const {r, t} = await get(u);
      const m = t.match(C.FATAL);
      if (r.status !== 200 || m) failed.push(`${short(u)} ${r.status} ${m ? snip(t, m) : ''}`);
    }
    return failed;
  };

  // Every create form of the admin: the "Add New" button of each grid, each product type,
  // the tax rate and the website, store and store view forms.
  C.createForms = async () => {
    const found = new Set();
    for (const u of C.menu()) {
      const {t} = await get(u);
      const re = new RegExp(base() + '(?:[a-z_]+\\/new|tax_rate\\/add|system_store\\/new(?:Website|Group|Store))\\/key\\/[0-9a-f]+\\/', 'g');
      for (const m of t.matchAll(re)) found.add(m[0]);
    }
    const product = [...found].find(u => u.includes('/catalog_product/new/'));
    if (product) {
      found.delete(product);
      for (const type of ['simple', 'virtual', 'downloadable', 'grouped', 'bundle', 'configurable', 'giftcard']) {
        found.add(product.replace('/new/', `/new/set/4/type/${type}/`));
      }
    }
    return [...found];
  };

  const frame = url => new Promise(resolve => {
    const f = document.createElement('iframe');
    f.style.cssText = 'position:absolute;left:-5000px;width:1400px;height:900px';
    let done = false;
    const finish = () => { if (!done) { done = true; setTimeout(() => resolve(f), 1500); } };
    f.onload = finish;
    setTimeout(finish, 20000);
    f.src = url;
    document.body.appendChild(f);
  });
  const findForm = d => d.querySelector('form#edit_form') || [...d.querySelectorAll('form')]
    .find(f => f.action && /\/save/.test(f.action) && !/search|filter|login/.test(f.action));

  const set = (f, name, v) => { const e = f.querySelector(`[name="${name}"]`); if (!e) return null; e.value = v; return name; };
  const lastOption = (f, name) => {
    const s = f.querySelector(`select[name="${name}"]`);
    if (!s) return null;
    [...s.options].forEach((o, i) => { o.selected = i === s.options.length - 1; });
    return name;
  };
  const choose = (f, name, pattern) => {
    const s = f.querySelector(`select[name="${name}"]`);
    const o = s && [...s.options].find(o => pattern.test(o.value));
    if (!o) return null;
    s.value = o.value;
    s.dispatchEvent(new Event('change', {bubbles: true}));
    return name;
  };
  // Values that the generic filler cannot guess, by the path of the create form.
  C.presets = {
    catalog_product_attribute: (f, tag) => [set(f, 'frontend_label[0]', tag), set(f, 'attribute_code', tag)],
    directory_country: f => [set(f, 'country_id', 'QZ'), set(f, 'iso2_code', 'QZ'), set(f, 'iso3_code', 'QZQ')],
    sitemap: (f, tag) => [set(f, 'sitemap_path', '/'), set(f, 'sitemap_filename', tag + '.xml')],
    customer: (f, tag) => [set(f, 'account[firstname]', 'Crud'), set(f, 'account[lastname]', tag), set(f, 'account[email]', tag + '@example.com')],
    feedmanager_destination: (f, tag) => [choose(f, 'type', /^sftp$/), set(f, 'config[host]', 'example.com'), set(f, 'config[username]', tag), set(f, 'config[password]', tag)],
    // The first class and rate of each list already form a rule in the sample data
    tax_rule: f => ['tax_customer_class[]', 'tax_product_class[]', 'tax_rate[]'].map(n => lastOption(f, n)),
    permissions_block: (f, tag) => [set(f, 'block_name', 'crud/' + tag)],
    catalog_product_review: f => [set(f, 'product_id', String(C.productId))],
  };
  // A grid that does not show the tag: the value to search for instead.
  C.search = {directory_country: 'QZ'};
  // The forms that ask for the admin password, or set the password of an admin or API user.
  const PASSWORD_FORMS = /^(permissions_user|permissions_role|api_user|api_role|api2_role|apiplatform_user|oauth_consumer)$/;

  const fillPasswords = (form, entity) => {
    if (!PASSWORD_FORMS.test(entity)) return;
    for (const e of form.querySelectorAll('input[type=password]')) {
      if (!e.disabled && e.value === '') e.value = C.password;
    }
  };

  // Fill every empty required field, and the password fields of the admin and API user forms.
  C.fill = (form, tag, entity) => {
    fillPasswords(form, entity);
    for (const e of form.querySelectorAll('input, select, textarea')) {
      if (!e.name || e.disabled) continue;
      const type = (e.type || '').toLowerCase();
      if (['button', 'submit', 'file', 'image', 'reset', 'hidden', 'password'].includes(type)) continue;
      if (!(e.classList.contains('required-entry') || e.required || e.classList.contains('validate-select'))) continue;
      if (type === 'checkbox' || type === 'radio') {
        if (!form.querySelector(`input[name="${CSS.escape(e.name)}"]:checked`)) e.checked = true;
        continue;
      }
      if (e.tagName === 'SELECT') {
        if (![...e.options].some(o => o.selected && o.value !== '')) {
          const opts = [...e.options].filter(o => o.value !== '');
          const pick = (e.multiple && opts.find(o => o.value === '0')) || opts.find(o => o.value !== '0') || opts[0];
          if (pick) pick.selected = true;
        }
        continue;
      }
      if (e.value !== '') continue;
      const n = e.name.toLowerCase(), c = e.className;
      if (/email/.test(n) || /validate-email/.test(c)) e.value = tag + '@example.com';
      else if (/validate-(number|digits|greater|zero|percent|not-negative)|validate-int/.test(c) || /qty|price|amount|rate|sort|position|priority|percent|weight|number|size|count|limit|days|balance/.test(n)) e.value = '1';
      else if (/validate-date/.test(c) || /date/.test(n)) e.value = new Date().toLocaleDateString('en-US');
      else if (/validate-url/.test(c)) e.value = 'https://example.com/' + tag;
      else e.value = tag;
    }
    (C.presets[entity] || (() => []))(form, tag);
  };

  // The admin form script posts the form and adds the form key. Do the same.
  const formData = (form, win) => {
    const fd = new FormData(form);
    if (!fd.get('form_key')) fd.set('form_key', win.FORM_KEY || '');
    return fd;
  };

  const gridHtml = async indexUrl => {
    const {t} = await get(indexUrl);
    const g = t.match(/varienGrid\(\s*'[^']+'\s*,\s*'([^']+)'/);
    return g ? t + await ajax(g[1]) : t;
  };
  const editLinks = html => new Set([...parse(html).querySelectorAll('tr[title], tr a[href]')]
    .map(e => e.getAttribute('title') || e.getAttribute('href'))
    .filter(h => h && /\/edit[A-Za-z]*\/.*\/key\//.test(h))
    .map(h => h.replace(/key\/[0-9a-f]+\/?/, '')));
  const indexOf = entity => C.menu().find(h => h.includes('/' + entity + '/index/'));

  // Find the edit url of the grid row that holds $tag. A grid keeps its last filter in the
  // admin session, so the filter is cleared again after the search.
  const findEdit = async (indexUrl, tag) => {
    const {t} = await get(indexUrl);
    const row = html => {
      for (const tr of parse(html).querySelectorAll('tr')) {
        if (!tr.textContent.includes(tag)) continue;
        const title = tr.getAttribute('title');
        if (title && /^http/.test(title)) return title;
        const a = [...tr.querySelectorAll('a[href]')].map(a => a.href).find(h => /\/edit[A-Za-z]*\//.test(h));
        if (a) return a;
      }
      return null;
    };
    const hit = row(t);
    if (hit) return hit;
    const g = t.match(/varienGrid\(\s*'[^']+'\s*,\s*'([^']+)'\s*,\s*'[^']*'\s*,\s*'[^']*'\s*,\s*'[^']*'\s*,\s*'([^']*)'/);
    if (!g) return null;
    const grid = v => g[1] + (g[1].includes('?') ? '&' : '?') + (g[2] || 'filter') + '=' + v;
    try {
      for (const n of new Set([...parse(t).querySelectorAll('.filter input[type=text]')].map(i => i.name).filter(Boolean))) {
        const found = row(await ajax(grid(encodeURIComponent(btoa(n + '=' + tag)))));
        if (found) return found;
      }
      return null;
    } finally {
      await ajax(grid(''));
    }
  };

  // Create, read, update and delete through one create form. Returns the steps that ran and
  // their outcome; a step that fails ends the run.
  C.one = async createUrl => {
    const tag = 'crud' + Math.random().toString(36).slice(2, 8);
    const entity = short(createUrl).replace(/\/(new|add|new[A-Z]\w*)\/.*$/, '');
    const index = indexOf(entity);
    const before = index ? editLinks(await gridHtml(index)) : new Set();
    const rep = {form: short(createUrl).replace(/\/$/, ''), steps: {}};
    const stop = (step, why) => { rep.steps[step] = why; return rep; };
    let f = await frame(createUrl);
    try {
      let doc = f.contentDocument, html = doc.documentElement.outerHTML, m = html.match(C.FATAL);
      if (m) return stop('new', 'FATAL ' + snip(html, m));
      let form = findForm(doc);
      if (!form) return stop('new', 'no form at ' + short(f.contentWindow.location.href));
      C.fill(form, tag, entity);
      let fd = formData(form, f.contentWindow);
      fd.set('back', 'edit');
      let res = await post(form.action, fd);
      m = res.t.match(C.FATAL);
      if (m) return stop('create', 'FATAL ' + snip(res.t, m));
      rep.steps.create = outcome(res.t);
      if (messages(res.t).err.length) return rep;
      let editUrl = /\/edit[A-Za-z]*\/.*\d+\//.test(short(res.r.url)) ? res.r.url : await findEdit(res.r.url, C.search[entity] || tag);
      if (!editUrl && index) {
        const added = [...editLinks(await gridHtml(index))].filter(l => !before.has(l));
        const html = await gridHtml(index);
        if (added.length === 1) editUrl = [...parse(html).querySelectorAll('tr[title], tr a[href]')].map(e => e.getAttribute('title') || e.getAttribute('href')).find(h => h && h.replace(/key\/[0-9a-f]+\/?/, '') === added[0]);
      }
      if (!editUrl) return stop('read', 'the new record is not in its grid');
      f.remove();
      f = await frame(editUrl);
      doc = f.contentDocument; html = doc.documentElement.outerHTML; m = html.match(C.FATAL);
      if (m) return stop('read', 'FATAL ' + snip(html, m));
      rep.steps.read = 'ok';
      form = findForm(doc);
      if (!form) return stop('update', 'no form on the edit page');
      const changed = [...form.querySelectorAll('input[type=text], textarea')].find(e => e.value === tag && !e.disabled);
      if (changed) changed.value = tag + 'u';
      fillPasswords(form, entity);
      res = await post(form.action, formData(form, f.contentWindow));
      m = res.t.match(C.FATAL);
      if (m) return stop('update', 'FATAL ' + snip(res.t, m));
      rep.steps.update = outcome(res.t);
      const del = (html.match(new RegExp(base() + '[a-z_]+\\/delete(?:Website|Group|Store)?(?:Post)?\\/[^\'"\\s<>]*key\\/[0-9a-f]+\\/')) || [null])[0];
      if (!del) return stop('delete', 'no delete url on the edit page');
      // Some delete buttons post the whole edit form, with the record id and the admin password
      res = await post(del, formData(form, f.contentWindow));
      m = res.t.match(C.FATAL);
      if (m) return stop('delete', 'FATAL ' + snip(res.t, m));
      rep.steps.delete = outcome(res.t);
      return rep;
    } catch (e) {
      return stop('js', String(e));
    } finally {
      f.remove();
    }
  };

  const findUrl = (html, pattern) => (html.match(new RegExp(base() + pattern + '[^\'"\\s<>&]*key\\/[0-9a-f]+\\/[^\'"\\s<>&]*')) || [null])[0];
  const step = async (steps, name, run) => {
    const {r, t} = await run();
    const m = t.match(C.FATAL);
    steps[name] = m ? 'FATAL ' + snip(t, m) : outcome(t);
    return {r, t, ok: !m && messages(t).ok.length > 0};
  };
  const submitForm = (html, change) => {
    const form = parse(html).querySelector('#edit_form');
    const fd = new FormData(form);
    if (!fd.get('form_key')) fd.set('form_key', window.FORM_KEY);
    change(fd);
    return post(form.getAttribute('action'), fd);
  };

  // Run on the order create page. Pick the customer, the store and the product through the
  // methods that the page buttons call, switch "Same As Billing Address" off and on, choose
  // flat rate and check or money order, and submit. Then invoice with a shipment, refund
  // with an adjustment, print the PDFs and reorder.
  C.salesFlow = async (customerId, storeId, productId) => {
    const steps = {};
    const order = window.order;
    const pending = new Set();
    const load = order.loadArea.bind(order);
    order.loadArea = (...args) => {
      const p = load(...args);
      pending.add(p);
      p.finally(() => pending.delete(p));
      return p;
    };
    const settle = async () => {
      do {
        await new Promise(r => setTimeout(r, 300));
        await Promise.allSettled([...pending]);
      } while (pending.size);
      const m = document.documentElement.innerHTML.match(C.FATAL);
      if (m) throw new Error('FATAL ' + snip(document.documentElement.innerHTML, m));
    };
    try {
      order.setCustomerId(customerId); await settle();
      order.setStoreId(storeId); await settle();
      order.addProduct(productId); await settle();
      order.setShippingAsBilling(false); await settle();
      order.setShippingAsBilling(true); await settle();
      order.loadShippingRates(); await settle();
      order.setShippingMethod('flatrate_flatrate'); await settle();
      order.switchPaymentMethod('checkmo'); await settle();
    } catch (e) {
      steps.quote = String(e);
      return steps;
    }
    steps.quote = 'ok';

    const form = document.getElementById('edit_form');
    const fd = new FormData(form);
    if (!fd.get('form_key')) fd.set('form_key', window.FORM_KEY);
    let res = await step(steps, 'order', () => post(form.action, fd));
    if (!res.ok) return steps;
    const view = res.t;

    const invoiceUrl = findUrl(view, 'sales_order_invoice\\/(?:new|start)\\/');
    if (!invoiceUrl) return {...steps, invoice: 'no invoice link on the order page'};
    res = await step(steps, 'invoice', async () => {
      const page = await get(invoiceUrl);
      return submitForm(page.t, fd => { fd.set('invoice[do_shipment]', '1'); fd.set('invoice[send_email]', '1'); });
    });
    if (!res.ok) return steps;

    const invoiceView = await get(findUrl(res.t, 'sales_order_invoice\\/view\\/'));
    const shipmentView = await get(findUrl(res.t, 'sales_order_shipment\\/view\\/'));
    for (const [name, html, pattern] of [
      ['invoice pdf', invoiceView.t, 'sales_order_invoice\\/print\\/'],
      ['shipment pdf', shipmentView.t, 'sales_order_shipment\\/print\\/'],
    ]) {
      const url = findUrl(html, pattern);
      const body = url ? await fetch(url, {credentials: 'same-origin'}).then(r => r.text()) : '';
      steps[name] = body.startsWith('%PDF-') ? 'ok' : 'not a pdf: ' + body.slice(0, 200).replace(/<[^>]+>/g, ' ');
    }

    const creditmemoUrl = findUrl(res.t, 'sales_order_creditmemo\\/(?:new|start)\\/');
    if (!creditmemoUrl) return {...steps, creditmemo: 'no credit memo link on the order page'};
    res = await step(steps, 'creditmemo', async () => {
      const page = await get(creditmemoUrl);
      return submitForm(page.t, fd => {
        fd.set('creditmemo[do_offline]', '1');
        fd.set('creditmemo[adjustment_positive]', '1.5');
        fd.set('creditmemo[shipping_amount]', '0');
        fd.set('creditmemo[send_email]', '1');
        for (const k of [...fd.keys()].filter(k => /\[qty\]$/.test(k))) fd.set(k.replace('[qty]', '[back_to_stock]'), '1');
      });
    });
    if (!res.ok) return steps;

    const reorderUrl = findUrl(res.t, 'sales_order_create\\/reorder\\/');
    if (!reorderUrl) return {...steps, reorder: 'no reorder link on the order page'};
    const reorder = await get(reorderUrl);
    const m = reorder.t.match(C.FATAL);
    steps.reorder = m ? 'FATAL ' + snip(reorder.t, m) : (/order-items/.test(reorder.t) ? 'ok' : 'no items area');
    return steps;
  };

  // Save one config section with no change.
  C.saveSection = async url => {
    const f = await frame(url);
    try {
      const form = f.contentDocument.querySelector('#config_edit_form');
      if (!form) return 'no form';
      const res = await post(form.action, formData(form, f.contentWindow));
      const m = res.t.match(C.FATAL);
      return m ? 'FATAL ' + snip(res.t, m) : outcome(res.t);
    } finally {
      f.remove();
    }
  };
})();

/**
 * Maho
 *
 * @package     js
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * Custom error with translated message
 */
class MahoError extends Error {
    /**
     * @param {string} message - original message
     * @param {Any} ...args - sprintf like replacements
     */
    constructor(message, ...args) {
        if (typeof Translator !== 'undefined') {
            super(Translator.translate(message, ...args));
        } else {
            const formatted = message.replaceAll(/%[ds]/g, (match) => args.shift() ?? match);
            super(formatted);
        }
        this.name = 'MahoError';
        this.originalMessage = message;
    }
}

/**
 * @param {string} url - fetch url
 * @param {Object} [options] - fetch options
 * @param {Object} [options.loaderArea] - parameter to pass to showLoader(), false to disable
 */
async function mahoFetch(url, options) {
    const { loaderArea, ...fetchOptions } = options ?? {};
    try {
        if (loaderArea !== false && typeof showLoader === 'function') {
            showLoader(loaderArea)
        }
        if (fetchOptions?.method?.toUpperCase() === 'POST' && typeof FORM_KEY !== 'undefined') {
            fetchOptions.body ??= new URLSearchParams();
            if (fetchOptions.body instanceof URLSearchParams || fetchOptions.body instanceof FormData) {
                fetchOptions.body.set('form_key', fetchOptions.body.get('form_key') ?? FORM_KEY);
            }
        }

        url = new URL(url);
        url.searchParams.set('isAjax', true);

        const response = await fetch(url, fetchOptions);
        const result = response.headers.get('Content-Type') === 'application/json'
              ? await response.json()
              : await response.text();

        if (typeof result === 'object' && result !== null) {
            if (result.error) {
                const message = result.message ?? result.error;
                throw new MahoError(typeof message === 'string' ? message : 'An error occurred.');
            } else if (result.ajaxExpired && result.ajaxRedirect) {
                setLocation(result.ajaxRedirect);
                await new Promise((resolve) => {});
            }
        }
        if (!response.ok) {
            throw new MahoError('Server returned status %s', response.status);
        }
        if (loaderArea !== false && typeof hideLoader === 'function') {
            hideLoader();
        }

        return result;

    } catch (error) {
        console.error('mahoFetch error:', error);
        if (loaderArea !== false && typeof hideLoader === 'function') {
            hideLoader();
        }
        throw error;
    }
}

function mahoOnReady(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
    } else {
        callback();
    }
}

let mahoLoaderTimeout, mahoLoaderElement;
function showLoader(loaderArea) {
    loaderArea = (typeof loaderArea === 'string' ? document.getElementById(loaderArea) : loaderArea) || document.body;
    hideLoader();

    const overlay = document.createElement('div');
    overlay.className = 'maho-loader-overlay';
    overlay.innerHTML = '<div class="maho-loader-spinner"></div>';
    overlay.style.opacity = '0';

    if (getComputedStyle(loaderArea).position === 'static') {
        loaderArea.style.position = 'relative';
    }

    loaderArea.appendChild(mahoLoaderElement = overlay);
    mahoLoaderTimeout = setTimeout(() => overlay.style.opacity = '', 150);
}

function hideLoader() {
    clearTimeout(mahoLoaderTimeout);
    mahoLoaderElement?.remove();
    mahoLoaderTimeout = mahoLoaderElement = null;
}

function popWin(url,win,para) {
    var win = window.open(url,win,para);
    win.focus();
}

function setLocation(url){
    window.location.href = encodeURI(url);
}

function setPLocation(url, setFocus){
    if (setFocus) {
        window.opener.focus();
    }
    window.opener.location.href = encodeURI(url);
}

function parseSidUrl(baseUrl, urlExt) {
    var sidPos = baseUrl.indexOf('/?SID=');
    var sid = '';
    urlExt = (urlExt != undefined) ? urlExt : '';

    if(sidPos > -1) {
        sid = '?' + baseUrl.substring(sidPos + 2);
        baseUrl = baseUrl.substring(0, sidPos + 1);
    }

    return baseUrl+urlExt+sid;
}

/**
 * Generate a random string format [a-z0-9]
 *
 * @see {@link https://stackoverflow.com/a/47496558}
 */
function generateRandomString(length) {
    if (length > 0) {
        return [...Array(length)].map(() => Math.random().toString(36)[2]).join('');
    }
    return '';
}

/**
 * Set Varien type route params, i.e. /id/1/
 *
 * @param {string} url - the base URL
 * @param {Object} params - key value pairs to add, update, or remove
 */
function setRouteParams(url, params = {}) {
    url = new URL(url);

    const noTrailingSlash = !url.pathname.endsWith('/');
    if (noTrailingSlash) {
        url.pathname += '/';
    }
    for (const [ key, val ] of Object.entries(params)) {
        const regex = new RegExp(String.raw`\/${key}\/(.*?)\/`);
        if (val === null || val === false) {
            url.pathname = url.pathname.replace(regex, '/');
        } else if (url.pathname.match(regex)) {
            url.pathname = url.pathname.replace(regex, `/${key}/${val}/`);
        } else {
            url.pathname += `${key}/${val}/`;
        }
    }
    if (noTrailingSlash) {
        url.pathname = url.pathname.slice(0, -1);
    }
    return url.toString();
}

/**
 * Set query params, i.e. ?id=1
 *
 * @param {string} url - the base URL
 * @param {Object} params - key value pairs to add, update, or remove
 */
function setQueryParams(url, params = {}) {
    url = new URL(url);
    for (const [ key, val ] of Object.entries(params)) {
        if (val === null || val === false) {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, val);
        }
    }
    return url.toString();
}

/**
 * Alternative to PrototypeJS's string.escapeHTML() method
 */
function escapeHtml(str, escapeQuotes = false) {
    const div = document.createElement('div');
    div.textContent = str;
    return escapeQuotes
        ? div.innerHTML.replaceAll('"', '&quot;').replaceAll("'", '&#039;')
        : div.innerHTML;
}

/**
 * Alternative to PrototypeJS's string.unescapeHTML() method
 */
function unescapeHtml(str) {
    if (!str) return '';
    const doc = new DOMParser().parseFromString(str, 'text/html');
    return doc.documentElement.textContent;
}

/**
 * Alternative to PrototypeJS's string.stripTags() method
 */
function stripTags(str, removeScriptAndStyleContent = false) {
    const doc = new DOMParser().parseFromString(str, 'text/html');
    if (removeScriptAndStyleContent) {
        doc.querySelectorAll('script').forEach(script => script.remove());
        doc.querySelectorAll('style').forEach(style => style.remove());
    }
    return doc.body.textContent;
}

/**
 * Alternative to PrototypeJS's evalScripts option for Ajax.Updater
 *
 * Note that unlike Prototype, scripts will executed in the global scope
 *
 * @param {HTMLElement} targetEl - The element to update
 * @param {string} html - The element's new HTML
 * @param {boolean} executeExternalScripts - Whether to execute `<script src=""></script>` tags
 * @see {@link https://stackoverflow.com/a/47614491}
 * @see {@link http://api.prototypejs.org/ajax/Ajax/Updater/index.html}
*/
function updateElementHtmlAndExecuteScripts(targetEl, html, executeExternalScripts = false) {
    const range = document.createRange();
    const fragment = range.createContextualFragment(html);
    if (!executeExternalScripts) {
        fragment.querySelectorAll('script[src]').forEach(script => script.remove());
    }
    targetEl.replaceChildren(fragment);
}

/**
 * Formats currency using patern
 * format - JSON (pattern, decimal, decimalsDelimeter, groupsDelimeter)
 * showPlus - true (always show '+'or '-'),
 *      false (never show '-' even if number is negative)
 *      null (show '-' if number is negative)
 */

function formatCurrency(price, format, showPlus){
    var precision = isNaN(format.precision = Math.abs(format.precision)) ? 2 : format.precision;
    var requiredPrecision = isNaN(format.requiredPrecision = Math.abs(format.requiredPrecision)) ? 2 : format.requiredPrecision;

    //precision = (precision > requiredPrecision) ? precision : requiredPrecision;
    //for now we don't need this difference so precision is requiredPrecision
    precision = requiredPrecision;

    var integerRequired = isNaN(format.integerRequired = Math.abs(format.integerRequired)) ? 1 : format.integerRequired;

    var decimalSymbol = format.decimalSymbol == undefined ? "," : format.decimalSymbol;
    var groupSymbol = format.groupSymbol == undefined ? "." : format.groupSymbol;
    var groupLength = format.groupLength == undefined ? 3 : format.groupLength;

    var s = '';

    if (showPlus == undefined || showPlus == true) {
        s = price < 0 ? "-" : ( showPlus ? "+" : "");
    } else if (showPlus == false) {
        s = '';
    }

    var i = parseInt(price = Math.abs(+price || 0).toFixed(precision)) + "";
    var pad = (i.length < integerRequired) ? (integerRequired - i.length) : 0;
    while (pad) { i = '0' + i; pad--; }
    j = (j = i.length) > groupLength ? j % groupLength : 0;
    re = new RegExp("(\\d{" + groupLength + "})(?=\\d)", "g");

    /**
     * replace(/-/, 0) is only for fixing Safari bug which appears
     * when Math.abs(0).toFixed() executed on "0" number.
     * Result is "0.-0" :(
     */
    var r = (j ? i.substr(0, j) + groupSymbol : "") + i.substr(j).replace(re, "$1" + groupSymbol) + (precision ? decimalSymbol + Math.abs(price - i).toFixed(precision).replace(/-/, 0).slice(2) : "");
    var pattern = '';
    if (format.pattern.indexOf('{sign}') == -1) {
        pattern = s + format.pattern;
    } else {
        pattern = format.pattern.replace('{sign}', s);
    }

    return pattern.replace('%s', r).replace(/^\s\s*/, '').replace(/\s\s*$/, '');
};

function expandDetails(el, childClass) {
    if (el.classList.contains('show-details')) {
        document.querySelectorAll(childClass).forEach(item => {
            item.style.display = 'none';
        });
        el.classList.remove('show-details');
    } else {
        document.querySelectorAll(childClass).forEach(item => {
            item.style.display = 'block';
        });
        el.classList.add('show-details');
    }
}

window.Varien = window.Varien || {};

Varien.showLoading = function() {
    const loader = document.getElementById('loading-process');
    if (loader) {
        loader.style.display = 'block';
    }
};
Varien.hideLoading = function() {
    const loader = document.getElementById('loading-process');
    if (loader) {
        loader.style.display = 'none';
    }
};

Varien.searchForm = class {
    constructor(form, field, emptyText) {
        this.form = document.getElementById(form);
        this.field = document.getElementById(field);
        this.emptyText = emptyText;

        this.form.addEventListener('submit', this.submit.bind(this));
        this.field.addEventListener('focus', this.focus.bind(this));
        this.field.addEventListener('blur', this.blur.bind(this));
        this.blur();
    }

    submit(event) {
        if (this.field.value === this.emptyText || this.field.value === '') {
            event.preventDefault();
            return false;
        }
        return true;
    }

    focus(event) {
        if (this.field.value === this.emptyText) {
            this.field.value = '';
        }
    }

    blur(event) {
        if (this.field.value === '') {
            this.field.value = this.emptyText;
        }
    }

    initAutocomplete(url, destinationElement) {
        new MahoAutocomplete(this.field, document.getElementById(destinationElement), url, {
            onSelect: (element) => {
                window.location.href = element.querySelector('a').getAttribute('href');
            }
        });
    }
};

Varien.Tabs = class {
    constructor(selector) {
        document.querySelectorAll(`${selector} a`).forEach(el => this.initTab(el));
    }

    initTab(el) {
        el.href = 'javascript:void(0)';
        if (el.parentNode.classList.contains('active')) {
            this.showContent(el);
        }
        el.addEventListener('click', () => this.showContent(el));
    }

    showContent(a) {
        const li = a.parentNode;
        const ul = li.parentNode;

        // Get all li elements in both ul and ol within the parent
        const allTabs = [...ul.querySelectorAll('li')];

        allTabs.forEach(el => {
            const contents = document.getElementById(`${el.id}_contents`);
            if (el === li) {
                el.classList.add('active');
                contents.style.display = 'block';
            } else {
                el.classList.remove('active');
                contents.style.display = 'none';
            }
        });
    }
};

Varien.DateElement = class {
    constructor(type, content, required, format) {
        if (type === 'id') {
            // id prefix
            this.day = document.getElementById(content + 'day');
            this.month = document.getElementById(content + 'month');
            this.year = document.getElementById(content + 'year');
            this.full = document.getElementById(content + 'full');
            this.advice = document.getElementById(content + 'date-advice');
        } else if (type === 'container') {
            // content must be container with data
            this.day = content.day;
            this.month = content.month;
            this.year = content.year;
            this.full = content.full;
            this.advice = content.advice;
        } else {
            return;
        }

        this.required = required;
        this.format = format;

        this.day.classList.add('validate-custom');
        this.day.validate = this.validate.bind(this);
        this.month.classList.add('validate-custom');
        this.month.validate = this.validate.bind(this);
        this.year.classList.add('validate-custom');
        this.year.validate = this.validate.bind(this);

        this.setDateRange(false, false);
        this.year.setAttribute('autocomplete', 'off');

        this.advice.style.display = 'none';

        const date = new Date();
        this.curyear = date.getFullYear();
    }

    validate() {
        let error = false;
        let valueError = false;  // Add this line to define valueError
        let countDaysInMonth;    // Add this to fix scope

        const day = parseInt(this.day.value, 10) || 0;
        const month = parseInt(this.month.value, 10) || 0;
        const year = parseInt(this.year.value, 10) || 0;

        if (this.day.value.trim() === '' &&
            this.month.value.trim() === '' &&
            this.year.value.trim() === '') {
            if (this.required) {
                error = 'This date is a required value.';
            } else {
                this.full.value = '';
            }
        } else if (!day || !month || !year) {
            error = 'Please enter a valid full date';
        } else {
            const date = new Date(year, month - 1, 32);
            countDaysInMonth = 32 - date.getDate();
            let errorType = null;

            if (!countDaysInMonth || countDaysInMonth > 31) {
                countDaysInMonth = 31;
            }

            if (year < 1900) {
                error = this.errorTextModifier(this.validateDataErrorText);
            }

            if (day < 1 || day > countDaysInMonth) {
                errorType = 'day';
                error = 'Please enter a valid day (1-%d).';
            } else if (month < 1 || month > 12) {
                errorType = 'month';
                error = 'Please enter a valid month (1-12).';
            } else {
                // Pad single digits with leading zero
                this.day.value = day.toString().padStart(2, '0');
                this.month.value = month.toString().padStart(2, '0');

                this.full.value = this.format
                    .replace(/%[mb]/i, this.month.value)
                    .replace(/%[de]/i, this.day.value)
                    .replace(/%y/i, this.year.value);

                const testFull = `${this.month.value}/${this.day.value}/${this.year.value}`;
                const test = new Date(testFull);

                if (isNaN(test)) {
                    error = 'Please enter a valid date.';
                } else {
                    this.setFullDate(test);
                }
            }

            if (!error && !this.validateData()) {
                errorType = this.validateDataErrorType;
                valueError = this.validateDataErrorText;
                error = valueError;
            }
        }

        if (error !== false) {
            try {
                error = Translator.translate(error);
            } catch (e) {
                // Translation failed, use original error
            }

            if (!valueError) {
                this.advice.innerHTML = error.replace('%d', countDaysInMonth);
            } else {
                this.advice.innerHTML = this.errorTextModifier(error);
            }
            this.advice.style.display = 'block';
            return false;
        }

        // fixing elements class
        this.day.classList.remove('validation-failed');
        this.month.classList.remove('validation-failed');
        this.year.classList.remove('validation-failed');

        this.advice.style.display = 'none';
        return true;
    }

    validateData() {
        const year = this.fullDate.getFullYear();
        return (year >= 1900 && year <= this.curyear);
    }

    validateDataErrorType = 'year';
    validateDataErrorText = 'Please enter a valid year (1900-%d).';

    errorTextModifier(text) {
        text = Translator.translate(text);
        return text.replace('%d', this.curyear);
    }

    setDateRange(minDate, maxDate) {
        this.minDate = minDate;
        this.maxDate = maxDate;
    }

    setFullDate(date) {
        this.fullDate = date;
    }
};

Varien.DOB = class {
    constructor(selector, required, format) {
        const el = document.querySelector(selector);

        const container = {
            day: el.querySelector('.dob-day input'),
            month: el.querySelector('.dob-month input'),
            year: el.querySelector('.dob-year input'),
            full: el.querySelector('.dob-full input'),
            advice: el.querySelector('.validation-advice')
        };

        new Varien.DateElement('container', container, required, format);
    }
};

Varien.dateRangeDate = class extends Varien.DateElement {
    validateDataErrorText = 'Date should be between %s and %s';

    validateData() {
        let validate = true;

        if (this.minDate || this.maxDate) {
            if (this.minDate) {
                this.minDate = new Date(this.minDate);
                this.minDate.setHours(0);
                if (isNaN(this.minDate.getTime())) {
                    this.minDate = new Date('1/1/1900');
                }
                validate = validate && (this.fullDate >= this.minDate);
            }

            if (this.maxDate) {
                this.maxDate = new Date(this.maxDate);
                this.maxDate.setHours(0);
                if (isNaN(this.maxDate.getTime())) {
                    this.maxDate = new Date();
                }
                validate = validate && (this.fullDate <= this.maxDate);
            }

            // Set appropriate error message based on constraints
            if (this.maxDate && this.minDate) {
                this.validateDataErrorText = 'Please enter a valid date between %s and %s';
            } else if (this.maxDate) {
                this.validateDataErrorText = 'Please enter a valid date less than or equal to %s';
            } else if (this.minDate) {
                this.validateDataErrorText = 'Please enter a valid date equal to or greater than %s';
            } else {
                this.validateDataErrorText = '';
            }
        }

        return validate;
    }

    errorTextModifier(text) {
        if (this.minDate) {
            text = text.replace('%s', this.dateFormat(this.minDate));
        }
        if (this.maxDate) {
            text = text.replace('%s', this.dateFormat(this.maxDate));
        }
        return text;
    }

    dateFormat(date) {
        return `${date.getMonth() + 1}/${date.getDate()}/${date.getFullYear()}`;
    }
};

Varien.FileElement = class {
    constructor(id) {
        this.fileElement = document.getElementById(id);
        this.hiddenElement = document.getElementById(id + '_value');
        this.fileElement.addEventListener('change', this.selectFile.bind(this));
    }

    selectFile(event) {
        this.hiddenElement.value = this.fileElement.value;
    }
};

if (typeof Validation !== 'undefined') {
    Validation.addAllThese([
        ['validate-custom', '', (value, element) => {
            return element.validate();
        }]
    ]);
}

function truncateOptions() {
    document.querySelectorAll('.truncated').forEach(function(element) {
        element.addEventListener('mouseover', function() {
            const fullValueDiv = element.querySelector('div.truncated_full_value');
            if (fullValueDiv) {
                fullValueDiv.classList.add('show');
            }
        });
        element.addEventListener('mouseout', function() {
            const fullValueDiv = element.querySelector('div.truncated_full_value');
            if (fullValueDiv) {
                fullValueDiv.classList.remove('show');
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    truncateOptions();
});

/**
 * Executes event handler on the element. Works with event handlers attached by Prototype,
 * in a browser-agnostic fashion.
 * @param element The element object
 * @param event Event name, like 'change'
 *
 * @example fireEvent($('my-input', 'click'));
 */
function fireEvent(element, event) {
    const evt = new Event(event, {
        bubbles: true,
        cancelable: true
    });
    return element.dispatchEvent(evt);
}

/**
 * Returns more accurate results of floating-point modulo division
 * E.g.:
 * 0.6 % 0.2 = 0.19999999999999996
 * modulo(0.6, 0.2) = 0
 *
 * @param dividend
 * @param divisor
 */
function modulo(dividend, divisor)
{
    var epsilon = divisor / 10000;
    var remainder = dividend % divisor;

    if (Math.abs(remainder - divisor) < epsilon || Math.abs(remainder) < epsilon) {
        remainder = 0;
    }

    return remainder;
}

/**
 * Create form element. Set parameters into it and send
 *
 * @param url
 * @param parametersArray
 * @param method
 */
Varien.formCreator = class {
    constructor(url, parametersArray, method) {
        this.url = url;
        this.parametersArray = JSON.parse(parametersArray);
        this.method = method;
        this.form = '';
        this.createForm();
        this.setFormData();
    }

    createForm() {
        this.form = document.createElement('form');
        this.form.method = this.method;
        this.form.action = this.url;
    }

    setFormData() {
        for (const [key, value] of Object.entries(this.parametersArray)) {
            const input = document.createElement('input');
            input.name = key;
            input.value = value;
            input.type = 'hidden';
            this.form.appendChild(input);
        }
    }
};

function customFormSubmit(url, parametersArray, method) {
    const createdForm = new Varien.formCreator(url, parametersArray, method);
    document.body.appendChild(createdForm.form);
    createdForm.form.submit();
}

async function customFormSubmitToParent(url, parametersArray, method) {
    try {
        const params = JSON.parse(parametersArray);
        const formData = new FormData();

        Object.entries(params).forEach(([key, value]) => {
            formData.append(key, value);
        });

        const response = await mahoFetch(url, {
            method: method.toUpperCase(),
            body: formData
        });

        const node = document.createElement('div');
        node.innerHTML = response;
        const responseMessage = node.getElementsByClassName('messages')[0];
        const pageTitle = window.document.body.getElementsByClassName('page-title')[0];
        if (responseMessage && pageTitle) {
            pageTitle.insertAdjacentHTML('afterend', responseMessage.outerHTML);
        }
        window.opener.focus();
        window.opener.location.href = url;
    } catch (error) {
        console.error('Form submit to parent error:', error);
    }
}

function buttonDisabler() {
    var buttons = document.querySelectorAll('button.save');
    buttons.forEach(function(button) {
        button.disabled = true;
    });
}

class Template
{
    static DEFAULT_PATTERN = /(^|.|\r|\n)(#{(.*?)})/;
    static JAVASCRIPT_PATTERN = /(^|.|\r|\n)(\${(.*?)})/;
    static HANDLEBARS_PATTERN = /(^|.|\r|\n)({{(.*?)}})/;
    static SQUARE_PATTERN = /(^|.|\r|\n)(\[\[(.*?)\]\])/;

    /**
     * Creates a Template object for string interpolation
     * @param {string} template - The template string
     * @param {RegExp} pattern - Optional custom pattern for replaceable symbols
     */
    constructor(template, pattern = Template.DEFAULT_PATTERN) {
        this.template = String(template);
        this.pattern = new RegExp(pattern, 'g');
    }

    /**
     * Evaluates the template with the provided data
     * @param {Record<string, any>} data - Object containing values for interpolation
     * @throws {Error} If data is null or undefined
     * @returns {string} Interpolated string
     */
    evaluate(data = {}) {
        if (typeof data?.toObject === 'function') {
            data = data.toObject();
        }
        return this.template.replaceAll(this.pattern, function() {
            const before = arguments[1] ?? '';    // The preceding character
            const symbol = arguments[2] ?? '';    // The entire symbol, i.e. "#{ foo }"
            const expr = (arguments[3] ?? '').trim(); // The expression, i.e. "foo"

            // Check if symbol was escaped
            if (before === '\\') {
                return symbol;
            }

            // Check if expression is empty
            if (expr === '') {
                return before;
            }

            // Convert bracket to dot notation
            const parts = expr.replaceAll(/\[(.*?)\]/g, '.$1').split('.');

            // Loop through each part and assign with null-safe property access
            let value = data;
            for (const part of parts) {
                value = value?.[part];
            }
            value ??= '';

            return before + value;
        });
    }

    /**
     * Creates a template using template literals
     * @param {string} template - Template string
     * @returns {Function} Template function
     */
    static create(template) {
        const t = new Template(template, Template.JAVASCRIPT_PATTERN);
        return t.evaluate.bind(t);
    }
}

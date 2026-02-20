/*
 * @copyright  Copyright (c) 2007 Andrew Tetlaw
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/license/mit.php
 */

class Validator {
    constructor(className, error, test, options = {}) {
        if (typeof test === 'function') {
            this.options = new Map(Object.entries(options));
            this._test = test;
        } else {
            this.options = new Map(Object.entries(test));
            this._test = () => true;
        }
        this.error = error || 'Validation failed.';
        this.className = className;
    }

    test(v, elm) {
        return this._test(v, elm) && Array.from(this.options.entries()).every(([key, value]) =>
            Validator.methods[key] ? Validator.methods[key](v, elm, value) : true
        );
    }

    static methods = {
        pattern: (v, elm, opt) => Validator.get('IsEmpty').test(v) || opt.test(v),
        minLength: (v, elm, opt) => v.length >= opt,
        maxLength: (v, elm, opt) => v.length <= opt,
        min: (v, elm, opt) => v >= parseFloat(opt),
        max: (v, elm, opt) => v <= parseFloat(opt),
        notOneOf: (v, elm, opt) => opt.every(value => v != value),
        oneOf: (v, elm, opt) => opt.some(value => v == value),
        is: (v, elm, opt) => v == opt,
        isNot: (v, elm, opt) => v != opt,
        equalToField: (v, elm, opt) => v == document.getElementById(opt)?.value,
        notEqualToField: (v, elm, opt) => v != document.getElementById(opt)?.value,
        include: (v, elm, opt) => opt.every(value => Validator.get(value).test(v, elm))
    };

    static get(validatorName) {
        return {
            test: () => true
        };
    }
}

class Validation {
    static defaultOptions = {
        onSubmit: true,
        stopOnFirst: false,
        immediate: false,
        focusOnError: true,
        useTitles: false,
        addClassNameToContainer: false,
        containerClassName: '.input-box',
        onFormValidate: (result, form) => {},
        onElementValidate: (result, elm) => {}
    };

    static methods = {
        '_LikeNoIDIEverSaw_': new Validator('_LikeNoIDIEverSaw_', '', {})
    };

    constructor(form, options = {}) {
        if (typeof form === 'string') {
            form = document.getElementById(form);
        }
        if (!form) {
            return false;
        }

        this.form = form;
        this.options = { ...Validation.defaultOptions, ...options };
        this.form.addEventListener('submit', this.onSubmit.bind(this));

        if (this.options.immediate) {
            const elements = [...this.form.getElementsByTagName('input'), ...this.form.getElementsByTagName('select')];
            elements.forEach(input => {
                if (input.tagName.toLowerCase() === 'select') {
                    input.addEventListener('blur', this.onChange.bind(this));
                } else if (['radio', 'checkbox'].includes(input.type.toLowerCase())) {
                    input.addEventListener('click', this.onChange.bind(this));
                } else {
                    input.addEventListener('change', this.onChange.bind(this));
                }
            });
        }
    }

    onChange(event) {
        Validation.isOnChange = true;
        Validation.validate(event.target, {
            useTitle: this.options.useTitles,
            onElementValidate: this.options.onElementValidate
        });
        Validation.isOnChange = false;
    }

    onSubmit(event) {
        if (!this.validate()) event.preventDefault();
    }

    validate() {
        let result = false;
        const { useTitles, onElementValidate } = this.options;

        try {
            const elements = [...this.form.elements];
            if (this.options.stopOnFirst) {
                result = elements.every(elm => {
                    if (elm.classList.contains('local-validation') && !this.isElementInForm(elm, this.form)) {
                        return true;
                    }
                    return Validation.validate(elm, { useTitle: useTitles, onElementValidate });
                });
            } else {
                result = elements.map(elm => {
                    if (elm.classList.contains('local-validation') && !this.isElementInForm(elm, this.form)) {
                        return true;
                    }
                    return Validation.validate(elm, { useTitle: useTitles, onElementValidate });
                }).every(Boolean);
            }
        } catch (e) {
            console.error(e);
        }

        if (!result && this.options.focusOnError) {
            try {
                this.form.querySelector('.validation-failed')?.focus();
            } catch (e) {
                console.error(e);
            }
        }

        this.options.onFormValidate(result, this.form);
        return result;
    }

    reset() {
        [...this.form.elements].forEach(Validation.reset);
    }

    isElementInForm(elm, form) {
        return elm.closest('form') === form;
    }

    static validate(elm, options = {}) {
        options = {
            useTitle: false,
            onElementValidate: (result, elm) => {},
            ...options
        };

        const cn = elm.className.split(' ');
        return cn.every(value => {
            const test = Validation.test(value, elm, options.useTitle);
            options.onElementValidate(test, elm);
            return test;
        });
    }

    static insertAdvice(elm, name, errorMsg) {
        const div = document.createElement('div');
        div.className = 'validation-advice';
        div.id = `advice-${name}-${Validation.getElmID(elm)}`;
        div.style.display = 'none';
        div.textContent = errorMsg;

        const container = elm.closest('.field-row');
        if (container) {
            container.insertAdjacentElement('afterend', div);
        } else if (elm.closest('td.value')) {
            elm.closest('td.value').insertAdjacentElement('beforeend', div);  // corrected from appendChild
        } else if (elm.adviceContainer || elm.advaiceContainer) {
            let adviceContainer = elm.adviceContainer || elm.advaiceContainer;
            if (typeof adviceContainer === 'string' || adviceContainer instanceof String) {
                adviceContainer = document.getElementById(adviceContainer);
            }
            adviceContainer.replaceChildren(div);
        } else {
            switch (elm.type.toLowerCase()) {
                case 'checkbox':
                case 'radio':
                    const p = elm.parentNode;
                    if (p) {
                        p.insertAdjacentElement('beforeend', div);
                    } else {
                        elm.insertAdjacentElement('afterend', div);
                    }
                    break;
                default:
                    elm.insertAdjacentElement('afterend', div);
            }
        }
    }

    static showAdvice(elm, advice, adviceName) {
        if (!elm.advices) {
            elm.advices = new Map();
        } else {
            elm.advices.forEach((value, key) => {
                if (!advice || value.id !== advice.id) {
                    Validation.hideAdvice(elm, value);
                }
            });
        }
        elm.advices.set(adviceName, advice);

        if (!advice._adviceAbsolutize) {
            advice.style.display = 'block';
        } else {
            advice.style.position = 'absolute';
            advice.style.display = 'block';
            advice.style.top = advice._adviceTop;
            advice.style.left = advice._adviceLeft;
            advice.style.width = advice._adviceWidth;
            advice.style.zIndex = '1000';
            advice.classList.add('advice-absolute');
        }
    }

    static hideAdvice(elm, advice) {
        if (advice != null) {
            advice.style.display = 'none';
        }
    }

    static updateCallback(elm, status) {
        if (typeof window[elm.callbackFunction] === 'function') {
            window[elm.callbackFunction](elm.id, status);
        }
    }

    static ajaxError(elm, errorMsg) {
        const name = 'validate-ajax';
        let advice = Validation.getAdvice(name, elm);
        if (advice == null) {
            advice = Validation.createAdvice(name, elm, false, errorMsg);
        }
        Validation.showAdvice(elm, advice, 'validate-ajax');
        Validation.updateCallback(elm, 'failed');

        elm.classList.add('validation-failed', 'validate-ajax');
        if (Validation.defaultOptions.addClassNameToContainer && Validation.defaultOptions.containerClassName != '') {
            const container = elm.closest(Validation.defaultOptions.containerClassName);
            if (container && Validation.allowContainerClassName(elm)) {
                container.classList.remove('validation-passed');
                container.classList.add('validation-error');
            }
        }
    }

    static allowContainerClassName(elm) {
        if (elm.type == 'radio' || elm.type == 'checkbox') {
            return elm.classList.contains('change-container-classname');
        }
        return true;
    }

    static test(name, elm, useTitle) {
        const v = Validation.get(name);
        const prop = '__advice' + name.replace(/-([a-z])/g, g => g[1].toUpperCase());
        try {
            if (Validation.isVisible(elm) && !v.test(elm.value, elm)) {
                let advice = Validation.getAdvice(name, elm);
                if (advice == null) {
                    advice = Validation.createAdvice(name, elm, useTitle);
                }
                Validation.showAdvice(elm, advice, name);
                Validation.updateCallback(elm, 'failed');

                elm[prop] = 1;
                if (!elm.adviceContainer || !elm.advaiceContainer) {
                    elm.classList.remove('validation-passed');
                    elm.classList.add('validation-failed');
                }

                if (Validation.defaultOptions.addClassNameToContainer && Validation.defaultOptions.containerClassName != '') {
                    const container = elm.closest(Validation.defaultOptions.containerClassName);
                    if (container && Validation.allowContainerClassName(elm)) {
                        container.classList.remove('validation-passed');
                        container.classList.add('validation-error');
                    }
                }
                return false;
            } else {
                const advice = Validation.getAdvice(name, elm);
                Validation.hideAdvice(elm, advice);
                Validation.updateCallback(elm, 'passed');
                elm[prop] = '';
                elm.classList.remove('validation-failed');
                elm.classList.add('validation-passed');
                if (Validation.defaultOptions.addClassNameToContainer && Validation.defaultOptions.containerClassName != '') {
                    const container = elm.closest(Validation.defaultOptions.containerClassName);
                    if (container && !container.querySelector('.validation-failed') && Validation.allowContainerClassName(elm)) {
                        if (!Validation.get('IsEmpty').test(elm.value) || !Validation.isVisible(elm)) {
                            container.classList.add('validation-passed');
                        } else {
                            container.classList.remove('validation-passed');
                        }
                        container.classList.remove('validation-error');
                    }
                }
                return true;
            }
        } catch(e) {
            throw(e);
        }
    }

    static isVisible(elm) {
        return (elm.tagName === 'INPUT' && elm.type === 'hidden')
            ? this.isVisible(elm.parentElement)
            : elm.checkVisibility();
    }

    static getAdvice(name, elm) {
        return document.getElementById('advice-' + name + '-' + Validation.getElmID(elm)) || document.getElementById('advice-' + Validation.getElmID(elm));
    }

    static createAdvice(name, elm, useTitle, customError) {
        const v = Validation.get(name);
        let errorMsg = useTitle ? ((elm && elm.title) ? elm.title : v.error) : v.error;
        if (customError) {
            errorMsg = customError;
        }
        try {
            if (typeof Translator !== 'undefined'){
                errorMsg = Translator.translate(errorMsg);
            }
        }
        catch(e){}

        Validation.insertAdvice(elm, name, errorMsg);
        const adviceEl = Validation.getAdvice(name, elm);
        if(elm.classList.contains('absolute-advice')) {
            const dimensions = elm.getBoundingClientRect();
            const originalPosition = Validation.cumulativeOffset(elm);

            adviceEl._adviceTop = (originalPosition[1] + dimensions.height) + 'px';
            adviceEl._adviceLeft = (originalPosition[0])  + 'px';
            adviceEl._adviceWidth = (dimensions.width)  + 'px';
            adviceEl._adviceAbsolutize = true;
        }
        return adviceEl;
    }

    static getElmID(elm) {
        return elm.id ? elm.id : elm.name;
    }

    static reset(elm) {
        const cn = elm.className.split(' ');
        cn.forEach(value => {
            const prop = '__advice' + value.replace(/-([a-z])/g, g => g[1].toUpperCase());
            if(elm[prop]) {
                const advice = Validation.getAdvice(value, elm);
                if (advice) {
                    advice.style.display = 'none';
                }
                elm[prop] = '';
            }
            elm.classList.remove('validation-failed', 'validation-passed');
            if (Validation.defaultOptions.addClassNameToContainer && Validation.defaultOptions.containerClassName != '') {
                const container = elm.closest(Validation.defaultOptions.containerClassName);
                if (container) {
                    container.classList.remove('validation-passed', 'validation-error');
                }
            }
        });
    }

    static add(className, error, test, options) {
        Validation.methods[className] = new Validator(className, error, test, options);
    }

    static addAllThese(validators) {
        validators.forEach(value => {
            Validation.methods[value[0]] = new Validator(value[0], value[1], value[2], (value.length > 3 ? value[3] : {}));
        });
    }

    static get(name) {
        return Validation.methods[name] ? Validation.methods[name] : Validation.methods['_LikeNoIDIEverSaw_'];
    }

    static cumulativeOffset(element) {
        let top = 0, left = 0;
        do {
            top += element.offsetTop  || 0;
            left += element.offsetLeft || 0;
            element = element.offsetParent;
        } while(element);

        return [left, top];
    }
}

Validation.add('IsEmpty', '', function(v) {
    return (v == '' || v == null || v.length == 0 || /^\s+$/.test(v));
});

Validation.addAllThese([
    ['validate-no-html-tags', 'HTML tags are not allowed', v => !/<(\/)?\w+/.test(v)],
    ['validate-select', 'Please select an option.', v => ((v != "none") && (v != null) && (v.length != 0))],
    ['required-entry', 'This is a required field.', v => !Validation.get('IsEmpty').test(v)],
    ['validate-number', 'Please enter a valid number in this field.', v => {
        return Validation.get('IsEmpty').test(v) || (!isNaN(parseNumber(v)) && /^\s*-?\d*(\.\d*)?\s*$/.test(v));
    }],
    ['validate-number-range', 'The value is not within the specified range.', (v, elm) => {
        if (Validation.get('IsEmpty').test(v)) {
            return true;
        }

        const numValue = parseNumber(v);
        if (isNaN(numValue)) {
            return false;
        }

        const reRange = /^number-range-(-?[\d.,]+)?-(-?[\d.,]+)?$/;
        let result = true;

        elm.className.split(' ').forEach(name => {
            const m = reRange.exec(name);
            if (m) {
                result = result
                    && (m[1] == null || m[1] == '' || numValue >= parseNumber(m[1]))
                    && (m[2] == null || m[2] == '' || numValue <= parseNumber(m[2]));
            }
        });

        return result;
    }],
    ['validate-digits', 'Please use numbers only in this field. Please avoid spaces or other characters such as dots or commas.', v => {
        return Validation.get('IsEmpty').test(v) || !/[^\d]/.test(v);
    }],
    ['validate-digits-range', 'The value is not within the specified range.', (v, elm) => {
        if (Validation.get('IsEmpty').test(v)) {
            return true;
        }

        const numValue = parseNumber(v);
        if (isNaN(numValue)) {
            return false;
        }

        const reRange = /^digits-range-(-?\d+)?-(-?\d+)?$/;
        let result = true;

        elm.className.split(' ').forEach(name => {
            const m = reRange.exec(name);
            if (m) {
                result = result
                    && (m[1] == null || m[1] == '' || numValue >= parseNumber(m[1]))
                    && (m[2] == null || m[2] == '' || numValue <= parseNumber(m[2]));
            }
        });

        return result;
    }],
    ['validate-hex-color', 'Please enter a valid hexadecimal color. For example ff0000.', v => {
        return Validation.get('IsEmpty').test(v) || /^[a-f0-9]{6}$/i.test(v);
    }],
    ['validate-hex-color-hash', 'Please enter a valid hexadecimal color with hash. For example #ff0000.', v => {
        return Validation.get('IsEmpty').test(v) || /^#[a-f0-9]{6}$/i.test(v);
    }],
    ['validate-alpha', 'Please use letters only (a-z or A-Z) in this field.', v => {
        return Validation.get('IsEmpty').test(v) || /^[a-zA-Z]+$/.test(v);
    }],
    ['validate-code', 'Please use only letters (a-z), numbers (0-9) or underscore(_) in this field, first character should be a letter.', v => {
        return Validation.get('IsEmpty').test(v) || /^[a-z]+[a-z0-9_]+$/.test(v);
    }],
    ['validate-code-event', 'Please do not use "event" for an attribute code.', v => {
        return Validation.get('IsEmpty').test(v) || !/^(event)$/.test(v);
    }],
    ['validate-alphanum', 'Please use only letters (a-z or A-Z) or numbers (0-9) only in this field. No spaces or other characters are allowed.', v => {
        return Validation.get('IsEmpty').test(v) || /^[a-zA-Z0-9]+$/.test(v);
    }],
    ['validate-alphanum-with-spaces', 'Please use only letters (a-z or A-Z), numbers (0-9) or spaces only in this field.', v => {
        return Validation.get('IsEmpty').test(v) || /^[a-zA-Z0-9 ]+$/.test(v);
    }],
    ['validate-street', 'Please use only letters (a-z or A-Z) or numbers (0-9) or spaces and # only in this field.', v => {
        return Validation.get('IsEmpty').test(v) || /^[ \w]{3,}([A-Za-z]\.)?([ \w]*\#\d+)?(\r\n| )[ \w]{3,}/.test(v);
    }],
    ['validate-phoneStrict', 'Please enter a valid phone number. For example (123) 456-7890 or 123-456-7890.', v => {
        return Validation.get('IsEmpty').test(v) || /^(\()?\d{3}(\))?(-|\s)?\d{3}(-|\s)\d{4}$/.test(v);
    }],
    ['validate-phoneLax', 'Please enter a valid phone number. For example (123) 456-7890 or 123-456-7890.', v => {
        return Validation.get('IsEmpty').test(v) || /^((\d[-. ]?)?((\(\d{3}\))|\d{3}))?[-. ]?\d{3}[-. ]?\d{4}$/.test(v);
    }],
    ['validate-fax', 'Please enter a valid fax number. For example (123) 456-7890 or 123-456-7890.', v => {
        return Validation.get('IsEmpty').test(v) || /^(\()?\d{3}(\))?(-|\s)?\d{3}(-|\s)\d{4}$/.test(v);
    }],
    ['validate-date', 'Please enter a valid date.', v => {
        const test = new Date(v);
        return Validation.get('IsEmpty').test(v) || !isNaN(test);
    }],
    ['validate-date-range', 'The From Date value should be less than or equal to the To Date value.', (v, elm) => {
        const m = /\bdate-range-(\w+)-(\w+)\b/.exec(elm.className);
        if (!m || m[2] == 'to' || Validation.get('IsEmpty').test(v)) {
            return true;
        }

        const currentYear = new Date().getFullYear() + '';
        const normalizedTime = function(v) {
            v = v.split(/[.\/]/);
            if (v[2] && v[2].length < 4) {
                v[2] = currentYear.substr(0, v[2].length) + v[2];
            }
            return new Date(v.join('/')).getTime();
        };

        const dependentElements = elm.form.querySelectorAll('.validate-date-range.date-range-' + m[1] + '-to');
        return !dependentElements.length || Validation.get('IsEmpty').test(dependentElements[0].value)
            || normalizedTime(v) <= normalizedTime(dependentElements[0].value);
    }],
    ['validate-email', 'Please enter a valid email address. For example johndoe@domain.com.', v => {
        return Validation.get('IsEmpty').test(v) || /^([a-z0-9,!\#\$%&'\*\+\/=\?\^_`\{\|\}~-]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z0-9,!\#\$%&'\*\+\/=\?\^_`\{\|\}~-]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*@([a-z0-9-]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z0-9-]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*\.(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]){2,})$/i.test(v);
    }],
    ['validate-emailSender', 'Please use only visible characters and spaces.', v => {
        return Validation.get('IsEmpty').test(v) || /^[\S ]+$/.test(v);
    }],
    ['validate-password', 'Please enter more characters or clean leading or trailing spaces.', (v, elm) => {
        const pass = v.trim();
        const reMin = new RegExp(/^min-pass-length-[0-9]+$/);
        let minLength = 7;
        elm.className.split(' ').forEach(name => {
            if (name.match(reMin)) {
                minLength = parseInt(name.split('-')[3]);
            }
        });
        return (!(v.length > 0 && v.length < minLength) && v.length == pass.length);
    }],
    ['validate-admin-password', 'Please enter more characters. Password should contain both numeric and alphabetic characters.', (v, elm) => {
        const pass = v.trim();
        if (0 == pass.length) {
            return true;
        }
        if (!(/[a-z]/i.test(v)) || !(/[0-9]/.test(v))) {
            return false;
        }
        const reMin = new RegExp(/^min-admin-pass-length-[0-9]+$/);
        let minLength = 7;
        elm.className.split(' ').forEach(name => {
            if (name.match(reMin)) {
                minLength = parseInt(name.split('-')[4]);
            }
        });
        return !(pass.length < minLength);
    }],
    ['validate-cpassword', 'Please make sure your passwords match.', v => {
        const conf = document.getElementById('confirmation') || document.querySelector('.validate-cpassword');
        let pass = false;
        if (document.getElementById('password')) {
            pass = document.getElementById('password');
        }
        const passwordElements = document.querySelectorAll('.validate-password');
        for (let i = 0; i < passwordElements.length; i++) {
            const passwordElement = passwordElements[i];
            if (passwordElement.closest('form').id == conf.closest('form').id) {
                pass = passwordElement;
            }
        }
        if (document.querySelector('.validate-admin-password')) {
            pass = document.querySelector('.validate-admin-password');
        }
        return (pass.value == conf.value);
    }],
    ['validate-both-passwords', 'Please make sure your passwords match.', (v, input) => {
        const dependentInput = input.form[input.name == 'password' ? 'confirmation' : 'password'];
        const isEqualValues = input.value == dependentInput.value;

        if (isEqualValues && dependentInput.classList.contains('validation-failed')) {
            Validation.test(input.className, dependentInput);
        }

        return dependentInput.value == '' || isEqualValues;
    }],
    ['validate-url', 'Please enter a valid URL. Protocol is required (http://, https:// or ftp://)', v => {
        v = (v || '').trim();
        if (Validation.get('IsEmpty').test(v)) return true;
        try {
            const url = new URL(v);
            return ['http:', 'https:', 'ftp:'].includes(url.protocol);
        } catch {
            return false;
        }
    }],
    ['validate-identifier', 'Please enter a valid URL Key. For example "example-page", "example-page.html" or "anotherlevel/example-page".', v => {
        return Validation.get('IsEmpty').test(v) || /^[a-z0-9][a-z0-9_\/-]+(\.[a-z0-9_-]+)?$/.test(v);
    }],
    ['validate-xml-identifier', 'Please enter a valid XML-identifier. For example something_1, block5, id-4.', v => {
        return Validation.get('IsEmpty').test(v) || /^[A-Z][A-Z0-9_\/-]*$/i.test(v);
    }],
    ['validate-ssn', 'Please enter a valid social security number. For example 123-45-6789.', v => {
        return Validation.get('IsEmpty').test(v) || /^\d{3}-?\d{2}-?\d{4}$/.test(v);
    }],
    ['validate-zip', 'Please enter a valid zip code. For example 90602 or 90602-1234.', v => {
        return Validation.get('IsEmpty').test(v) || /(^\d{5}$)|(^\d{5}-\d{4}$)/.test(v);
    }],
    ['validate-zip-international', 'Please enter a valid zip code.', v => {
        return true;
    }],
    ['validate-date-au', 'Please use this date format: dd/mm/yyyy. For example 17/03/2006 for the 17th of March, 2006.', v => {
        if (Validation.get('IsEmpty').test(v)) return true;
        const regex = /^(\d{2})\/(\d{2})\/(\d{4})$/;
        if (!regex.test(v)) return false;
        const [, day, month, year] = v.match(regex);
        const d = new Date(year, month - 1, day);
        return (parseInt(month, 10) == (1 + d.getMonth())) &&
            (parseInt(day, 10) == d.getDate()) &&
            (parseInt(year, 10) == d.getFullYear());
    }],
    ['validate-currency-dollar', 'Please enter a valid $ amount. For example $100.00.', v => {
        return Validation.get('IsEmpty').test(v) || /^\$?\-?([1-9]{1}[0-9]{0,2}(\,[0-9]{3})*(\.[0-9]{0,2})?|[1-9]{1}\d*(\.[0-9]{0,2})?|0(\.[0-9]{0,2})?|(\.[0-9]{1,2})?)$/.test(v);
    }],
    ['validate-one-required', 'Please select one of the above options.', (v, elm) => {
        const options = elm.parentNode.querySelectorAll('INPUT');
        return Array.from(options).some(elm => elm.value);
    }],
    ['validate-one-required-by-name', 'Please select one of the options.', (v, elm) => {
        const inputs = document.querySelectorAll(`input[name="${elm.name.replace(/([\\"])/g, '\\$1')}"]`);
        let error = 1;
        for (let i = 0; i < inputs.length; i++) {
            if ((inputs[i].type == 'checkbox' || inputs[i].type == 'radio') && inputs[i].checked == true) {
                error = 0;
            }
            if (Validation.isOnChange && (inputs[i].type == 'checkbox' || inputs[i].type == 'radio')) {
                Validation.reset(inputs[i]);
            }
        }
        return error === 0;
    }],
    ['validate-not-negative-number', 'Please enter a number 0 or greater in this field.', v => {
        if (Validation.get('IsEmpty').test(v)) {
            return true;
        }
        v = parseNumber(v);
        return !isNaN(v) && v >= 0;
    }],
    ['validate-zero-or-greater', 'Please enter a number 0 or greater in this field.', v => {
        return Validation.get('validate-not-negative-number').test(v);
    }],
    ['validate-greater-than-zero', 'Please enter a number greater than 0 in this field.', v => {
        if (Validation.get('IsEmpty').test(v)) {
            return true;
        }
        v = parseNumber(v);
        return !isNaN(v) && v > 0;
    }],
    ['validate-special-price', 'The Special Price is active only when lower than the Actual Price.', v => {
        const priceInput = document.getElementById('price');
        const priceType = document.getElementById('price_type');
        const priceValue = parseFloat(v);

        if (!priceInput || !priceInput.value || Validation.get('IsEmpty').test(v) || !Validation.get('validate-number').test(v)) {
            return true;
        }
        if (priceType) {
            return (priceType && priceValue <= 99.99);
        }
        return priceValue < parseFloat(priceInput.value);
    }],
    ['validate-state', 'Please select State/Province.', v => {
        return (v != 0 || v == '');
    }],
    ['validate-new-password', 'Please enter more characters or clean leading or trailing spaces.', (v, elm) => {
        if (!Validation.get('validate-password').test(v, elm)) return false;
        if (Validation.get('IsEmpty').test(v) && v != '') return false;
        return true;
    }],
    ['validate-cc-number', 'Please enter a valid credit card number.', (v, elm) => {
        const ccTypeContainer = document.getElementById(elm.id.substr(0, elm.id.indexOf('_cc_number')) + '_cc_type');
        if (ccTypeContainer && typeof Validation.creditCartTypes[ccTypeContainer.value] != 'undefined'
            && Validation.creditCartTypes[ccTypeContainer.value][2] == false) {
            if (!Validation.get('IsEmpty').test(v) && Validation.get('validate-digits').test(v)) {
                return true;
            } else {
                return false;
            }
        }
        return validateCreditCard(v);
    }],
    ['validate-cc-type', 'Credit card number does not match credit card type.', (v, elm) => {
        elm.value = removeDelimiters(elm.value);
        v = removeDelimiters(v);
        const ccTypeContainer = document.getElementById(elm.id.substr(0, elm.id.indexOf('_cc_number')) + '_cc_type');
        if (!ccTypeContainer) {
            return true;
        }
        const ccType = ccTypeContainer.value;
        if (typeof Validation.creditCartTypes[ccType] == 'undefined') {
            return false;
        }
        if (Validation.creditCartTypes[ccType][0] == false) {
            return true;
        }
        let validationFailure = false;
        Object.entries(Validation.creditCartTypes).forEach(([key, value]) => {
            if (key == ccType) {
                if (value[0] && !v.match(value[0])) {
                    validationFailure = true;
                }
                return;
            }
        });
        if (validationFailure) {
            return false;
        }
        if (ccTypeContainer.classList.contains('validation-failed') && Validation.isOnChange) {
            Validation.validate(ccTypeContainer);
        }
        return true;
    }],
    ['validate-cc-type-select', 'Card type does not match credit card number.', (v, elm) => {
        const ccNumberContainer = document.getElementById(elm.id.substr(0, elm.id.indexOf('_cc_type')) + '_cc_number');
        if (Validation.isOnChange && Validation.get('IsEmpty').test(ccNumberContainer.value)) {
            return true;
        }
        if (Validation.get('validate-cc-type').test(ccNumberContainer.value, ccNumberContainer)) {
            Validation.validate(ccNumberContainer);
        }
        return Validation.get('validate-cc-type').test(ccNumberContainer.value, ccNumberContainer);
    }],
    ['validate-cc-exp', 'Incorrect credit card expiration date.', (v, elm) => {
        const ccExpMonth = v;
        const ccExpYear = document.getElementById(elm.id.substr(0, elm.id.indexOf('_expiration')) + '_expiration_yr').value;
        const currentTime = new Date();
        const currentMonth = currentTime.getMonth() + 1;
        const currentYear = currentTime.getFullYear();
        if (ccExpMonth < currentMonth && ccExpYear == currentYear) {
            return false;
        }
        return true;
    }],
    ['validate-cc-cvn', 'Please enter a valid credit card verification number.', (v, elm) => {
        const ccTypeContainer = document.getElementById(elm.id.substr(0, elm.id.indexOf('_cc_cid')) + '_cc_type');
        if (!ccTypeContainer) {
            return true;
        }
        const ccType = ccTypeContainer.value;
        if (typeof Validation.creditCartTypes[ccType] == 'undefined') {
            return false;
        }
        const re = Validation.creditCartTypes[ccType][1];
        if (v.match(re)) {
            return true;
        }
        return false;
    }],
    ['validate-ajax', '', v => true],
    ['validate-data', 'Please use only letters (a-z or A-Z), numbers (0-9) or underscore(_) in this field, first character should be a letter.', v => {
        if (v != '' && v) {
            return /^[A-Za-z]+[A-Za-z0-9_]+$/.test(v);
        }
        return true;
    }],
    ['validate-css-length', 'Please input a valid CSS-length. For example 100px or 77pt or 20em or .5ex or 50%.', v => {
        if (v != '' && v) {
            return /^[0-9\.]+(px|pt|em|ex|%)?$/.test(v) && (!(/\..*\./.test(v))) && !(/\.$/.test(v));
        }
        return true;
    }],
    ['validate-length', 'Text length does not satisfy specified text range.', (v, elm) => {
        const reMax = new RegExp(/^maximum-length-[0-9]+$/);
        const reMin = new RegExp(/^minimum-length-[0-9]+$/);
        let result = true;
        elm.className.split(' ').forEach(name => {
            if (name.match(reMax) && result) {
                const length = parseInt(name.split('-')[2]);
                result = (v.length <= length);
            }
            if (name.match(reMin) && result && !Validation.get('IsEmpty').test(v)) {
                const length = parseInt(name.split('-')[2]);
                result = (v.length >= length);
            }
        });
        return result;
    }],
    ['validate-percents', 'Please enter a number lower than 100.', { max: 100 }],
    ['required-file', 'Please select a file', (v, elm) => {
        let result = !Validation.get('IsEmpty').test(v);
        if (result === false) {
            const ovId = elm.id + '_value';
            const ovElm = document.getElementById(ovId);
            if (ovElm) {
                result = !Validation.get('IsEmpty').test(ovElm.value);
            }
        }
        return result;
    }],
    ['validate-cc-ukss', 'Please enter issue number or start date for switch/solo card type.', (v, elm) => {
        let endposition;

        if (elm.id.match(/(.)+_cc_issue$/)) {
            endposition = elm.id.indexOf('_cc_issue');
        } else if (elm.id.match(/(.)+_start_month$/)) {
            endposition = elm.id.indexOf('_start_month');
        } else {
            endposition = elm.id.indexOf('_start_year');
        }

        const prefix = elm.id.substr(0, endposition);

        const ccTypeContainer = document.getElementById(prefix + '_cc_type');

        if (!ccTypeContainer) {
            return true;
        }
        const ccType = ccTypeContainer.value;

        if (['SS', 'SM', 'SO'].indexOf(ccType) == -1) {
            return true;
        }

        document.getElementById(prefix + '_cc_issue').advaiceContainer
            = document.getElementById(prefix + '_start_month').advaiceContainer
            = document.getElementById(prefix + '_start_year').advaiceContainer
            = document.getElementById(prefix + '_cc_type_ss_div').querySelector('ul li.adv-container');

        const ccIssue = document.getElementById(prefix + '_cc_issue').value;
        const ccSMonth = document.getElementById(prefix + '_start_month').value;
        const ccSYear = document.getElementById(prefix + '_start_year').value;

        const ccStartDatePresent = (ccSMonth && ccSYear) ? true : false;

        if (!ccStartDatePresent && !ccIssue) {
            return false;
        }
        return true;
    }],
    ['validate-comma-separated-numbers', 'Please enter comma-separated numbers only, ex: 10,20,30', function(v) {
        const isEmptyValid = Validation.get('IsEmpty');
        let isValid = !isEmptyValid.test(v);
        const values = v.split(',');
        for (let i = 0; i < values.length; i++) {
            if (!/^[0-9]+$/.test(values[i])) {
                isValid = false;
            }
        }
        return isValid;
    }],
    ['validate-per-page-value', 'Please enter a valid value from list', function(v, elm) {
        const isEmptyValid = Validation.get('IsEmpty');
        if (isEmptyValid.test(v)) {
            return false;
        }
        const valuesElement = document.getElementById(elm.id + '_values');
        if (!valuesElement) {
            return true; // If companion field doesn't exist, skip validation
        }
        const values = valuesElement.value.split(',');
        return values.indexOf(v) !== -1;
    }]
]);

function removeDelimiters (v) {
    v = v.replace(/\s/g, '');
    v = v.replace(/\-/g, '');
    return v;
}

function parseNumber(v)
{
    if (typeof v != 'string') {
        return parseFloat(v);
    }

    var isDot  = v.indexOf('.');
    var isComa = v.indexOf(',');

    if (isDot != -1 && isComa != -1) {
        if (isComa > isDot) {
            v = v.replace('.', '').replace(',', '.');
        }
        else {
            v = v.replace(',', '');
        }
    }
    else if (isComa != -1) {
        v = v.replace(',', '.');
    }

    return parseFloat(v);
}

function validateCreditCard(input) {
    const number = input.toString();
    const digits = number.replace(/\D/g, '').split('').map(Number);
    let sum = 0;
    let isSecond = false;
    for (let i = digits.length - 1; i >= 0; i--) {
        let digit = digits[i];
        if (isSecond) {
            digit *= 2;
            if (digit > 9) {
                digit -= 9;
            }
        }
        sum += digit;
        isSecond = !isSecond;
    }
    return sum % 10 === 0;
}

/**
 * Hash with credit card types which can be simply extended in payment modules
 * 0 - regexp for card number
 * 1 - regexp for cvn
 * 2 - check or not credit card number trough Luhn algorithm by
 *     function validateCreditCard which you can find above in this file
 */
Validation.creditCartTypes = {
    'SO': [new RegExp('^(6334[5-9]([0-9]{11}|[0-9]{13,14}))|(6767([0-9]{12}|[0-9]{14,15}))$'), new RegExp('^([0-9]{3}|[0-9]{4})?$'), true],
    'VI': [new RegExp('^4[0-9]{12}([0-9]{3})?$'), new RegExp('^[0-9]{3}$'), true],
    'MC': [new RegExp('^(5[1-5][0-9]{14}|2(22[1-9][0-9]{12}|2[3-9][0-9]{13}|[3-6][0-9]{14}|7[0-1][0-9]{13}|720[0-9]{12}))$'), new RegExp('^[0-9]{3}$'), true],
    'AE': [new RegExp('^3[47][0-9]{13}$'), new RegExp('^[0-9]{4}$'), true],
    'DI': [new RegExp('^(30[0-5][0-9]{13}|3095[0-9]{12}|35(2[8-9][0-9]{12}|[3-8][0-9]{13})|36[0-9]{12}|3[8-9][0-9]{14}|6011(0[0-9]{11}|[2-4][0-9]{11}|74[0-9]{10}|7[7-9][0-9]{10}|8[6-9][0-9]{10}|9[0-9]{11})|62(2(12[6-9][0-9]{10}|1[3-9][0-9]{11}|[2-8][0-9]{12}|9[0-1][0-9]{11}|92[0-5][0-9]{10})|[4-6][0-9]{13}|8[2-8][0-9]{12})|6(4[4-9][0-9]{13}|5[0-9]{14}))$'), new RegExp('^[0-9]{3}$'), true],
    'JCB': [new RegExp('^(30[0-5][0-9]{13}|3095[0-9]{12}|35(2[8-9][0-9]{12}|[3-8][0-9]{13})|36[0-9]{12}|3[8-9][0-9]{14}|6011(0[0-9]{11}|[2-4][0-9]{11}|74[0-9]{10}|7[7-9][0-9]{10}|8[6-9][0-9]{10}|9[0-9]{11})|62(2(12[6-9][0-9]{10}|1[3-9][0-9]{11}|[2-8][0-9]{12}|9[0-1][0-9]{11}|92[0-5][0-9]{10})|[4-6][0-9]{13}|8[2-8][0-9]{12})|6(4[4-9][0-9]{13}|5[0-9]{14}))$'), new RegExp('^[0-9]{3,4}$'), true],
    'DICL': [new RegExp('^(30[0-5][0-9]{13}|3095[0-9]{12}|35(2[8-9][0-9]{12}|[3-8][0-9]{13})|36[0-9]{12}|3[8-9][0-9]{14}|6011(0[0-9]{11}|[2-4][0-9]{11}|74[0-9]{10}|7[7-9][0-9]{10}|8[6-9][0-9]{10}|9[0-9]{11})|62(2(12[6-9][0-9]{10}|1[3-9][0-9]{11}|[2-8][0-9]{12}|9[0-1][0-9]{11}|92[0-5][0-9]{10})|[4-6][0-9]{13}|8[2-8][0-9]{12})|6(4[4-9][0-9]{13}|5[0-9]{14}))$'), new RegExp('^[0-9]{3}$'), true],
    'SM': [new RegExp('(^(5[0678])[0-9]{11,18}$)|(^(6[^05])[0-9]{11,18}$)|(^(601)[^1][0-9]{9,16}$)|(^(6011)[0-9]{9,11}$)|(^(6011)[0-9]{13,16}$)|(^(65)[0-9]{11,13}$)|(^(65)[0-9]{15,18}$)|(^(49030)[2-9]([0-9]{10}$|[0-9]{12,13}$))|(^(49033)[5-9]([0-9]{10}$|[0-9]{12,13}$))|(^(49110)[1-2]([0-9]{10}$|[0-9]{12,13}$))|(^(49117)[4-9]([0-9]{10}$|[0-9]{12,13}$))|(^(49118)[0-2]([0-9]{10}$|[0-9]{12,13}$))|(^(4936)([0-9]{12}$|[0-9]{14,15}$))'), new RegExp('^([0-9]{3}|[0-9]{4})?$'), true],
    'OT': [false, new RegExp('^([0-9]{3}|[0-9]{4})?$'), false]
};

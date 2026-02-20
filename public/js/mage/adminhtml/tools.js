/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
function setLocation(url){
    window.location.href = encodeURI(url);
}

function confirmSetLocation(message, url){
    if( confirm(message) ) {
        setLocation(url);
    }
    return false;
}

function deleteConfirm(message, url) {
    confirmSetLocation(message, url);
}

function setElementDisable(element, disable) {
    if (typeof element === 'string' || element instanceof String) {
        element = document.getElementById(element);
    }
    if (!(element instanceof Element)) {
        throw new TypeError('Argument must be type of String or Element');
    }
    element.disabled = disable;
    element.classList.toggle('disabled', disable);
}

function toggleParentVis(element, force = null) {
    if (typeof element === 'string' || element instanceof String) {
        element = document.getElementById(element);
    }
    if (!(element instanceof Element)) {
        throw new TypeError('Argument must be type of String or Element');
    }
    toggleVis(element.parentNode, force);
}

// to fix new app/design/adminhtml/default/default/template/widget/form/renderer/fieldset.phtml
// with toggleParentVis
function toggleFieldsetVis(element, force = null) {
    if (typeof element === 'string' || element instanceof String) {
        element = document.getElementById(element);
    }
    if (!(element instanceof Element)) {
        throw new TypeError('Argument must be type of String or Element');
    }
    toggleVis(element, force);

    const previousElement = element.previousElementSibling;
    if (previousElement && previousElement.classList.contains('entry-edit-head')) {
        toggleVis(previousElement, force);
    }
}

function toggleVis(element, force = null) {
    if (typeof element === 'string' || element instanceof String) {
        element = document.getElementById(element);
    }
    if (!(element instanceof Element)) {
        throw new TypeError('Argument must be type of String or Element');
    }
    if (element.style.display === 'none') {
        element.style.display = '';
        element.classList.add('no-display')
    }
    if (force === null) {
        element.classList.toggle('no-display');
    } else {
        element.classList.toggle('no-display', !force);
    }
}

function checkVisibility(element) {
    if (typeof element === 'string' || element instanceof String) {
        element = document.getElementById(element);
    }
    if (!(element instanceof Element)) {
        throw new TypeError('Argument must be type of String or Element');
    }
    if (element.checkVisibility) {
        return element.checkVisibility();
    } else {
        return element.style.display !== 'none' && !element.classList.contains('no-display');
    }
}

function imagePreview(element){
    const el = typeof element === 'string' ? document.getElementById(element) : element;
    if (!el) return;

    Dialog.info(`<img src="${el.src}" style="max-width: 100%; margin: 0 auto;">`, {
        title: Translator.translate('Image Preview'),
        className: 'image-preview-dialog'
    });
}

function checkByProductPriceType(elem) {
    if (elem.id == 'price_type') {
        this.productPriceType = elem.value;
        return false;
    } else {
        if (elem.id == 'price' && this.productPriceType == 0) {
            return false;
        }
        return true;
    }
}

window.addEventListener('load', function() {
    const priceDefault = document.getElementById('price_default');
    const price = document.getElementById('price');
    if (priceDefault && priceDefault.checked && price) {
        price.disabled = true;
    }
});

function toggleValueElements(checkbox, container, excludedElements, checked){
    if(container && checkbox){
        let ignoredElements = [checkbox];
        if (typeof excludedElements != 'undefined') {
            if (!Array.isArray(excludedElements)) {
                excludedElements = [excludedElements];
            }
            ignoredElements = ignoredElements.concat(excludedElements);
        }
        const elems = container.querySelectorAll('select, input, textarea, button, img');
        const isDisabled = (checked !== undefined ? checked : checkbox.checked);
        elems.forEach(function (elem) {
            if (checkByProductPriceType(elem)) {
                if (ignoredElements.includes(elem)) {
                    return;
                }

                elem.disabled = isDisabled;
                elem.classList.toggle('disabled', isDisabled);
                if (elem.nodeName.toLowerCase() === 'img') {
                    elem.style.display = isDisabled ? 'none' : '';
                }
            }
        });
    }
}

/**
 * @todo add validation for fields
 */
function submitAndReloadArea(area, url) {
    const areaElement = typeof area === 'string' ? document.getElementById(area) : area;
    if(areaElement) {
        const fields = areaElement.querySelectorAll('input, select, textarea');
        const formData = new FormData();

        fields.forEach(field => {
            if (field.type === 'checkbox' || field.type === 'radio') {
                if (field.checked) {
                    formData.append(field.name, field.value);
                }
            } else if (field.type !== 'submit' && field.type !== 'button') {
                formData.append(field.name, field.value);
            }
        });

        url = url + (url.includes('?') ? '&isAjax=true' : '?isAjax=true');

        mahoFetch(url, {
            method: 'POST',
            body: formData
        })
        .then(responseText => {
            try {
                const response = JSON.parse(responseText);
                if (response.error) {
                    alert(response.message);
                }
                if(response.ajaxExpired && response.ajaxRedirect) {
                    setLocation(response.ajaxRedirect);
                }
            } catch (e) {
                areaElement.innerHTML = responseText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
}

function syncOnchangeValue(baseElem, distElem){
    const baseElement = typeof baseElem === 'string' ? document.getElementById(baseElem) : baseElem;
    const distElement = typeof distElem === 'string' ? document.getElementById(distElem) : distElem;

    if (baseElement && distElement) {
        baseElement.addEventListener('change', function(){
            distElement.value = baseElement.value;
        });
    }
}

// Insert some content to the cursor position of input element
function updateElementAtCursor(el, value) {
    if (el.selectionStart !== null) {
        el.setRangeText(value, el.selectionStart, el.selectionEnd);
    } else {
        el.value += value;
    }
}

// Firebug detection
function firebugEnabled() {
    if(window.console && window.console.firebug) {
        return true;
    }
    return false;
}

function disableElement(elem) {
    elem.disabled = true;
    elem.classList.add('disabled');
}

function enableElement(elem) {
    elem.disabled = false;
    elem.classList.remove('disabled');
}

function disableElements(search){
    document.querySelectorAll('.' + search).forEach(disableElement);
}

function enableElements(search){
    document.querySelectorAll('.' + search).forEach(enableElement);
}

/** Cookie Reading And Writing **/

const Cookie = {
    all() {
        const pairs = document.cookie.split(';');
        const cookies = {};
        pairs.forEach(item => {
            const pair = item.trim().split('=');
            if (pair.length === 2) {
                cookies[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1]);
            }
        });

        return cookies;
    },
    read(cookieName) {
        const cookies = this.all();
        return cookies[cookieName] || null;
    },
    write(cookieName, cookieValue, cookieLifeTime) {
        let expires = '';
        if (cookieLifeTime) {
            const date = new Date();
            date.setTime(date.getTime() + (cookieLifeTime * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        const urlPath = '/' + BASE_URL.split('/').slice(3).join('/'); // Get relative path
        document.cookie = encodeURIComponent(cookieName) + "=" + encodeURIComponent(cookieValue) + expires + "; path=" + urlPath;
    },
    clear(cookieName) {
        this.write(cookieName, '', -1);
    }
};

const Fieldset = {
    cookiePrefix: 'fh-',
    applyCollapse(containerId) {
        const stateElement = document.getElementById(containerId + '-state');
        const headElement = document.getElementById(containerId + '-head');
        const containerElement = document.getElementById(containerId);

        let collapsed;
        if (stateElement) {
            collapsed = stateElement.value == 1 ? 0 : 1;
        } else {
            collapsed = headElement ? headElement.collapsed : undefined;
        }

        if (collapsed == 1 || collapsed === undefined) {
            if (headElement) {
                headElement.classList.remove('open');
                const sectionConfig = headElement.closest('.section-config');
                if (sectionConfig) {
                    sectionConfig.classList.remove('active');
                }
            }
            if (containerElement) {
                containerElement.style.display = 'none';
            }
        } else {
            if (headElement) {
                headElement.classList.add('open');
                const sectionConfig = headElement.closest('.section-config');
                if (sectionConfig) {
                    sectionConfig.classList.add('active');
                }
            }
            if (containerElement) {
                containerElement.style.display = '';
            }
        }
    },
    toggleCollapse(containerId, saveThroughAjax) {
        const stateElement = document.getElementById(containerId + '-state');
        const headElement = document.getElementById(containerId + '-head');

        let collapsed;
        if (stateElement) {
            collapsed = stateElement.value == 1 ? 0 : 1;
        } else {
            collapsed = headElement ? headElement.collapsed : undefined;
        }

        if(collapsed == 1 || collapsed === undefined) {
            if (stateElement) {
                stateElement.value = 1;
            }
            if (headElement) {
                headElement.collapsed = 0;
            }
        } else {
            if (stateElement) {
                stateElement.value = 0;
            }
            if (headElement) {
                headElement.collapsed = 1;
            }
        }

        this.applyCollapse(containerId);
        if (typeof saveThroughAjax !== "undefined" && stateElement) {
            this.saveState(saveThroughAjax, {container: containerId, value: stateElement.value});
        }
    },
    addToPrefix(value) {
        this.cookiePrefix += value + '-';
    },
    saveState(url, parameters) {
        const params = new URLSearchParams(parameters);
        mahoFetch(url + '?' + params.toString(), {
            method: 'GET'
        });
    }
};

const Base64 = {
    // private property
    _keyStr : "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",
     //'+/=', '-_,'
    // public method for encoding
    encode: function (input) {
        var output = "";
        var chr1, chr2, chr3, enc1, enc2, enc3, enc4;
        var i = 0;

        input = Base64._utf8_encode(input);

        while (i < input.length) {

            chr1 = input.charCodeAt(i++);
            chr2 = input.charCodeAt(i++);
            chr3 = input.charCodeAt(i++);

            enc1 = chr1 >> 2;
            enc2 = ((chr1 & 3) << 4) | (chr2 >> 4);
            enc3 = ((chr2 & 15) << 2) | (chr3 >> 6);
            enc4 = chr3 & 63;

            if (isNaN(chr2)) {
                enc3 = enc4 = 64;
            } else if (isNaN(chr3)) {
                enc4 = 64;
            }
            output = output +
            this._keyStr.charAt(enc1) + this._keyStr.charAt(enc2) +
            this._keyStr.charAt(enc3) + this._keyStr.charAt(enc4);
        }

        return output;
    },

    // public method for decoding
    decode: function (input) {
        var output = "";
        var chr1, chr2, chr3;
        var enc1, enc2, enc3, enc4;
        var i = 0;

        input = input.replace(/[^A-Za-z0-9\+\/\=]/g, "");

        while (i < input.length) {

            enc1 = this._keyStr.indexOf(input.charAt(i++));
            enc2 = this._keyStr.indexOf(input.charAt(i++));
            enc3 = this._keyStr.indexOf(input.charAt(i++));
            enc4 = this._keyStr.indexOf(input.charAt(i++));

            chr1 = (enc1 << 2) | (enc2 >> 4);
            chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
            chr3 = ((enc3 & 3) << 6) | enc4;

            output = output + String.fromCharCode(chr1);

            if (enc3 != 64) {
                output = output + String.fromCharCode(chr2);
            }
            if (enc4 != 64) {
                output = output + String.fromCharCode(chr3);
            }
        }
        output = Base64._utf8_decode(output);
        return output;
    },

    mageEncode: function(input){
        return this.encode(input).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ',');
    },

    mageDecode: function(output){
        output = output.replace(/\-/g, '+').replace(/_/g, '/').replace(/,/g, '=');
        return this.decode(output);
    },

    idEncode: function(input){
        return this.encode(input).replace(/\+/g, ':').replace(/\//g, '_').replace(/=/g, '-');
    },

    idDecode: function(output){
        output = output.replace(/\-/g, '=').replace(/_/g, '/').replace(/\:/g, '\+');
        return this.decode(output);
    },

    // private method for UTF-8 encoding
    _utf8_encode : function (string) {
        string = string.replace(/\r\n/g,"\n");
        var utftext = "";

        for (var n = 0; n < string.length; n++) {

            var c = string.charCodeAt(n);

            if (c < 128) {
                utftext += String.fromCharCode(c);
            }
            else if((c > 127) && (c < 2048)) {
                utftext += String.fromCharCode((c >> 6) | 192);
                utftext += String.fromCharCode((c & 63) | 128);
            }
            else {
                utftext += String.fromCharCode((c >> 12) | 224);
                utftext += String.fromCharCode(((c >> 6) & 63) | 128);
                utftext += String.fromCharCode((c & 63) | 128);
            }
        }
        return utftext;
    },

    // private method for UTF-8 decoding
    _utf8_decode : function (utftext) {
        var string = "";
        var i = 0;
        var c = c1 = c2 = 0;

        while ( i < utftext.length ) {

            c = utftext.charCodeAt(i);

            if (c < 128) {
                string += String.fromCharCode(c);
                i++;
            }
            else if((c > 191) && (c < 224)) {
                c2 = utftext.charCodeAt(i+1);
                string += String.fromCharCode(((c & 31) << 6) | (c2 & 63));
                i += 2;
            }
            else {
                c2 = utftext.charCodeAt(i+1);
                c3 = utftext.charCodeAt(i+2);
                string += String.fromCharCode(((c & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63));
                i += 3;
            }
        }
        return string;
    }
};

/**
 * Callback function for sort numeric values
 *
 * @param val1
 * @param val2
 */
function sortNumeric(val1, val2)
{
    return val1 - val2;
}

/**
 * Adds copy icons to elements that have the class 'copy-text'
 */
function addCopyIcons() {
    if (navigator.clipboard === undefined) {
        return;
    }

    const copyTexts = document.querySelectorAll('[data-copy-text]');
    copyTexts.forEach(copyText => {
        const iconElement = createCopyIconElement();
        copyText.insertAdjacentElement('afterend', iconElement);
    });
}

/**
 * @return {HTMLElement} The created copy icon element
 */
function createCopyIconElement() {
    const copyIcon = document.createElement('span');
    copyIcon.classList.add('icon-copy');
    copyIcon.setAttribute('onclick', 'copyText(event)');
    copyIcon.setAttribute('title', Translator.translate('Copy text to clipboard'));

    return copyIcon;
}

/**
 * Copies the text from the data-text attribute of the clicked element to the clipboard
 *
 * @param {Event} event - The event object triggered by the click event
 */
function copyText(event) {
    event.stopPropagation();
    event.preventDefault();
    const copyIcon = event.currentTarget;
    const copyText = copyIcon.previousElementSibling.getAttribute('data-copy-text');
    navigator.clipboard.writeText(copyText);
    copyIcon.classList.add('icon-copy-copied');
    setTimeout(() => {
        copyIcon.classList.remove('icon-copy-copied');
    }, 1000);
}

/**
 * Clear <div id="messages"></div>
 */
function clearMessagesDiv(div = null) {
    setMessagesDivHtml('', div);
}

/**
 * Set a message in <div id="messages"></div>
 *
 * @param {string} message - text value of the message to display
 * @param {string} type - one of `success|error|notice`
 */
function setMessagesDiv(message, type = 'success', div = null) {
    message = escapeHtml(message);
    type = escapeHtml(type, true);
    setMessagesDivHtml(`<ul class="messages"><li class="${type}-msg"><ul><li><span>${message}</span></li></ul></li></ul>`, div);
}

/**
 * Raw function to update <div id="messages"></div>
 *
 * @param {string} html
*/
function setMessagesDivHtml(html, div = null) {
    if (div ??= document.getElementById('messages')) {
        div.innerHTML = xssFilter(html);
    }
}

/**
 * Alternative to PrototypeJS's Function.wrap() method
 */
function wrapFunction(originalFn, wrapperFn) {
    if (typeof originalFn !== 'function' || typeof wrapperFn !== 'function') {
        throw new TypeError('Arguments must be functions');
    }
    return function() {
        return wrapperFn(originalFn, ...arguments);
    };
}

/**
 * Creates a debounced function that delays invoking `func` until after `wait`
 * milliseconds have elapsed since the last time the function was invoked.
 */
function debounce(func, delay) {
    let timer;
    return function(...args) {
        clearTimeout(timer);
        timer = setTimeout(() => {
            func.apply(this, args);
        }, delay);
    };
}

// https://cdn.jsdelivr.net/npm/xsskillah@0.1.3/src/xsskillah.min.js
(function() {
    const sandboxes=new Set,globalDefaults={allowTags:[],allowTagRules:[],xssDocReset:8},xssDoc=document.implementation.createHTMLDocument(),SAFE_URL_PATTERN=/^(?!javascript:)(?:[a-z0-9+.-]+:|[^&:\/?#]*(?:[\/?#]|$))/i,sanitizeUrl=e=>(e=String(e)).match(SAFE_URL_PATTERN)?e:"unsafe:"+e,vulnerableAttributes=["href","src","srcset","style","background","action","formaction","xmlns"],vulnerableTags=["script","iframe","object","embed","meta","base","style","canvas","link","marquee","applet","frame","frameset"],xssKillah=(e={})=>(t,s)=>{{const t=Object.hasOwn(e,"xssDocReset")?e.xssDocReset:globalDefaults.xssDocReset;if(sandboxes.size===t){for(const e of sandboxes){e.deref().remove()}sandboxes.clear()}}const a=document.createTreeWalker(xssDoc.body,NodeFilter.SHOW_ELEMENT),o={},l=[];let n;const r=document.createElement("div");xssDoc.body.appendChild(r),r.innerHTML=t;const c=new WeakRef(r);sandboxes.add(c);const i=s?.allowTags||e?.allowTags||globalDefaults.allowTags,u=s?.allowTagRules||e?.allowTagRules||globalDefaults.allowTagRules;for(const e of u)o[e]=!0;for(vulnerableTags.filter((e=>!i.includes(e))).forEach((e=>{r.querySelectorAll(e).forEach((e=>e.remove()))}));n=a.nextNode();){const e=l.indexOf(n);if(e>-1){l.splice(e,1);continue}const t=n.nodeType;if(1===t)for(const e of n.attributes){const s=e.value,a=e.name;if("input"===t&&"type"===a&&"text/javascript"===s&&o.inputTypeJS&&n.remove(),"form"===t&&"action"===a&&o.formAction){const e=n.querySelectorAll("*");l.push(...e),n.remove()}a.startsWith("on")&&!o.onEvents?n.removeAttribute(a):e.value=sanitizeUrl(s);const r=a.startsWith('"')&&!o.strayDoubleQuotes,c=a.startsWith("'")&&!o.straySingleQuote,i=a.startsWith("`")&&!o.strayBackTicks;(r||c||i)&&n.removeAttribute(a),(vulnerableAttributes.includes(a)||a.startsWith("data"))&&(e.value=sanitizeUrl(s))}}return r.childNodes};xssKillah.makeAlive=(e,t=document)=>{const s=t.querySelectorAll(e),a=s[0];if(s.length>1||"SCRIPT"!==a.tagName)return void console.warn("makeAlive requires a unique selector");const o=document.createElement("script");o.textContent=a.textContent,a.replaceWith(o)};
    window.xssKillah = xssKillah;
})();

function xssFilter(str) {
    const sanitize = xssKillah();
    const safeNodes = sanitize(str);
    return Array.from(safeNodes).map(n => n.outerHTML || n.textContent).join('');
}

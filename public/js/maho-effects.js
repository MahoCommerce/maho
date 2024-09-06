if (typeof Effect === 'undefined') {
    const Effect = {
        Appear: function(element, options = {}) {
            const el = typeof element === 'string' ? document.getElementById(element) : element;
            if (!el) throw { name: 'ElementDoesNotExistError', message: 'Element does not exist' };
            el.style.opacity = '1';
            el.style.display = '';
            if (options.afterFinishInternal) options.afterFinishInternal({ element: el, options });
            if (options.afterFinish) options.afterFinish({ element: el, options });
        },
        Fade: function(element, options = {}) {
            const el = typeof element === 'string' ? document.getElementById(element) : element;
            if (!el) throw { name: 'ElementDoesNotExistError', message: 'Element does not exist' };
            el.style.opacity = '0';
            if (options.afterFinishInternal) options.afterFinishInternal({ element: el, options });
            if (options.afterFinish) options.afterFinish({ element: el, options });
        },
        Morph: function(element, options = {}) {
            const el = typeof element === 'string' ? document.getElementById(element) : element;
            if (!el) throw { name: 'ElementDoesNotExistError', message: 'Element does not exist' };
            const style = typeof options.style === 'string'
                ? options.style.indexOf(':') > -1
                    ? Object.fromEntries(options.style.split(';').filter(Boolean).map(s => s.split(':')))
                    : (el.className = options.style, getComputedStyle(el))
                : options.style || {};
            Object.assign(el.style, style);
            if (options.afterFinishInternal) options.afterFinishInternal({ element: el, options });
            if (options.afterFinish) options.afterFinish({ element: el, options });
        }
    };

    window.Effect = Effect;
}
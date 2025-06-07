/**
 * Maho
 *
 * @package     js
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/license/mit  MIT
 */

(function() {
    "use strict";
    
    const events = ["click", "keydown", "mousemove", "mouseover", "touchstart", "scroll", "wheel"];
    let loaded = false;
    let scripts = [];
    let loadIndex = 0;
    
    function init() {
        // Collect all deferred scripts
        document.querySelectorAll('script[type="text/plain"][data-maho-script]').forEach(script => {
            scripts.push({
                element: script,
                src: script.getAttribute("src"),
                content: script.textContent,
                index: +script.getAttribute("data-maho-script")
            });
        });
        
        // Sort by original index to maintain order
        scripts.sort((a, b) => a.index - b.index);
        
        // Setup event listeners
        events.forEach(event => {
            document.addEventListener(event, load, { once: true, passive: true });
        });
        
        // Also trigger on first real user interaction with forms
        document.addEventListener("focusin", load, { once: true, passive: true });
        document.addEventListener("change", load, { once: true, passive: true });
    }
    
    function load() {
        if (loaded) return;
        loaded = true;
        
        // Remove remaining event listeners
        events.forEach(event => document.removeEventListener(event, load));

        loadNext();
    }
    
    function loadNext() {
        if (loadIndex >= scripts.length) {
            // All scripts loaded, now fire events
            fireEvents();
            return;
        }
        
        const script = scripts[loadIndex++];
        const newScript = document.createElement("script");
        
        // Copy all attributes except type and data-maho-script
        for (const attr of script.element.attributes) {
            if (attr.name !== "type" && attr.name !== "data-maho-script") {
                newScript.setAttribute(attr.name, attr.value);
            }
        }
        
        if (script.src) {
            // External script
            newScript.onload = newScript.onerror = () => {
                // Some scripts may define things in onload, give them time
                setTimeout(loadNext, 0);
            };
            newScript.src = script.src;
        } else {
            // Inline script
            newScript.textContent = script.content;
        }
        
        // Replace original script element
        script.element.parentNode.replaceChild(newScript, script.element);
        
        // Continue immediately for inline scripts
        if (!script.src) {
            setTimeout(loadNext, 0);
        }
    }
    
    function fireEvents() {
        setTimeout(() => {
            // Fire DOMContentLoaded
            document.dispatchEvent(new Event("DOMContentLoaded", { bubbles: true, cancelable: false }));
            
            // Fire load event
            const loadEvent = new Event("load", { bubbles: false, cancelable: false });
            window.dispatchEvent(loadEvent);
            
            // Also trigger window.onload if set
            if (typeof window.onload === "function") {
                window.onload(loadEvent);
            }
            
            // For scripts that check document.readyState
            Object.defineProperty(document, "readyState", {
                configurable: true,
                get: function() { return "complete"; }
            });
        }, 0);
    }
    
    // Initialize when ready
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        setTimeout(init, 0);
    }
})();

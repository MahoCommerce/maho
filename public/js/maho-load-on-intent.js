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

        loadAllScripts();
    }
    
    function loadAllScripts() {
        // Create promises for all scripts
        const scriptPromises = scripts.map((script, index) => {
            return new Promise((resolve) => {
                if (script.src) {
                    // External script - create a new script element
                    const newScript = document.createElement("script");
                    
                    // Copy all attributes except type and data-maho-script
                    for (const attr of script.element.attributes) {
                        if (attr.name !== "type" && attr.name !== "data-maho-script") {
                            newScript.setAttribute(attr.name, attr.value);
                        }
                    }
                    
                    // Store the script element for later insertion
                    script.newScript = newScript;
                    
                    // Create a preload to start download immediately
                    const link = document.createElement("link");
                    link.rel = "preload";
                    link.as = "script";
                    link.href = script.src;
                    
                    // When preload completes, we're ready (but don't execute yet)
                    link.onload = link.onerror = () => {
                        resolve();
                    };
                    
                    document.head.appendChild(link);
                } else {
                    // Inline script - prepare the new script element
                    const newScript = document.createElement("script");
                    
                    // Copy all attributes except type and data-maho-script
                    for (const attr of script.element.attributes) {
                        if (attr.name !== "type" && attr.name !== "data-maho-script") {
                            newScript.setAttribute(attr.name, attr.value);
                        }
                    }
                    
                    newScript.textContent = script.content;
                    script.newScript = newScript;
                    
                    // Inline scripts are immediately ready
                    resolve();
                }
            });
        });
        
        // Wait for all scripts to be ready, then execute in order
        Promise.all(scriptPromises).then(() => {
            executeScriptsInOrder();
        });
    }
    
    function executeScriptsInOrder() {
        let index = 0;
        
        function executeNext() {
            if (index >= scripts.length) {
                // All scripts executed
                fireEvents();
                return;
            }
            
            const script = scripts[index++];
            
            if (script.src) {
                // For external scripts, set src and add to DOM
                script.newScript.src = script.src;
                
                // Wait for execution before continuing
                script.newScript.onload = script.newScript.onerror = () => {
                    setTimeout(executeNext, 0);
                };
            }
            
            // Replace the original script element
            script.element.parentNode.replaceChild(script.newScript, script.element);
            
            // For inline scripts, continue immediately
            if (!script.src) {
                setTimeout(executeNext, 0);
            }
        }
        
        executeNext();
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

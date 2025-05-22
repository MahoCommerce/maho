/**
 * Maho
 *
 * @package    rwd_default
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

// =============================================
// Primary Break Points
// =============================================

// These should be used with the bp (max-width, xx) mixin
// where a min-width is used, remember to +1 to break correctly
// If these are changed, they must also be updated in _var.scss

var bp = {
    xsmall: 479,
    small: 599,
    medium: 770,
    large: 979,
    xlarge: 1199
};

// ==============================================
// Pointer abstraction
// ==============================================

/**
 * This class provides an easy and abstracted mechanism to determine the
 * best pointer behavior to use -- that is, is the user currently interacting
 * with their device in a touch manner, or using a mouse.
 *
 * Since devices may use either touch or mouse or both, there is no way to
 * know the user's preferred pointer type until they interact with the site.
 *
 * To accommodate this, this class provides a method and two events
 * to determine the user's preferred pointer type.
 *
 * - getPointer() returns the last used pointer type, or, if the user has
 *   not yet interacted with the site, falls back to a test.
 *
 * - The mouse-detected event is triggered on the window object when the user
 *   is using a mouse pointer input, or has switched from touch to mouse input.
 *   It can be observed in this manner: $j(window).on('mouse-detected', function(event) { // custom code });
 *
 * - The touch-detected event is triggered on the window object when the user
 *   is using touch pointer input, or has switched from mouse to touch input.
 *   It can be observed in this manner: $j(window).on('touch-detected', function(event) { // custom code });
 */
const PointerManager = {
    MOUSE_POINTER_TYPE: 'mouse',
    TOUCH_POINTER_TYPE: 'touch',
    POINTER_EVENT_TIMEOUT_MS: 500,
    standardTouch: false,
    touchDetectionEvent: null,
    lastTouchType: null,
    pointerTimeout: null,
    pointerEventLock: false,

    getPointerEventsSupported: function() {
        return this.standardTouch;
    },

    /**
     * If called before init(), get best guess of input pointer type
     * If called after init(), get current pointer in use.
     */
    getPointer: function() {
        // On iOS devices, always default to touch, as this.lastTouchType will intermittently return 'mouse' if
        // multiple touches are triggered in rapid succession in Safari on iOS
        if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
            return this.TOUCH_POINTER_TYPE;
        }

        if (this.lastTouchType) {
            return this.lastTouchType;
        }

        return ('ontouchstart' in window || navigator.maxTouchPoints > 0) ? this.TOUCH_POINTER_TYPE : this.MOUSE_POINTER_TYPE;
    },

    setPointerEventLock: function() {
        this.pointerEventLock = true;
    },

    clearPointerEventLock: function() {
        this.pointerEventLock = false;
    },

    setPointerEventLockTimeout: function() {
        var that = this;

        if (this.pointerTimeout) {
            clearTimeout(this.pointerTimeout);
        }

        this.setPointerEventLock();
        this.pointerTimeout = setTimeout(function() { that.clearPointerEventLock(); }, this.POINTER_EVENT_TIMEOUT_MS);
    },

    triggerMouseEvent: function(originalEvent) {
        if (this.lastTouchType == this.MOUSE_POINTER_TYPE) {
            return; //prevent duplicate events
        }

        this.lastTouchType = this.MOUSE_POINTER_TYPE;
        window.dispatchEvent(new CustomEvent('mouse-detected', { detail: originalEvent }));
    },

    triggerTouchEvent: function(originalEvent) {
        if (this.lastTouchType == this.TOUCH_POINTER_TYPE) {
            return; //prevent duplicate events
        }

        this.lastTouchType = this.TOUCH_POINTER_TYPE;
        window.dispatchEvent(new CustomEvent('touch-detected', { detail: originalEvent }));
    },

    initEnv: function() {
        this.standardTouch = 'PointerEvent' in window;
        this.touchDetectionEvent = 'pointerdown';
    },

    wirePointerDetection: function() {
        var that = this;

        window.addEventListener(this.touchDetectionEvent, function(e) {
            if (that.pointerEventLock) {
                return;
            }

            that.setPointerEventLockTimeout();

            if (e.pointerType === 'mouse') {
                that.triggerMouseEvent(e);
            } else if (e.pointerType === 'touch' || e.pointerType === 'pen') {
                that.triggerTouchEvent(e);
            }
        });
    },

    init: function() {
        this.initEnv();
        this.wirePointerDetection();
    }
};

/**
 * This class manages the main navigation and supports infinite nested
 * menus which support touch, mouse click, and hover correctly.
 *
 * The following is the expected behavior:
 *
 * - Hover with an actual mouse should expand the menu (at any level of nesting)
 * - Click with an actual mouse will follow the link, regardless of any children
 * - Touch will follow links without children, and toggle submenus of links with children
 *
 * Caveats:
 * - According to Mozilla's documentation (https://developer.mozilla.org/en-US/docs/Web/Guide/Events/Touch_events),
 *   Firefox has disabled Apple-style touch events on desktop, so desktop devices using Firefox will not support
 *   the desired touch behavior.
 */
const MenuManager = {
    // These variables are used to detect incorrect touch / mouse event order
    mouseEnterEventObserved: false,
    touchEventOrderIncorrect: false,
    cancelNextTouch: false,

    /**
     * This object manages touch scroll detection
     */
    TouchScroll: {
        /**
         * Touch which moves the screen vertically more than
         * this many pixels will be considered a scroll.
         */
        TOUCH_SCROLL_THRESHOLD: 20,

        touchStartPosition: null,

        /**
         * Note scroll position so that scroll action can be detected later.
         * Should probably be called on touchstart (or similar) event.
         */
        reset: function() {
            this.touchStartPosition = window.scrollY;
        },

        /**
         * Determines if touch was actually a scroll. Should probably be checked
         * on touchend (or similar) event.
         * @returns {boolean}
         */
        shouldCancelTouch: function() {
            if (this.touchStartPosition == null) {
                return false;
            }

            const scroll = window.scrollY - this.touchStartPosition;
            return Math.abs(scroll) > this.TOUCH_SCROLL_THRESHOLD;
        }
    },

    /**
     * Determines if small screen behavior should be used.
     *
     * @returns {boolean}
     */
    useSmallScreenBehavior: function() {
        return window.matchMedia("screen and (max-width:" + bp.medium + "px)").matches;
    },

    /**
     * Toggles a given menu item's visibility.
     * On large screens, also closes sibling and children of sibling menus.
     *
     * @param target
     */
    toggleMenuVisibility: function(target) {
        const li = target.closest('li');

        if (!this.useSmallScreenBehavior()) {
            // remove menu-active from siblings and children of siblings
            li.parentElement.querySelectorAll('li.menu-active').forEach(el => {
                if (el !== li) {
                    el.classList.remove('menu-active');
                }
            });
            // remove menu-active from children
            li.querySelectorAll('li.menu-active').forEach(el => el.classList.remove('menu-active'));
        }

        // toggle current item's active state
        li.classList.toggle('menu-active');
    },

    // --------------------------------------------
    // Initialization methods
    //

    /**
     * Initialize MenuManager and wire all required events.
     * Should only be called once.
     *
     */
    init: function() {
        this.wirePointerEvents();
    },

    /**
     * This method observes events to implement expected header navigation functionality.
     * It differentiates between mouse and touch inputs using the PointerEvent API.
     */
    wirePointerEvents: function() {
        const nav = document.getElementById('nav');
        if (!nav) return;
        const hoverTargets = nav.querySelectorAll('li');
        const pointerTargets = nav.querySelectorAll('a.has-children');

        hoverTargets.forEach(target => {
            target.addEventListener('pointerenter', (e) => {
                if (e.pointerType === 'mouse') {
                    this.mouseEnterAction(e, target);
                }
            });
            target.addEventListener('pointerleave', (e) => {
                if (e.pointerType === 'mouse') {
                    this.mouseLeaveAction(e, target);
                }
            });
        });

        pointerTargets.forEach(target => {
            target.addEventListener('click', (e) => {
                if (e.pointerType === 'mouse') {
                    this.mouseClickAction(e, target);
                } else {
                    this.touchAction(e, target);
                }
            });
        });

        window.addEventListener('touchstart', () => {
            this.TouchScroll.reset();
        });
    },

    /**
     * On large screens, show menu.
     * On small screens, do nothing.
     *
     * @param event
     * @param target
     */
    mouseEnterAction: function(event, target) {
        if (this.useSmallScreenBehavior()) {
            return; // don't do mouse enter functionality on smaller screens
        }

        target.classList.add('menu-active'); // show current menu
    },

    /**
     * On large screens, hide menu.
     * On small screens, do nothing.
     *
     * @param event
     * @param target
     */
    mouseLeaveAction: function(event, target) {
        if (this.useSmallScreenBehavior()) {
            return; // don't do mouse leave functionality on smaller screens
        }

        target.classList.remove('menu-active'); // hide all menus
    },

    /**
     * On large screens, don't interfere so that browser will follow link.
     * On small screens, toggle menu visibility.
     *
     * @param event
     * @param target
     */
    mouseClickAction: function(event, target) {
        if (this.useSmallScreenBehavior()) {
            event.preventDefault(); // don't follow link
            this.toggleMenuVisibility(target); // instead, toggle visibility
        }
    },

    /**
     * Toggle menu visibility, and prevent event default to avoid
     * undesired, duplicate, synthetic mouse events.
     *
     * @param event
     * @param target
     */
    touchAction: function(event, target) {
        if (this.TouchScroll.shouldCancelTouch()) {
            return; // Touch was a scroll -- don't do anything else
        }
        this.toggleMenuVisibility(target);
        event.preventDefault();
    }
};

// ==============================================
// Init
// ==============================================

document.addEventListener('DOMContentLoaded', () => {
    PointerManager.init();
    // ==============================================
    // Shared Vars
    // ==============================================

    // Document
    const w = window;
    const d = document;
    const body = document.body;

    /* Wishlist Toggle Class */
    document.querySelectorAll('.change').forEach(element => {
        element.addEventListener('click', function(e) {
            this.classList.toggle('active');
            e.stopPropagation();
        });
    });

    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('change')) {
            document.querySelectorAll('.change.active').forEach(element => {
                element.classList.remove('active');
            });
        }
    });

    // Skip Links
    const skipLinks = document.querySelector('.skip-links');
    if (skipLinks) {
        skipLinks.addEventListener('click', (e) => {
            const skipLink = e.target.closest('.skip-link');
            if (!skipLink) return;

            const target = skipLink.getAttribute('data-target-element');
            if (!target) return;
            const elem = document.getElementById(target);
            if (!elem) return;

            e.preventDefault();

            const isSkipContentOpen = elem.classList.contains('skip-active');
            document.querySelectorAll('.skip-active').forEach(el => el.classList.remove('skip-active'));

            if (!isSkipContentOpen) {
                skipLink.classList.add('skip-active');
                elem.classList.add('skip-active');
            }

            if (target === '#header-search') {
                const searchInput = document.getElementById('search');
                if (searchInput) {
                    searchInput.focus();
                }
            }
        });


        document.querySelector('#header-cart .skip-link-close').addEventListener('click', (e) => {
            const parent = e.target.closest('.skip-content');
            const link = parent.parentElement.querySelector('.skip-link');
            parent.classList.remove('skip-active');
            link.classList.remove('skip-active');
            e.preventDefault();
        });
    }


    // ==============================================
    // Header Menus
    // ==============================================

    MenuManager.init();

    // Prevent sub menus from spilling out of the window.
    function preventMenuSpill() {
        const windowWidth = window.innerWidth;
        document.querySelectorAll('ul.level0').forEach(ul => {
            // Show it long enough to get info, then hide it.
            ul.classList.add('position-test');
            ul.classList.remove('spill');

            const width = ul.offsetWidth;
            const offset = ul.getBoundingClientRect().left;

            ul.classList.remove('position-test');

            // Add the spill class if it will spill off the page.
            if ((offset + width) > windowWidth) {
                ul.classList.add('spill');
            }
        });
    }
    preventMenuSpill();
    window.addEventListener('delayed-resize', preventMenuSpill);

    // ==============================================
    // Menu State
    // ==============================================

    const resetMenuState = (mq) => {
        document.querySelectorAll('.menu-active').forEach(el => el.classList.remove('menu-active'));
        document.querySelectorAll('.sub-menu-active').forEach(el => el.classList.remove('sub-menu-active'));
        document.querySelectorAll('.skip-active').forEach(el => el.classList.remove('skip-active'));

        let minicart = document.getElementById('header-cart');
        let mobileContainer = document.getElementById('minicart-container-mobile');
        if (mq.matches) {
            mobileContainer.appendChild(minicart);
        } else {
            document.querySelector('.skip-cart').after(minicart);
        }
    };

    let maxWidthLargeMediaQuery = window.matchMedia('(max-width: ' + bp.large + 'px)');
    let maxWidthMediumMediaQuery = window.matchMedia('(max-width: ' + bp.medium + 'px)');
    maxWidthMediumMediaQuery.addEventListener('change', resetMenuState);
    resetMenuState(maxWidthMediumMediaQuery);

    // ==============================================
    // UI Pattern - Media Switcher
    // ==============================================

    // Used to swap primary product photo from thumbnails.
    const mediaListLinks = document.querySelectorAll('.media-list a');
    const mediaPrimaryImage = document.querySelector('.primary-image img');
    if (mediaListLinks.length) {
        mediaListLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                mediaPrimaryImage.src = link.href;
            });
        });
    }

    // ==============================================
    // UI Pattern - ToggleSingle
    // ==============================================

    // Use this plugin to toggle the visibility of content based on a toggle link/element.
    // This pattern differs from the accordion functionality in the Toggle pattern in that each toggle group acts
    // independently of the others. It is named so as not to be confused with the Toggle pattern below
    //
    // This plugin requires a specific markup structure. The plugin expects a set of elements that it
    // will use as the toggle link. It then hides all immediately following siblings and toggles the sibling's
    // visibility when the toggle link is clicked.
    //
    // Example markup:
    // <div class="block">
    //     <div class="block-title">Trigger</div>
    //     <div class="block-content">Content that should show when </div>
    // </div>
    //
    // Options:
    //     destruct: defaults to false, but if true, the plugin will remove itself, display content, and remove event handlers

    function toggleSingle(elements, options = {}) {
        const settings = {
            destruct: false,
            ...options
        };

        elements.forEach(element => {
            // Remove event listener if it exists
            if (element.toggleSingleHandler) {
                element.removeEventListener('click', element.toggleSingleHandler);
                element.toggleSingleHandler = null;
            }

            if (!settings.destruct) {
                element.toggleSingleHandler = function() {
                    this.classList.toggle('active');
                    const nextElement = this.nextElementSibling;
                    if (nextElement) {
                        nextElement.classList.toggle('no-display');
                    }
                };
                element.addEventListener('click', element.toggleSingleHandler);

                // Hide the content if it's not already hidden
                const nextElement = element.nextElementSibling;
                if (nextElement && !nextElement.classList.contains('no-display')) {
                    nextElement.classList.add('no-display');
                }
            } else {
                // Remove all classes that were added by this function
                element.classList.remove('active');
                const nextElement = element.nextElementSibling;
                if (nextElement) {
                    nextElement.classList.remove('no-display');
                }
            }
        });
    }

    // ==============================================
    // UI Pattern - Toggle Content (tabs and accordions in one setup)
    // ==============================================

    document.querySelectorAll('.toggle-content').forEach(wrapper => {
        const hasTabs = wrapper.classList.contains('tabs');
        const hasAccordion = wrapper.classList.contains('accordion');
        const startOpen = wrapper.classList.contains('open');
        const dl = wrapper.querySelector('dl');
        if (!dl) return;
        const dts = Array.from(dl.querySelectorAll(':scope > dt'));
        const panes = Array.from(dl.querySelectorAll('dd'));
        const groups = [dts, panes];

        // Create a ul for tabs if necessary
        let lis = [];
        if (hasTabs) {
            const ul = document.createElement('ul');
            ul.className = 'toggle-tabs';
            ul.role = 'tablist';
            dts.forEach(dt => {
                const li = document.createElement('li');
                li.id = dt.id;
                li.role = 'tab';
                li.innerHTML = dt.innerHTML;
                ul.appendChild(li);
            });
            dl.parentNode.insertBefore(ul, dl);
            lis = Array.from(ul.children);
            groups.push(lis);
        }

        // Add "last" classes
        groups.forEach(group => {
            group[group.length - 1].classList.add('last');
        });

        function toggleClasses(clickedItem, group) {
            const index = group.indexOf(clickedItem);
            groups.forEach(g => {
                g.forEach(item => item.classList.remove('current'));
                g[index].classList.add('current');
            });
        }

        // Toggle on dt click
        dts.forEach(dt => {
            dt.addEventListener('click', () => {
                if (dt.classList.contains('current') && wrapper.classList.contains('accordion-open')) {
                    wrapper.classList.remove('accordion-open');
                } else {
                    wrapper.classList.add('accordion-open');
                }
                toggleClasses(dt, dts);
            });
        });

        // Toggle on li click (for tabs)
        if (hasTabs) {
            lis.forEach(li => {
                li.addEventListener('click', () => {
                    toggleClasses(li, lis);
                });
            });
            // Open the first tab
            lis[0].click();
        }

        // Open the first accordion if desired
        if (startOpen) {
            dts[0].click();
        }
    });


    // ==============================================
    // Layered Navigation Block
    // ==============================================

    // On product list pages, we want to show the layered nav/category menu immediately above the product list.
    // While it would make more sense to just move the .block-layered-nav block rather than .col-left-first
    // (since other blocks can be inserted into left_first), it creates simpler code to move the entire
    // .col-left-first block, so that is the approach we're taking
    if (document.querySelector('.col-left-first > .block') && document.querySelector('div.category-products')) {
        const repositionLayered = (mq) => {
            const colLeftFirst = document.querySelector('.col-left-first');
            const categoryProducts = document.querySelector('div.category-products');
            const colMain = document.querySelector('.col-main');

            if (mq.matches) {
                categoryProducts.parentNode.insertBefore(colLeftFirst, categoryProducts);
            } else {
                colMain.parentNode.insertBefore(colLeftFirst, colMain);
            }
        };

        const maxWidthMediumMediaQuery = window.matchMedia('(max-width: 770px)');
        maxWidthMediumMediaQuery.addEventListener('change', repositionLayered);
        repositionLayered(maxWidthMediumMediaQuery);
    }

    // ==============================================
    // 3 column layout
    // ==============================================

    // On viewports smaller than 1000px, move the right column into the left column
    if (document.querySelector('.main-container.col3-layout')) {
        const reposition3rdColumn = (mq) => {
            const rightColumn = document.querySelector('.col-right');
            if (mq.matches) {
                const colWrapper = document.querySelector('.col-wrapper');
                colWrapper.appendChild(rightColumn);
            } else {
                const main = document.querySelector('.main');
                main.appendChild(rightColumn);
            }
        };

        const maxWidth1000MediaQuery = window.matchMedia('(max-width: 1000px)');
        maxWidth1000MediaQuery.addEventListener('change', reposition3rdColumn);
        reposition3rdColumn(maxWidth1000MediaQuery);
    }

    // ==============================================
    // Block collapsing (on smaller viewports)
    // ==============================================

    const toggleElementsForMediumSize = (mq) => {
        const elements = document.querySelectorAll(
            '.col-left-first .block:not(.block-layered-nav) .block-title, ' +
            '.col-left-first .block-layered-nav .block-subtitle--filter, ' +
            '.sidebar:not(.col-left-first) .block .block-title'
        );

        if (mq.matches) {
            toggleSingle(elements);
        } else {
            toggleSingle(elements, { destruct: true });
        }
    };
    maxWidthMediumMediaQuery.addEventListener('change', toggleElementsForMediumSize);
    toggleElementsForMediumSize(maxWidthMediumMediaQuery);

    // ==============================================
    // OPC - Progress Block
    // ==============================================

    if (document.body.classList.contains('checkout-onepage-index')) {
        const repositionCheckoutProgress = (mq) => {
            const checkoutProgressWrapper = document.getElementById('checkout-progress-wrapper');
            if (mq.matches) {
                document.getElementById('checkout-step-review').prepend(checkoutProgressWrapper);
            } else {
                document.querySelector('.col-right').prepend(checkoutProgressWrapper);
            }
        };
        maxWidthLargeMediaQuery.addEventListener('change', repositionCheckoutProgress);
        repositionCheckoutProgress(maxWidthLargeMediaQuery);
    }

    // ==============================================
    // Checkout Cart - events
    // ==============================================

    if (document.body.classList.contains('checkout-cart-index')) {
        document.querySelectorAll('input[name^="cart"]').forEach(input => {
            input.addEventListener('focus', function() {
                const siblingButton = this.nextElementSibling;
                if (siblingButton && siblingButton.tagName === 'BUTTON') {
                    siblingButton.style.display = 'inline-block'; // or 'block', depending on your layout
                }
            });
        });
    }

    // ==============================================
    // Gift Registry Styles
    // ==============================================

    if (document.querySelector('.a-left')) {
        const repositionGiftRegistry = (mq) => {
            if (mq.matches) {
                document.querySelectorAll('.gift-info').forEach(giftInfo => {
                    const textarea = giftInfo.nextElementSibling.querySelector('textarea');
                    if (textarea) {
                        giftInfo.appendChild(textarea);
                    }
                });
            } else {
                document.querySelectorAll('.left-note').forEach(leftNote => {
                    const textarea = leftNote.previousElementSibling.querySelector('textarea');
                    if (textarea) {
                        leftNote.appendChild(textarea);
                    }
                });
            }
        };
        maxWidthLargeMediaQuery.addEventListener(repositionGiftRegistry);
        repositionGiftRegistry(maxWidthLargeMediaQuery);
    }

    // ==============================================
    // Generic, efficient window resize handler
    // ==============================================

    // Using setTimeout since Web-Kit and some other browsers call the resize function constantly upon window resizing.
    let resizeTimer;
    window.addEventListener('resize', (e) => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            // Create and dispatch a custom event
            const event = new CustomEvent('delayed-resize', { detail: e });
            window.dispatchEvent(event);
        }, 250);
    });
});

// ==============================================
// PDP - image zoom - needs to be available outside document.ready scope
// ==============================================

var ProductMediaManager = {
    IMAGE_ZOOM_THRESHOLD: 20,
    imageWrapper: null,

    createZoom: function(image) {
        if (PointerManager.getPointer() == PointerManager.TOUCH_POINTER_TYPE
            || window.matchMedia("screen and (max-width:" + bp.medium + "px)").matches) {
            return;
        }

        if (!image) {
            return;
        }

        if (image.naturalWidth && image.naturalHeight) {
            let widthDiff = image.naturalWidth - image.width - ProductMediaManager.IMAGE_ZOOM_THRESHOLD;
            let heightDiff = image.naturalHeight - image.height - ProductMediaManager.IMAGE_ZOOM_THRESHOLD;
            let parentImage = image.closest('.product-image');

            if (widthDiff < 0 && heightDiff < 0) {
                parentImage.classList.remove('zoom-available');
                return;
            } else {
                parentImage.classList.add('zoom-available');
            }
        }

        const container = document.querySelector(".product-image-gallery");
        container.addEventListener("mousemove", onZoom);
        container.addEventListener("mouseover", onZoom);
        container.addEventListener("mouseleave", offZoom);

        function onZoom(e) {
            const rect = container.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const img = document.querySelector(".product-image-gallery img.visible");
            img.style.transformOrigin = `${x}px ${y}px`;
            img.style.transform = "scale(2.5)";
        }

        function offZoom(e) {
            const img = document.querySelector(".product-image-gallery img.visible");
            img.style.transformOrigin = `center center`;
            img.style.transform = "scale(1)";
        }
    },

    swapImage: function(targetImage) {
        targetImage.classList.add('gallery-image');
        const imageGallery = document.querySelector('.product-image-gallery');

        const swapAndZoom = () => {
            imageGallery.querySelectorAll('.gallery-image').forEach(image => {
                image.classList.remove('visible');
            });

            // Append targetImage to the gallery
            imageGallery.appendChild(targetImage);
            targetImage.classList.add('visible');
            ProductMediaManager.createZoom(targetImage);
        };

        // Remove the loading attribute
        targetImage.removeAttribute('loading'); // Remove lazy load
        if (targetImage.complete) {
            // Image already loaded, swap immediately
            swapAndZoom();
        } else {
            // Need to wait for image to load
            imageGallery.classList.add('loading');
            imageGallery.appendChild(targetImage);

            targetImage.onload = function() {
                imageGallery.classList.remove('loading');
                swapAndZoom();
            };
        }
    },

    wireThumbnails: function() {
        document.querySelectorAll('.product-image-thumbs .thumb-link').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                let imageIndex = link.getAttribute('data-image-index');
                let target = document.querySelector('#image-' + imageIndex);
                ProductMediaManager.swapImage(target);
            });
        });
    },

    initZoom: function() {
        ProductMediaManager.createZoom(document.querySelector(".gallery-image.visible"));
    },

    init: function() {
        ProductMediaManager.imageWrapper = document.querySelector('.product-img-box');

        // Re-initialize zoom on viewport size change since resizing causes problems with zoom and since smaller
        // viewport sizes shouldn't have zoom
        window.addEventListener('delayed-resize', function(e) {
            ProductMediaManager.initZoom();
        });

        ProductMediaManager.initZoom();
        ProductMediaManager.wireThumbnails();
        const event = new CustomEvent('product-media-loaded', { detail: ProductMediaManager });
        document.dispatchEvent(event);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    ProductMediaManager.init();
});

// Slideshow management
document.addEventListener('DOMContentLoaded', () => {
    const slideshowContainer = document.querySelector('.slideshow');
    if (!slideshowContainer) {
        return;
    }

    const slideshow = slideshowContainer.querySelector('ul');
    const slides = slideshow.querySelectorAll('li');

    const dotsContainer = document.createElement('div');
    dotsContainer.className = 'slideshow-dots';
    slides.forEach((_, index) => {
        const dot = document.createElement('span');
        dot.className = 'dot';
        dot.addEventListener('click', () => slideshowContainer.scrollLeft = index * slides[0].offsetWidth);
        dotsContainer.appendChild(dot);
    });
    slideshowContainer.insertAdjacentElement('afterend', dotsContainer);

    const updateDots = () => {
        const index = Math.round(slideshowContainer.scrollLeft / slides[0].offsetWidth);
        dotsContainer.querySelectorAll('.dot').forEach((dot, i) =>
            dot.classList.toggle('active', i === index)
        );
    };
    slideshowContainer.addEventListener('scroll', updateDots);
    updateDots();
});

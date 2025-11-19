/**
 * Maho
 *
 * @package     base_default
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2021-2022 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!window.Catalog) {
    window.Catalog = {};
}

Catalog.Map = {
    helpLinks: [],
    active: false,

    addHelpLink(linkElement, title, actualPrice, msrpPrice, addToCartLink) {
        if (typeof linkElement === 'string') {
            linkElement = document.getElementById(linkElement);
        }

        if (!linkElement) {
            return;
        }

        const helpLink = {
            link: linkElement
        };

        let showPopup = false;

        if (typeof title === 'string' && title) {
            helpLink.title = title;
            showPopup = true;
        }

        if ((typeof actualPrice === 'string' && actualPrice) || (typeof actualPrice === 'object' && actualPrice)) {
            helpLink.price = actualPrice;
            showPopup = true;
        }

        if (typeof msrpPrice === 'string' && msrpPrice) {
            helpLink.msrp = msrpPrice;
            showPopup = true;
        }

        if (typeof addToCartLink === 'string' && addToCartLink) {
            helpLink.cartLink = addToCartLink;
        } else if (addToCartLink && addToCartLink.url) {
            helpLink.cartLink = addToCartLink.url;
            if (addToCartLink.qty) {
                helpLink.qty = addToCartLink.qty;
            }
            if (addToCartLink.notUseForm) {
                helpLink.notUseForm = addToCartLink.notUseForm;
            }
        }

        if (!showPopup) {
            this.setGotoView(linkElement, addToCartLink);
        } else {
            const helpLinkIndex = this.helpLinks.push(helpLink) - 1;
            linkElement.addEventListener('click', this.showHelp.bind(this.helpLinks[helpLinkIndex]));
        }
        return helpLink;
    },

    setGotoView(element, viewPageUrl) {
        const oldClickHandler = element.onclick;
        element.removeEventListener('click', oldClickHandler);
        element.href = viewPageUrl;
        if (window.opener) {
            element.addEventListener('click', (event) => {
                setPLocation(this.href, true);
                Catalog.Map.hideHelp();
                event.preventDefault();
            });
        } else {
            element.addEventListener('click', (event) => {
                setLocation(this.href);
                Catalog.Map.hideHelp();
                event.preventDefault();
            });
        }
    },

    showSelects() {
        document.querySelectorAll('select')
            .forEach(select => select.style.visibility = 'visible');
    },

    hideSelects() {
        document.querySelectorAll('select')
            .forEach(select => select.style.visibility = 'hidden');
    },

    showHelp(event) {
        const helpBox = document.getElementById('map-popup');
        if (!helpBox) {
            return;
        }

        // Move help box to be right in body tag
        const bodyNode = document.body;
        if (helpBox.parentNode !== bodyNode) {
            helpBox.remove();
            bodyNode.appendChild(helpBox);
        }

        if (this !== Catalog.Map && Catalog.Map.active !== this.link) {
            helpBox.style.display = 'none';
            if (!helpBox.offsetPosition) {
                helpBox.offsetPosition = { left: 0, top: 0 };
            }

            // First, display the popup but make it invisible to get its dimensions
            helpBox.style.display = 'block';
            helpBox.style.visibility = 'hidden';

            // Get viewport and popup dimensions
            const viewportWidth = Math.max(document.documentElement.clientWidth, window.innerWidth || 0);
            const popupWidth = helpBox.offsetWidth;

            // Calculate left position
            let leftPos = event.pageX - (popupWidth / 2);

            // Adjust if popup would overflow right edge
            if (leftPos + popupWidth > viewportWidth) {
                leftPos = viewportWidth - popupWidth - 10; // 10px padding from right edge
            }

            // Adjust if popup would overflow left edge
            if (leftPos < 10) {
                leftPos = 10; // 10px padding from left edge
            }

            // Set the position and make visible
            helpBox.style.left = `${leftPos}px`;
            helpBox.style.top = `${event.pageY + 10}px`;
            helpBox.style.visibility = 'visible';

            // Title
            const mapTitle = document.getElementById('map-popup-heading');
            if (typeof this.title !== 'undefined') {
                mapTitle.textContent = this.title;
                mapTitle.style.display = 'block';
            } else {
                mapTitle.style.display = 'none';
            }

            // MSRP price
            const mapMsrp = document.getElementById('map-popup-msrp-box');
            if (typeof this.msrp !== 'undefined') {
                document.getElementById('map-popup-msrp').textContent = this.msrp;
                mapMsrp.style.display = 'block';
            } else {
                mapMsrp.style.display = 'none';
            }

            // Actual price
            const mapPrice = document.getElementById('map-popup-price-box');
            if (typeof this.price !== 'undefined') {
                const price = typeof this.price === 'object' ? this.price.innerHTML : this.price;
                document.getElementById('map-popup-price').innerHTML = price;
                mapPrice.style.display = 'block';
            } else {
                mapPrice.style.display = 'none';
            }

            // Add to cart button
            const cartButton = document.getElementById('map-popup-button');
            if (typeof this.cartLink !== 'undefined') {
                if (typeof productAddToCartForm === 'undefined' || this.notUseForm) {
                    Catalog.Map.setGotoView(cartButton, this.cartLink);
                    productAddToCartForm = document.getElementById('product_addtocart_form_from_popup');
                } else {
                    if (this.qty) {
                        productAddToCartForm.qty = this.qty;
                    }
                    cartButton.removeEventListener('click', this.showHelp);
                    cartButton.href = this.cartLink;
                    cartButton.addEventListener('click', () => {
                        productAddToCartForm.action = cartButton.href;
                        productAddToCartForm.submit(cartButton);
                    });
                }
                productAddToCartForm.action = this.cartLink;
                const productField = document.getElementById('map-popup-product-id');
                productField.value = this.product_id;
                cartButton.style.display = 'block';
                document.querySelectorAll('.additional-addtocart-box')
                    .forEach(el => el.style.display = 'block');
            } else {
                cartButton.style.display = 'none';
                document.querySelectorAll('.additional-addtocart-box')
                    .forEach(el => el.style.display = 'none');
            }

            // Horizontal line
            const mapText = document.getElementById('map-popup-text');
            const mapTextWhatThis = document.getElementById('map-popup-text-what-this');
            const mapContent = document.getElementById('map-popup-content');

            if (mapMsrp.style.display === 'none' &&
                mapPrice.style.display === 'none' &&
                cartButton.style.display === 'none') {
                // If just "What's this?" link
                mapText.style.display = 'none';
                mapTextWhatThis.style.display = 'block';
                mapTextWhatThis.classList.remove('map-popup-only-text');
                mapContent.style.display = 'none';
                mapContent.style.visibility = 'hidden';
                document.getElementById('product_addtocart_form_from_popup').style.display = 'none';
            } else {
                mapTextWhatThis.style.display = 'none';
                mapText.style.display = 'block';
                mapText.classList.add('map-popup-only-text');
                mapContent.style.display = 'block';
                mapContent.style.visibility = 'visible';
                document.getElementById('product_addtocart_form_from_popup').style.display = 'block';
            }

            helpBox.style.display = 'block';
            const closeButton = document.getElementById('map-popup-close');
            if (closeButton) {
                closeButton.removeEventListener('click', this.showHelp);
                // Changed this line to use hideHelp directly instead of showHelp
                closeButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    Catalog.Map.hideHelp();
                });
                Catalog.Map.active = this.link;
            }
        } else {
            helpBox.style.display = 'none';
            Catalog.Map.active = false;
        }

        if (helpBox && this != Catalog.Map && Catalog.Map.active != this.link) {
            helpBox.classList.remove('map-popup-right');
            helpBox.classList.remove('map-popup-left');

            // Replace Element.getWidth with standard JavaScript
            const helpBoxWidth = helpBox.offsetWidth;
            const bodyWidth = bodyNode.offsetWidth;

            if (bodyWidth < event.pageX + (helpBoxWidth / 2)) {
                helpBox.classList.add('map-popup-left');
            } else if (event.pageX - (helpBoxWidth / 2) < 0) {
                helpBox.classList.add('map-popup-right');
            }
        }

        event.preventDefault();
    },

    hideHelp() {
        const helpBox = document.getElementById('map-popup');
        if (helpBox) {
            helpBox.style.display = 'none';
            Catalog.Map.active = false;
        }
    },

    bindProductForm() {
        let productAddToCartFormOld;

        if (typeof productAddToCartForm !== 'undefined' && productAddToCartForm) {
            productAddToCartFormOld = productAddToCartForm;
            productAddToCartForm = new VarienForm('product_addtocart_form_from_popup');
            productAddToCartForm.submitLight = productAddToCartFormOld.submitLight;
        } else if (!document.getElementById('product_addtocart_form_from_popup')) {
            return false;
        } else if (typeof productAddToCartForm === 'undefined') {
            productAddToCartForm = new VarienForm('product_addtocart_form_from_popup');
        }

        productAddToCartForm.submit = function(button, url) {
            if (typeof productAddToCartFormOld !== 'undefined' && productAddToCartFormOld) {
                if (Catalog.Map.active) {
                    Catalog.Map.hideHelp();
                }
                if (productAddToCartForm.qty && document.getElementById('qty')) {
                    document.getElementById('qty').value = productAddToCartForm.qty;
                }
                const parentResult = productAddToCartFormOld.submit();
                return false;
            }

            if (window.opener) {
                const parentButton = button;
                fetch(this.form.action + '?isAjax=1&method=GET')
                    .then(() => {
                        window.opener.focus();
                        if (parentButton && parentButton.href) {
                            setPLocation(parentButton.href, true);
                            Catalog.Map.hideHelp();
                        }
                    });
                return;
            }

            if (this.validator.validate()) {
                const form = this.form;
                const oldUrl = form.action;

                if (url) {
                    form.action = url;
                }
                if (!form.getAttribute('action')) {
                    form.action = productAddToCartForm.action;
                }
                try {
                    form.submit();
                } catch (e) {
                    form.action = oldUrl;
                    throw e;
                }
                form.action = oldUrl;

                if (button && button !== 'undefined') {
                    button.disabled = true;
                }
            }
        };
    }
};

window.addEventListener('resize', (event) => {
    if (Catalog.Map.active) {
        Catalog.Map.showHelp(event);
    }
});

document.addEventListener('bundle:reload-price', (event) => {
    const { bundle } = event.detail;
    if (!Number(bundle.config.isMAPAppliedDirectly) && !Number(bundle.config.isFixedPrice)) {
        let canApplyMAP = false;
        try {
            for (const option in bundle.config.selected) {
                if (bundle.config.options[option] && bundle.config.options[option].selections) {
                    const selections = bundle.config.options[option].selections;
                    for (let i = 0; i < bundle.config.selected[option].length; i++) {
                        const selectionId = bundle.config.selected[option][i];
                        if (Number(selections[selectionId].canApplyMAP)) {
                            canApplyMAP = true;
                            break;
                        }
                    }
                }
                if (canApplyMAP) {
                    break;
                }
            }
        } catch (e) {
            canApplyMAP = true;
        }

        if (canApplyMAP) {
            document.querySelectorAll('.full-product-price')
                .forEach(el => el.style.display = 'none');
            document.querySelectorAll('.map-info')
                .forEach(el => el.style.display = 'block');
            event.noReloadPrice = true;
        } else {
            document.querySelectorAll('.full-product-price')
                .forEach(el => el.style.display = 'block');
            document.querySelectorAll('.map-info')
                .forEach(el => el.style.display = 'none');
        }
    }
});

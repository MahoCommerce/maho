/**
 * Maho
 *
 * @package     base_default
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

var ConfigurableMediaImages = {
    imageType: null,
    productImages: {},
    imageObjects: {},

    arrayIntersect: function(a, b) {
        return a.filter(value => b.includes(value));
    },

    getCompatibleProductImages: function(productFallback, selectedLabels) {
        // Find compatible products
        let compatibleProducts = [];
        let compatibleProductSets = [];

        selectedLabels.forEach(function(selectedLabel) {
            if (productFallback['option_labels'] && productFallback['option_labels'][selectedLabel]) {
                let optionProducts = productFallback['option_labels'][selectedLabel]['products'];
                compatibleProductSets.push(optionProducts);

                // Optimistically push all products
                optionProducts.forEach(function (productId) {
                    compatibleProducts.push(productId);
                });
            }
        });

        // Intersect compatible products
        compatibleProductSets.forEach(function(productSet) {
            compatibleProducts = ConfigurableMediaImages.arrayIntersect(compatibleProducts, productSet);
        });

        return compatibleProducts;
    },

    isValidImage: function(fallbackImageUrl) {
        return !!fallbackImageUrl;
    },

    getSwatchImage: function(productId, optionLabel, selectedLabels) {
        var fallback = ConfigurableMediaImages.productImages[productId];
        if (!fallback) {
            return null;
        }

        // First, try to get label-matching image on config product for this option's label
        if (fallback['option_labels'] && fallback['option_labels'][optionLabel]) {
            var currentLabelImage = fallback['option_labels'][optionLabel];
            if (currentLabelImage && fallback['option_labels'][optionLabel]['configurable_product'][ConfigurableMediaImages.imageType]) {
                // Found label image on configurable product
                return fallback['option_labels'][optionLabel]['configurable_product'][ConfigurableMediaImages.imageType];
            }
        }

        var compatibleProducts = ConfigurableMediaImages.getCompatibleProductImages(fallback, selectedLabels);

        if (compatibleProducts.length === 0) { // No compatible products
            return null; // Bail
        }

        // Second, get any product which is compatible with currently selected option(s)
        var optionLabels = fallback['option_labels'];
        for (var key in optionLabels) {
            if (optionLabels.hasOwnProperty(key)) {
                var value = optionLabels[key];
                var image = value['configurable_product'][ConfigurableMediaImages.imageType];
                var products = value['products'];

                if (image) { // Configurable product has image in the first place
                    // If intersection between compatible products and this label's products, we found a match
                    var isCompatibleProduct = products.some(function(productId) {
                        return compatibleProducts.includes(productId);
                    });

                    if (isCompatibleProduct) {
                        return image;
                    }
                }
            }
        }

        // Third, get image off of child product which is compatible
        var childSwatchImage = null;
        var childProductImages = fallback[ConfigurableMediaImages.imageType];

        // Replace .each with .some for early breaking
        compatibleProducts.some(function(productId) {
            if (childProductImages[productId] && ConfigurableMediaImages.isValidImage(childProductImages[productId])) {
                childSwatchImage = childProductImages[productId];
                return true; // Break the loop
            }
            return false;
        });

        if (childSwatchImage) {
            return childSwatchImage;
        }

        // Fourth, get base image off parent product
        if (childProductImages[productId] && ConfigurableMediaImages.isValidImage(childProductImages[productId])) {
            return childProductImages[productId];
        }

        // No fallback image found
        return null;
    },

    getImageObject: function(productId, imageUrl) {
        var key = productId + '-' + imageUrl;
        if (!ConfigurableMediaImages.imageObjects[key]) {
            var image = document.createElement('img');
            image.src = imageUrl;
            ConfigurableMediaImages.imageObjects[key] = image;
        }
        return ConfigurableMediaImages.imageObjects[key];
    },

    updateImage: function(el) {
        var select = el;
        var label = select.options[select.selectedIndex].getAttribute('data-label');
        var productId = optionsPrice.productId; // Get product ID from options price object

        // Find all selected labels
        var selectedLabels = [];

        var superAttributeSelects = document.querySelectorAll('.product-options .super-attribute-select');
        superAttributeSelects.forEach(function(option) {
            if (option.value !== '') {
                selectedLabels.push(option.options[option.selectedIndex].getAttribute('data-label'));
            }
        });

        var swatchImageUrl = ConfigurableMediaImages.getSwatchImage(productId, label, selectedLabels);
        if (!ConfigurableMediaImages.isValidImage(swatchImageUrl)) {
            // no image found
            return;
        }

        var swatchImage = ConfigurableMediaImages.getImageObject(productId, swatchImageUrl);
        this.swapImage(swatchImage);
    },

    swapImage: function(targetImage) {
        targetImage.classList.add('gallery-image');

        var imageGallery = document.querySelector('.product-image-gallery');

        if (targetImage.complete) { // Image already loaded -- swap immediately
            var galleryImages = imageGallery.querySelectorAll('.gallery-image');
            galleryImages.forEach(function(image) {
                image.classList.remove('visible');
            });

            // Move target image to correct place, in case it's necessary
            imageGallery.appendChild(targetImage);

            // Reveal new image
            targetImage.classList.add('visible');
        } else { // Need to wait for image to load
            // Add spinner
            imageGallery.classList.add('loading');

            // Move target image to correct place, in case it's necessary
            imageGallery.appendChild(targetImage);

            // Wait until image is loaded
            targetImage.addEventListener('load', function() {
                // Remove spinner
                imageGallery.classList.remove('loading');

                // Hide old image
                var galleryImages = imageGallery.querySelectorAll('.gallery-image');
                galleryImages.forEach(function(image) {
                    image.classList.remove('visible');
                });

                // Reveal new image
                targetImage.classList.add('visible');
            });
        }
    },

    wireOptions: function() {
        var selectElements = document.querySelectorAll('.product-options .super-attribute-select');
        selectElements.forEach(function(selectElement) {
            selectElement.addEventListener('change', function(e) {
                ConfigurableMediaImages.updateImage(this);
            });
        });
    },

    swapListImage: function(productId, imageObject) {
        var originalImage = document.querySelector('#product-collection-image-' + productId);

        if (imageObject.complete) { // Swap image immediately
            // Remove old image
            originalImage.classList.add('hidden');
            document.querySelectorAll('.product-collection-image-' + productId).forEach(function (image) {
                image.remove();
            });

            // Add new image
            originalImage.parentNode.insertBefore(imageObject, originalImage.nextSibling);
        } else { // Need to load image
            var wrapper = originalImage.parentNode;

            // Add spinner
            wrapper.classList.add('loading');

            // Wait until image is loaded
            imageObject.addEventListener('load', function () {
                // Remove spinner
                wrapper.classList.remove('loading');

                // Remove old image
                originalImage.classList.add('hidden');
                document.querySelectorAll('.product-collection-image-' + productId).forEach(function (image) {
                    image.remove();
                });

                // Add new image
                originalImage.parentNode.insertBefore(imageObject, originalImage.nextSibling);
            });
        }
    },

    swapListImageByOption: function(productId, optionLabel) {
        var swatchImageUrl = ConfigurableMediaImages.getSwatchImage(productId, optionLabel, [optionLabel]);
        if (!swatchImageUrl) {
            return;
        }

        var newImage = ConfigurableMediaImages.getImageObject(productId, swatchImageUrl);
        newImage.classList.add('product-collection-image-' + productId);

        ConfigurableMediaImages.swapListImage(productId, newImage);
    },

    setImageFallback: function(productId, imageFallback) {
        ConfigurableMediaImages.productImages[productId] = imageFallback;
    },

    init: function(imageType) {
        ConfigurableMediaImages.imageType = imageType;
        ConfigurableMediaImages.wireOptions();
    }
};

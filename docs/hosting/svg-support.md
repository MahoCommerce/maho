---
description: Upload SVG images in Maho for favicons and other contexts, with automatic sanitization that strips malicious code to keep every upload secure.
---

# SVG Image Uploads <span class="version-badge">v25.11+</span>

Maho includes comprehensive support for uploading SVG (Scalable Vector Graphics) files in specific contexts, with automatic sanitization to ensure security.

## Supported Upload Contexts

SVG uploads are currently supported in the following areas:

### 1. Favicon
Navigate to **System → Configuration → General → Design → HTML Head → Favicon Icon**

Upload an SVG favicon that will scale perfectly across all devices and screen resolutions.

**Allowed formats**: ico, png, gif, jpg, jpeg, apng, webp, avif, **svg**

### 2. Product Placeholder Images
Navigate to **System → Configuration → Catalog → Catalog → Product Image Placeholders**

Upload SVG placeholders that will be used when product images are missing.

**Allowed formats**: webp, avif, jpg, jpeg, png, gif, bmp, **svg**

## Benefits of SVG

SVG (Scalable Vector Graphics) offers several advantages over raster image formats:

- **Perfect scaling**: Looks crisp at any resolution (from 16px to 4K displays)
- **Small file size**: Typically smaller than equivalent PNG/ICO files
- **Modern standard**: Supported by all modern browsers
- **Ideal for logos**: Perfect for simple graphics, icons, and logos

## Security

All uploaded SVG files are automatically sanitized using [Symfony HtmlSanitizer](https://symfony.com/doc/current/components/html_sanitizer.html) to remove potentially dangerous content.

### What Gets Blocked

The following dangerous elements are automatically removed from uploaded SVG files:

- **JavaScript**: `<script>` tags and event handlers
- **External resources**: `<image>`, `<link>`, `<use>` elements that could load external content
- **HTML embedding**: `<foreignObject>` elements
- **Plugins**: `<object>`, `<embed>` elements
- **Media**: `<video>`, `<audio>` elements
- **CSS attacks**: `<style>` tags

### What's Allowed

Clean, self-contained SVG graphics including:

- Standard SVG elements: `<svg>`, `<path>`, `<circle>`, `<rect>`, `<g>`, etc.
- Gradients: `<linearGradient>`, `<radialGradient>`, `<stop>`
- Text: `<text>`, `<tspan>`
- Basic shapes: `<line>`, `<polyline>`, `<polygon>`, `<ellipse>`
- Definitions: `<defs>`, `<clipPath>`, `<mask>`, `<symbol>`
- Safe attributes: fill, stroke, transform, opacity, etc.

## Why Not All Image Uploads?

SVG support is **intentionally limited** to specific contexts.

### Where SVG is NOT Supported

- **Product images**: Product images need to be resized, thumbnailed, and processed by PHP's GD library, which cannot handle SVG files
- **Category images**: Same reason as product images
- **Blog post images**: May require resizing and processing
- **CMS content images**: User-generated content images

### Technical Reason

SVG files are XML-based vector graphics, not raster images. They cannot be processed by PHP's `imagecreatefromstring()` or other GD library functions used for:

- Generating thumbnails
- Resizing images
- Image quality optimization
- Watermarking
- Format conversion

This is why SVG is only enabled for contexts where the image is used as-is without any server-side processing.

## Troubleshooting

### SVG Looks Different After Upload

If your SVG appearance changes after upload, this is because dangerous or external elements were removed during sanitization:

- **External resources stripped**: Any `xlink:href` or `href` pointing to external URLs
- **Styles removed**: Inline `<style>` tags are blocked for security
- **Fonts changed**: External font references are removed

Solution: Create self-contained SVGs without external dependencies.

## Technical Implementation

For developers interested in the technical details:

### Validation Process

1. File extension check: Must be `.svg`
2. XML validation: File must be valid XML with `<svg>` root element
3. Sanitization: Symfony HtmlSanitizer removes dangerous content
4. Content rewrite: Sanitized content is written back to the uploaded file

### Adding SVG Support to Custom Modules

If you're developing a custom module and want to add SVG support to an image upload field:

```php
class Your_Module_Model_System_Config_Backend_YourImage
    extends Mage_Adminhtml_Model_System_Config_Backend_Image
{
    protected function _getAllowedExtensions()
    {
        return array_merge(parent::_getAllowedExtensions(), ['svg']);
    }
}
```

The parent `Image` backend model will automatically:

1. Add the SVG validator when 'svg' is in allowed extensions
2. Skip GD image processing for SVG files
3. Sanitize SVG content using Symfony HtmlSanitizer

## Related Documentation

For developers looking to use SVG icons in templates, see the **[Maho Icons Library](../developer/icons-library.md)** documentation, which covers the built-in library of nearly 6,000 optimized icons available for use without uploading files.

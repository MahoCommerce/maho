---
description: Show color, image and text swatches instead of dropdowns - the settings that turn them on, the three ways to give a value an appearance, and what changes in listings and filters.
---

# Configurable Swatches

Swatches replace the plain dropdown of a configurable product with something a customer recognizes
at a glance: a color, a sample of fabric, a pattern, or the option name as a text chip. The same
feature also puts swatches in product listings and in the layered navigation filters, so a customer
can pick a color without opening the product page.

Swatches do not create a new product type and they do not change stock. A swatch only decides how
the options of an attribute are drawn, and the attribute must have options for swatches to make
sense.

## Turning swatches on

Go to **System > Configuration > Catalog > Configurable Swatches**.

| Setting | What it does |
|---------|--------------|
| Enabled | Turns the feature on |
| Product Attributes to Show as Swatches in Product Detail | The attributes drawn as swatches on the product page, in the layered navigation filters and in the "shopping by" tag, for example `color` and `size` |
| Product Attribute to Use for Swatches in Product Listing | The single attribute used in category and search results |
| Dynamic Price Change for Swatches in Product Listing | When a customer picks a swatch in a listing, the price shown follows the selected variant |
| Swatch Dimensions on Product Detail Page | Inner size of the swatches on the product page, in pixels |
| Swatch Dimensions in Product Listing | Inner size of the swatches in listings |
| Swatch Dimensions in Layered Navigation | Inner size of the swatches in filters |

Two things are easy to miss:

- The feature is off when **Product Attribute to Use for Swatches in Product Listing** is empty,
  even if Enabled is set to Yes. The product page stops showing swatches too.
- An attribute is drawn with swatches only if it is listed in one of the two attribute settings.
  Making the attribute filterable is not enough on its own.

## How a swatch can look

A swatch shows one of three things. When a value has more than one, the order in
[Which appearance wins](#which-appearance-wins) decides:

| Appearance | What it is | Good for |
|------------|------------|----------|
| Color | a solid color, one per option | standard colors, no photography of the single values |
| Image | a catalog image (one file per value, shared by every product) or a product image (one picture on one product: a fabric, a texture, a print) | fabrics, materials, patterns |
| Text | the option name, also the fallback when a value has no color and no image | sizes |

## Setting a color

1. Go to **Catalog > Attributes > Manage Attributes** and open the attribute, `color` for example.
2. Open the **Manage Label / Options** tab. Every option row has a color picker.
3. Pick the color and save the attribute.

The color picker appears only for the attributes listed in **Product Attributes to Show as
Swatches in Product Detail**. A color belongs to the option, so it is the same in every store
view: use an image if a value has to look different per store view.

## Using a catalog image

A catalog image is one small file per value, shared by every product that carries that value. This
is how a fabric or a material is shown consistently across a catalog.

1. Name the file after the option: lower case, with spaces and symbols turned into hyphens.
   `Blue Light` becomes `blue-light.webp`, `Caffè` becomes `caffe.webp`, `70% Lana` becomes
   `70-lana.webp`.
2. Use the image format of the store, the one set under **System > Configuration > Advanced >
   System > Storage Configuration for Media**, in **File type for image conversions**. With WebP
   configured, the file must be `blue-light.webp`; another extension is not found.
3. Upload the file into the `wysiwyg/swatches` folder of the media storage, for example from
   **CMS > Media Library**. There is no upload field in the attribute form: the swatch image is a
   media file, so it is uploaded like any other media file.
4. To replace a swatch, delete the old file first. If a file with the same name exists, the Media
   Library saves the new file as `blue-light_1.webp`, and the store does not find that name.

A square image of about 100 x 100 pixels is enough. The store generates the sizes it needs.

When the option name is translated in a store view, the store looks for the file named after the
name in the default language, so one file serves all the translations of that value. That fallback
needs the value to have at least one variant visible in the store view: a color that is out of
stock and hidden falls back to the option name as text.

## Using a product image

A product image is a picture on one configurable product that serves as the swatch of one value.
Use it when the swatch belongs to a single product, for example a print that only exists on one
shirt.

1. Open the **configurable** product, not a child variant.
2. In the **Images** tab upload the picture, or pick an existing one.
3. In its **Label** field write the option name, then `-swatch`, for example `Bianco-swatch`. Write
   it in the language of the store view where it has to appear; the default language is the
   fallback.
4. Save the product.

The label decides the relationship between the swatch and the photo of that option:

| Label of the image | Swatch | Photo of that option |
|--------------------|--------|----------------------|
| `Bianco-swatch` | shows this image | stays the photo of the variant |
| `Bianco` | shows this image only when there is no color and no catalog image for the value | becomes this image |

So a label without the suffix is the way to have one picture that is both the swatch and the photo
of that color. The suffix is the way to show a texture as a swatch and keep the garment photo.

In both cases the image is hidden from the product gallery, so it does not appear twice.

The label works on the parent product only: images uploaded on the child variants are not used for
swatches.

## Which appearance wins

When a value has more than one appearance, the store uses the first one that exists:

1. the product image labeled `<option name>-swatch`;
2. the catalog image in `wysiwyg/swatches`;
3. the color of the option;
4. the product image labeled `<option name>`;
5. the option name as text.

Two practical consequences: a catalog image hides a product image without the suffix (the photo of
the option still changes, the swatch does not), and the suffix always wins, which is why it is the
safe choice for a texture.

## Where swatches appear

| Place | Which attributes | Which appearance |
|-------|------------------|------------------|
| Product page | every attribute enabled for the product page | color, catalog image, product image |
| Category and search results | the single listing attribute, shown when the product has at least two values | color, catalog image, product image |
| Layered navigation filters | the filterable attributes enabled as swatches | color or catalog image |
| "Shopping by" tag of an active filter | the same attributes | color or catalog image |

!!! note "Filters never show product images"
    A product image is a product page and listing feature. Both the filter list and the
    "shopping by" tag use only the color or the catalog image, so a value that has to look the
    same in a filter needs one of those two.

## What happens when a customer picks a swatch

- The price updates to the price of the configurable product plus the price of the selected
  option, if the option has one (see [Configurable product prices](configurable-product-prices.md)).
  The main image changes to the image of that option.
- With **Dynamic Price Change for Swatches in Product Listing** enabled, the price in a listing
  updates without opening the product page.
- Options that are out of stock follow the store setting about showing out of stock products.

## Troubleshooting

| Symptom | What to check |
|---------|---------------|
| Swatches show as text | The value has no color, no catalog image and no product image |
| No swatch and no dropdown | Configurable Swatches is disabled, or the listing attribute is empty |
| No color picker in the attribute | The attribute is not listed in **Product Attributes to Show as Swatches in Product Detail** |
| A catalog image is ignored | The file name (lower case, hyphens, exact name) or the extension. On most servers file names are case sensitive, so `Blue-Light.webp` does not match `blue-light.webp` |
| A product image does not appear | A catalog image with that name exists and wins, or the image is on a child variant instead of the configurable product |
| Text in one store view, image in another | The value has no variant visible in that store view, for example out of stock and hidden, so the translated name cannot be connected to the file |
| The previous swatch is still visible | The new file was saved with a suffix, such as `blue-light_1.webp`. Delete both files, then upload the new one again |

## Related

- [Product Types](product-types.md) for configurable products and their variants.
- [SEO](seo.md) for the crawl rules of layered navigation pages, which also carry the swatches.

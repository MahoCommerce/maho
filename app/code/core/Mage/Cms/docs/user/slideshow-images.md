---
description: Give a slide of a content slideshow a different image on a phone or a tablet, with the breakpoint presets of the store or a custom width in pixels.
admin_pages: [cms_page/edit, cms_block/edit]
---

# Slideshow Images for Each Screen Size <span class="version-badge">v27.1+</span>

A slide of a slideshow can show a different image on a small screen. For example, a wide banner
shows on a computer, and a tall version of the same banner shows on a phone.

Each alternative image has a **breakpoint**. The breakpoint is the widest screen, in pixels, that
shows the alternative image. A screen that is wider than every breakpoint shows the default image of
the slide.

## Add an image for a small screen

1. Double-click the slideshow in the content editor.
2. Find the slide, then click **Add mobile image**.
3. Select the image in the media library.
4. In **Breakpoint**, select a preset from the list, or type a width in pixels, such as `900`.
5. Click **Insert Slideshow**, then save the page or the block.

A slide can have more than one alternative image, for example one for **Mobile** and one for
**Tablet**. Two images of the same slide cannot have the same width. The editor puts the narrowest
breakpoint first, because the browser uses the first image that matches the screen.

## Presets and custom widths

| Breakpoint | The content stores | After a change of the preset |
|------------|--------------------|------------------------------|
| A preset, such as **Mobile** | The name of the preset | The slide uses the new width |
| A width that you type, such as `900` | The width in pixels | The slide keeps its width |

After a preset, the field shows the current width of the preset, such as **767 px**. Use a preset
for most slides. The presets follow the theme of the store, so a new theme needs a change in one
place only. Type a width for a slide that must change at a width of its own.

## Change the presets

Go to **System > Configuration > Design > Breakpoints**. The default values are:

| Preset | Default width |
|--------|---------------|
| Mobile | 767 |
| Tablet | 1023 |

Each value is a whole number from 1 to 9999. **Tablet** must be wider than **Mobile**. You can set a
different value for each website or store view (see [Scope](scope.md)).

!!! warning "A slide with a preset and a custom width"
    The editor sorts the images of a slide when you save the slideshow. If you later change a preset
    to a width on the other side of a custom width of the same slide, the order of the images is
    wrong. Open the slideshow and click **Insert Slideshow** again to sort the images.

## In the source view

The source view shows each alternative image as a `<source>` in a `<picture>`. A preset is a
`{{breakpoint}}` directive. The store replaces it with the width of the preset when it shows the
page.

```html
<picture>
    <source media="{{breakpoint code=mobile}}" srcset="{{media url="wysiwyg/banner-mobile.jpg"}}">
    <source media="(max-width: 1100px)" srcset="{{media url="wysiwyg/banner-tablet.jpg"}}">
    <img src="{{media url="wysiwyg/banner.jpg"}}" alt="Summer sale">
</picture>
```

Write the code of the preset without quotes, as in the example. If the store does not know the
code, the slide shows the default image on every screen. The content editor removes a `<source>`
whose `media` is not a preset or one `(max-width: ...px)` value, and it shows a message before it
removes it.

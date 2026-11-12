---
title: "The slideshow keeps a manual option for the high priority of its first image, with a label that names the effect"
author: Fabrizio
date: 2026-11-12
branch: feature/slideshow-image-priority
cost: { minutes: 5, tokens: 18586 }
id: l-bbcce922
scope: repo
status: active
src: agent
touches:
  - app/code/core/Mage/Cms/docs/user/slideshow-images.md
  - public/js/mage/adminhtml/wysiwyg/tiptap/extensions.js
  - app/code/core/Mage/Cms/etc/jstranslator.xml
  - app/locale/en_US/Mage_Cms.csv
---

## Decided
- The high fetch priority of the first slideshow image stays a manual checkbox in the slideshow dialog, on by default.
- Reason: the editor sees one content only. A page shows many contents (CMS page, blocks, widgets), and one block can show at the top of one page and in the footer of another. Only the merchant knows where the slideshow shows.
- The label names what the option does on the page: "Load the first image with high priority on the page".

## Tried and ruled out
- An automatic rule (the first slideshow in a content gets the priority). Each content then has its own "first slideshow", and nothing can turn off a wrong one.
- A label that describes where the slideshow is ("This slideshow is at the top of the page"). The maintainer rejected it.
- The first label "Load the first image first". The maintainer found it unclear.

## Watch out
- A change to the label must change four places, or the translation breaks.

In the code:
- public/js/mage/adminhtml/wysiwyg/tiptap/extensions.js (MahoSlideshow dialog, node attribute `priority`)
- app/code/core/Mage/Cms/etc/jstranslator.xml (`slideshow-priority`)
- app/locale/en_US/Mage_Cms.csv
- app/code/core/Mage/Cms/docs/user/slideshow-images.md
- Issue #1785, PR #1813

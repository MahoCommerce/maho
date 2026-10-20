// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

import { COLUMN_PRESETS, BENTO_PRESETS, ACCORDION_STYLES } from './extensions.js';

const columnIcon = (key) => key.endsWith('-equal') ? `columns-${COLUMN_PRESETS[key].columns}` : `columns-${key}`;

/**
 * The blocks that the editor inserts, in sections. The toolbar dropdowns and the
 * Blocks pane of the workspace both read this list, so a new preset shows in both.
 *
 * Each item has the shape of a toolbar dropdown item: `title`, `icon`, and either
 * `command` with `args` or `onClick`. An item with `enabled: false` is left out.
 */
export const blockRegistry = (setup) => {
    const config = setup.config ?? {};
    const sections = [
        {
            id: 'columns',
            title: 'Columns',
            icon: 'columns-2',
            items: Object.entries(COLUMN_PRESETS).map(([key, preset]) => ({
                title: preset.label, icon: columnIcon(key), command: 'insertColumns', args: [key],
            })),
        },
        {
            id: 'bento',
            title: 'Bento Grid',
            icon: 'bento-grid',
            items: Object.entries(BENTO_PRESETS).map(([key, preset]) => ({
                title: preset.label, icon: `bento-${key}`, command: 'insertBentoGrid', args: [key],
            })),
        },
        {
            id: 'accordion',
            title: 'Accordion',
            icon: 'accordion',
            items: Object.entries(ACCORDION_STYLES).map(([key, style]) => ({
                title: style.label, icon: key, command: 'insertAccordion', args: [key],
            })),
        },
        {
            id: 'media',
            title: 'Media',
            items: [
                { title: 'Insert Image', icon: 'image', command: 'insertMahoImage', enabled: Boolean(config.add_images) },
                { title: 'Insert Slideshow', icon: 'slideshow', command: 'insertMahoSlideshow', enabled: Boolean(config.add_images) && config.add_slideshows !== false },
            ],
        },
        {
            id: 'dynamic',
            title: 'Dynamic',
            items: [
                { title: 'Insert Widget', icon: 'widget', command: 'insertMahoWidget', enabled: Boolean(config.add_widgets) },
                { title: 'Insert Variable', icon: 'variable', command: 'insertMahoVariable', enabled: Boolean(config.add_variables) },
            ],
        },
        {
            id: 'text',
            title: 'Text',
            items: [
                { title: 'Paragraph', icon: 'paragraph', command: 'setParagraph' },
                { title: 'Heading', icon: 'heading', command: 'setHeading', args: [{ level: 2 }] },
                { title: 'Insert Table', icon: 'table', command: 'insertTable', args: [{ rows: 3, cols: 3, withHeaderRow: true }] },
                { title: 'Button', icon: 'button', command: 'openButtonDialog' },
            ],
        },
    ];

    return sections
        .map((section) => ({ ...section, items: section.items.filter((item) => item.enabled ?? true) }))
        .filter((section) => section.items.length > 0);
};

/** A section of the registry as a dropdown of the main toolbar. */
export const blockDropdown = (setup, id) => {
    const section = blockRegistry(setup).find((item) => item.id === id);
    return section ? {
        type: 'dropdown',
        title: section.title,
        icon: section.icon,
        showTitle: false,
        showChevron: false,
        items: section.items,
    } : { type: 'button', enabled: false };
};

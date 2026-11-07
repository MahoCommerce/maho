// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

import { MahoCodeEditor, createXmlView } from './wysiwyg/tiptap/code-editor.js';

for (const id of ['mapping_xml_header', 'mapping_xml_footer']) {
    const textarea = document.getElementById(id);
    if (textarea) {
        new MahoCodeEditor(textarea, textarea, createXmlView);
    }
}

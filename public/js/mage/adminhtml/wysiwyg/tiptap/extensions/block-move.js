// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

import { Extension, Fragment, NodeSelection, TextSelection } from '../../../../../tiptap.js';

/**
 * Nodes that belong to the structure of their parent. A drag or a move never takes
 * one of them out of it: a cell without its grid, or a summary without its section,
 * is not valid content.
 */
export const STRUCTURAL_NODES = [
    'mahoColumn', 'mahoBentoCell', 'detailsSummary', 'detailsContent',
    'tableRow', 'tableCell', 'tableHeader',
];

/** A text block inside one of these moves with its parent, not alone. */
const WRAPPED_TEXT_PARENTS = ['listItem', 'tableCell', 'tableHeader', 'detailsSummary'];

/**
 * The block that a move takes: the selected node, else the deepest block around
 * the caret that is not part of a structure.
 */
const movableBlock = (selection) => {
    if (selection instanceof NodeSelection && selection.node.isBlock
        && !STRUCTURAL_NODES.includes(selection.node.type.name)) {
        return { pos: selection.from, node: selection.node };
    }
    const { $from } = selection;
    for (let depth = $from.depth; depth > 0; depth -= 1) {
        const node = $from.node(depth);
        if (STRUCTURAL_NODES.includes(node.type.name)) {
            continue;
        }
        if (node.isTextblock && WRAPPED_TEXT_PARENTS.includes($from.node(depth - 1).type.name)) {
            continue;
        }
        return { pos: $from.before(depth), node };
    }
    return null;
};

/**
 * Where the block goes: past its previous or next sibling, or out of its container
 * when it is the first or the last child. A container that cannot hold the block
 * (a grid holds cells only) is skipped, so the block lands next to the grid.
 */
const destination = ($pos, node, direction) => {
    const index = $pos.index();
    if (direction < 0 && index > 0) {
        return $pos.pos - $pos.parent.child(index - 1).nodeSize;
    }
    if (direction > 0 && index < $pos.parent.childCount - 1) {
        return $pos.pos + node.nodeSize + $pos.parent.child(index + 1).nodeSize;
    }
    for (let depth = $pos.depth; depth > 0; depth -= 1) {
        const parent = $pos.node(depth - 1);
        const at = $pos.index(depth - 1) + (direction > 0 ? 1 : 0);
        if (parent.canReplace(at, at, Fragment.from(node))) {
            return direction > 0 ? $pos.after(depth) : $pos.before(depth);
        }
    }
    return null;
};

const moveBlock = (direction) => ({ state, dispatch }) => {
    const block = movableBlock(state.selection);
    if (!block) {
        return false;
    }
    const $pos = state.doc.resolve(block.pos);
    const target = destination($pos, block.node, direction);
    if (target === null) {
        return false;
    }
    if (!dispatch) {
        return true;
    }

    const tr = state.tr.delete(block.pos, block.pos + block.node.nodeSize);
    // A container must keep one child: an empty paragraph takes the place of the block
    if ($pos.parent.childCount === 1) {
        tr.insert(block.pos, state.schema.nodes.paragraph.create());
    }
    const at = tr.mapping.map(target);
    tr.insert(at, block.node);
    tr.setSelection(block.node.isTextblock
        ? TextSelection.create(tr.doc, at + 1)
        : NodeSelection.create(tr.doc, at));
    dispatch(tr.scrollIntoView());
    return true;
};

export const MahoBlockMove = Extension.create({
    name: 'mahoBlockMove',

    addCommands() {
        return {
            moveBlockUp: () => moveBlock(-1),
            moveBlockDown: () => moveBlock(1),
        };
    },

    addKeyboardShortcuts() {
        return {
            'Alt-ArrowUp': () => this.editor.commands.moveBlockUp(),
            'Alt-ArrowDown': () => this.editor.commands.moveBlockDown(),
        };
    },
});

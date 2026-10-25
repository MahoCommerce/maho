<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho\Data;

use Maho\Data\Tree\Node;
use Maho\Data\Tree\Node\Collection as NodeCollection;

class Tree
{
    /**
     * Nodes collection
     *
     * @var NodeCollection
     */
    protected $_nodes;

    public function __construct()
    {
        $this->_nodes = new NodeCollection($this);
    }

    /**
     * @return Tree
     */
    public function getTree()
    {
        return $this;
    }

    /**
     * @param Node $parentNode
     * @param int $recursionLevel
     */
    public function load($parentNode = null, $recursionLevel = 0) {}

    /**
     * @param int $nodeId
     */
    public function loadNode($nodeId) {}

    /**
     * @param Node $parentNode
     * @param Node $prevNode
     * @return Node
     */
    public function appendChild(array|Node $data, $parentNode, $prevNode = null)
    {
        if (is_array($data)) {
            return $this->addNode(
                new Node($data, $parentNode->getIdField(), $this),
                $parentNode,
            );
        }
        return $this->addNode($data, $parentNode);
    }

    /**
     * @param Node $node
     * @param Node $parent
     * @return Node
     */
    public function addNode($node, $parent = null)
    {
        $this->_nodes->add($node);
        $node->setParent($parent);
        if (!is_null($parent) && ($parent instanceof Node)) {
            $parent->addChild($node);
        }
        return $node;
    }

    /**
     * @param Node $node
     * @param Node $parentNode
     * @param Node $prevNode
     */
    public function moveNodeTo($node, $parentNode, $prevNode = null) {}

    /**
     * @param Node $node
     * @param Node $parentNode
     * @param Node $prevNode
     */
    public function copyNodeTo($node, $parentNode, $prevNode = null) {}

    /**
     * @param Node $node
     * @return Tree
     */
    public function removeNode($node)
    {
        $this->_nodes->delete($node);
        if ($node->getParent()) {
            $node->getParent()->removeChild($node);
        }
        unset($node);
        return $this;
    }

    /**
     * @param Node $parentNode
     * @param Node $prevNode
     */
    public function createNode($parentNode, $prevNode = null) {}

    /**
     * @param Node $node
     */
    public function getChild($node) {}

    /**
     * @param Node $node
     */
    public function getChildren($node) {}

    /**
     * @return NodeCollection
     */
    public function getNodes()
    {
        return $this->_nodes;
    }

    /**
     * Retrieve ids of all nodes in the tree
     *
     * @return list<string>
     */
    public function getAllIds(): array
    {
        $ids = [];
        foreach ($this->getNodes() as $node) {
            $ids[] = $node->getId();
        }
        return $ids;
    }

    /**
     * @param int $nodeId
     * @return Node|null
     */
    public function getNodeById($nodeId)
    {
        return $this->_nodes->searchById($nodeId);
    }

    /**
     * @param Node $node
     * @return array
     */
    public function getPath($node)
    {
        if ($node instanceof Node) {
        } elseif (is_numeric($node)) {
            if ($_node = $this->getNodeById($node)) {
                return $_node->getPath();
            }
        }
        return [];
    }
}

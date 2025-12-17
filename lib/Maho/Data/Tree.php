<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
     */
    public function load($parentNode = null) {}

    /**
     * @param int $nodeId
     */
    public function loadNode($nodeId) {}

    /**
     * @param array|Node $data
     * @param Node $parentNode
     * @param Node $prevNode
     * @return Node
     */
    public function appendChild($data, $parentNode, $prevNode = null)
    {
        if (is_array($data)) {
            $node = $this->addNode(
                new Node($data, $parentNode->getIdField(), $this),
                $parentNode,
            );
        } elseif ($data instanceof Node) {
            $node = $this->addNode($data, $parentNode);
        }
        return $node;
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

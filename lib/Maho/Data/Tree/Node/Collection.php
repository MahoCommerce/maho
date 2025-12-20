<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Data\Tree\Node;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Maho\Data\Tree;
use Maho\Data\Tree\Node;

class Collection implements ArrayAccess, IteratorAggregate, Countable
{
    /**
     * @var Node[]
     */
    private $_nodes;

    /**
     * @var Tree
     */
    private $_container;

    /**
     * Node_Collection constructor.
     * @param $container
     */
    public function __construct($container)
    {
        $this->_nodes = [];
        $this->_container = $container;
    }

    /**
     * @return Node[]
     */
    public function getNodes()
    {
        return $this->_nodes;
    }

    /**
     * Implementation of IteratorAggregate::getIterator()
     */
    #[\Override]
    public function getIterator(): \Traversable
    {
        return new ArrayIterator($this->_nodes);
    }

    /**
     * Implementation of ArrayAccess:offsetSet()
     * @param string $key
     * @param string $value
     */
    #[\Override]
    public function offsetSet($key, $value): void
    {
        $this->_nodes[$key] = $value;
    }

    /**
     * Implementation of ArrayAccess:offsetGet()
     * @param string $key
     * @return mixed|Node
     */
    #[\ReturnTypeWillChange]
    #[\Override]
    public function offsetGet($key)
    {
        return $this->_nodes[$key];
    }

    /**
     * Implementation of ArrayAccess:offsetUnset()
     * @param string $key
     */
    #[\Override]
    public function offsetUnset($key): void
    {
        unset($this->_nodes[$key]);
    }

    /**
     * Implementation of ArrayAccess:offsetExists()
     * @param string $key
     */
    #[\Override]
    public function offsetExists($key): bool
    {
        return isset($this->_nodes[$key]);
    }

    /**
     * Adds a node to this node
     * @return Node
     */
    public function add(Node $node)
    {
        $node->setParent($this->_container);

        // Set the Tree for the node
        if ($this->_container->getTree() instanceof Tree) {
            $node->setTree($this->_container->getTree());
        }

        $this->_nodes[$node->getId()] = $node;

        return $node;
    }

    /**
     * @param Node $node
     * @return $this
     */
    public function delete($node)
    {
        $id = $node->getId();
        if (isset($this->_nodes[$id])) {
            unset($this->_nodes[$id]);
        }
        return $this;
    }

    /**
     * Implementation of Countable:count()
     */
    #[\Override]
    public function count(): int
    {
        return count($this->_nodes);
    }

    /**
     * @return Node|null
     */
    public function lastNode()
    {
        return empty($this->_nodes) ? null : $this->_nodes[count($this->_nodes) - 1];
    }

    /**
     * @param $nodeId
     * @return Node|null
     */
    public function searchById($nodeId)
    {
        return $this->_nodes[$nodeId] ?? null;
    }
}

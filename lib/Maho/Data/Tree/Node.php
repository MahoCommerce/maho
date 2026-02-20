<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Data\Tree;

use Maho\Data\Tree;
use Maho\Data\Tree\Node\Collection as NodeCollection;

/**
 * @method int getLevel()
 * @method string getClass()
 * @method string getPositionClass()
 * @method string getOutermostClass()
 * @method $this setOutermostClass(string $class)
 * @method $this setChildrenWrapClass(string $class)
 * @method bool getIsFirst()
 * @method bool getIsLast()
 */
class Node extends \Maho\DataObject
{
    /**
     * Parent node
     *
     * @var Node
     */
    protected $_parent;

    /**
     * Main tree object
     *
     * @var Tree
     */
    protected $_tree;

    /**
     * Child nodes
     *
     * @var NodeCollection
     */
    protected $_childNodes;

    /**
     * Node ID field name
     *
     * @var string
     */
    protected $_idField;

    /**
     * Data tree node constructor
     *
     * @param array $data
     * @param string $idField
     * @param Tree $tree
     * @param Node $parent
     */
    public function __construct($data, $idField, $tree, $parent = null)
    {
        $this->setTree($tree);
        $this->setParent($parent);
        $this->setIdField($idField);
        $this->setData($data);
        $this->_childNodes = new NodeCollection($this);
    }

    /**
     * Retrieve node id
     *
     * @return mixed
     */
    #[\Override]
    public function getId()
    {
        return $this->getData($this->getIdField());
    }

    /**
     * Set node id field name
     *
     * @param   string $idField
     * @return  $this
     */
    public function setIdField($idField)
    {
        $this->_idField = $idField;
        return $this;
    }

    /**
     * Retrieve node id field name
     *
     * @return string
     */
    public function getIdField()
    {
        return $this->_idField;
    }

    /**
     * Set node tree object
     *
     * @return  $this
     */
    public function setTree(Tree $tree)
    {
        $this->_tree = $tree;
        return $this;
    }

    /**
     * Retrieve node tree object
     *
     * @return Tree
     */
    public function getTree()
    {
        return $this->_tree;
    }

    /**
     * Set node parent
     *
     * @param   Node $parent
     * @return  Node
     */
    public function setParent($parent)
    {
        $this->_parent = $parent;
        return $this;
    }

    /**
     * Retrieve node parent
     *
     * @return Node
     */
    public function getParent()
    {
        return $this->_parent;
    }

    /**
     * Check node children
     *
     * @return bool
     */
    public function hasChildren()
    {
        return $this->_childNodes->count() > 0;
    }

    /**
     * @param int $level
     * @return $this
     */
    public function setLevel($level)
    {
        $this->setData('level', $level);
        return $this;
    }

    /**
     * @param int $path
     * @return $this
     */
    public function setPathId($path)
    {
        $this->setData('path_id', $path);
        return $this;
    }

    /**
     * @param Node $node
     * @todo LTS implement
     */
    public function isChildOf($node) {}

    /**
     * Load node children
     *
     * @param   int  $recursionLevel
     * @return  Node
     */
    public function loadChildren($recursionLevel = 0)
    {
        $this->_tree->load($this, $recursionLevel);
        return $this;
    }

    /**
     * Retrieve node children collection
     *
     * @return NodeCollection
     */
    public function getChildren()
    {
        return $this->_childNodes;
    }

    /**
     * @param array $nodes
     * @return Node[]
     */
    public function getAllChildNodes(&$nodes = [])
    {
        foreach ($this->_childNodes as $node) {
            $nodes[$node->getId()] = $node;
            $node->getAllChildNodes($nodes);
        }
        return $nodes;
    }

    /**
     * @return Node
     */
    public function getLastChild()
    {
        return $this->_childNodes->lastNode();
    }

    /**
     * Add child node
     *
     * @param   Node $node
     * @return  Node
     */
    public function addChild($node)
    {
        $this->_childNodes->add($node);
        return $this;
    }

    /**
     * @param Node|null $prevNode
     * @return $this
     */
    public function appendChild($prevNode = null)
    {
        $this->_tree->appendChild($this, $prevNode);
        return $this;
    }

    /**
     * @param Node $parentNode
     * @param Node|null $prevNode
     * @return $this
     */
    public function moveTo($parentNode, $prevNode = null)
    {
        $this->_tree->moveNodeTo($this, $parentNode, $prevNode);
        return $this;
    }

    /**
     * @param Node $parentNode
     * @param Node|null $prevNode
     * @return $this
     */
    public function copyTo($parentNode, $prevNode = null)
    {
        $this->_tree->copyNodeTo($this, $parentNode, $prevNode);
        return $this;
    }

    /**
     * @param Node $childNode
     * @return $this
     */
    public function removeChild($childNode)
    {
        $this->_childNodes->delete($childNode);
        return $this;
    }

    /**
     * @param array $prevNodes
     * @return array
     */
    public function getPath(&$prevNodes = [])
    {
        if ($this->_parent) {
            $prevNodes[] = $this;
            $this->_parent->getPath($prevNodes);
        }
        return $prevNodes;
    }

    /**
     * @return bool
     */
    public function getIsActive()
    {
        return $this->_getData('is_active');
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->_getData('name');
    }
}

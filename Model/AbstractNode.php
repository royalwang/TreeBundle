<?php

namespace Goutte\TreeBundle\Model;

use Goutte\TreeBundle\Is\Node as NodeInterface;
use Goutte\TreeBundle\Exception\CyclicReferenceException;
use Goutte\TreeBundle\Exception\DisjointNodesException;
use Goutte\TreeBundle\Exception\TreeIntegrityException;

abstract class AbstractNode implements NodeInterface
{
    /**
     * The parent Node, or null if this is the root
     * @var NodeInterface
     */
    protected $parent;

    /**
     * The value held by the Node, may be pretty much anything (operator function, operand, etc.) but must be
     * "stringable" for some Drivers
     * @var mixed
     */
    protected $value;

    /**
     * An array of Nodes that are the direct children of this Node
     * @var NodeInterface[]
     */
    protected $children;


    function __construct()
    {
        $this->parent = null;
        $this->children = array();
    }


    public function isRoot()
    {
        return (null === $this->parent);
    }

    public function isLeaf()
    {
        return (0 === count($this->children));
    }

    public function isChildOf(NodeInterface $node)
    {
        return ($node === $this->parent);
    }

    public function isParentOf(NodeInterface $node)
    {
        return in_array($node, $this->children, true); // strict, or will l∞p
    }

    public function isDescendantOf(NodeInterface $node)
    {
        if ($this->isChildOf($node)) {
            return true;
        } else {
            if (!$this->isRoot()) {
                return $this->getParent()->isDescendantOf($node);
            } else {
                return false;
            }
        }
    }

    public function isAncestorOf(NodeInterface $node)
    {
        return $node->isDescendantOf($this);
    }

    public function getPreviousSibling()
    {
        if ($this->parent) {
            $siblings = $this->parent->getChildren();
            $index = array_search($this, $siblings);

            if (false === $index) throw new TreeIntegrityException();

            if (0 < $index) {
                return $siblings[$index - 1];
            }
        }

        return null;
    }

    public function getNextSibling()
    {
        if ($this->parent) {
            $siblings = $this->parent->getChildren();
            $index = array_search($this, $siblings);

            if (false === $index) throw new TreeIntegrityException();

            if ($index < (count($siblings) - 1)) {
                return $siblings[$index + 1];
            }
        }

        return null;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function setParent($node, $careAboutIntegrity=true)
    {
        if (null !== $node && ($this === $node || $this->isAncestorOf($node))) {
            throw new CyclicReferenceException();
        }

        if ($careAboutIntegrity && !$this->isRoot()) {
            $this->getParent()->removeChild($this);
        }

        $this->parent = $node;
        if ($node && !$node->isParentOf($this)) {
            $node->addChild($this);
        }
    }

    public function getChildren()
    {
        return $this->children;
    }

    public function addChild(NodeInterface $node)
    {
        if ($this === $node || $this->isDescendantOf($node)) {
            throw new CyclicReferenceException();
        }

        if (!$this->isParentOf($node)) {
            if (!$node->isRoot()) {
                $node->getParent()->removeChild($node);
            }
            $this->children[] = $node; // first, or will l∞p
            $node->setParent($this, false);
        }
    }

    public function removeChild(NodeInterface $node) {
        if ($this->isParentOf($node)) {
            unset($this->children[array_search($node, $this->children, true)]);
            $this->children = array_values($this->children);
            $node->setParent(null, false);
        }
    }

    public function getRoot()
    {
        if ($this->isRoot()) {
            return $this;
        } else {
            return $this->getParent()->getRoot();
        }
    }

    /**
     * May be insanely optimized performance-wise, i trust
     * But i kinda like the simplicity of this
     */
    public function getNodesAlongThePathTo(NodeInterface $node)
    {
        if ($this === $node || $this->isParentOf($node) || $this->isChildOf($node)) {
            return array();
        }

        if ($this->isAncestorOf($node)) {
            return array_merge($this->getNodesAlongThePathTo($node->getParent()), array($node->getParent()));
        } else {
            if ($this->isRoot()) {
                // i am root but not your ancestor and you're not me, we are not on the same tree then
                throw new DisjointNodesException("Cannot build path between disjoint nodes.");
            } else {
                return array_merge(array($this->getParent()), $this->getParent()->getNodesAlongThePathTo($node));
            }
        }
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        $this->value = $value;
    }

}
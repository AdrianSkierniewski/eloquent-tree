<?php namespace Gzero\EloquentTree\Model;


use DB;
use Illuminate\Database\Eloquent\Collection;

/**
 * Class Tree
 *
 * @package Gzero\EloquentTree\Model
 */
class Tree extends \Illuminate\Database\Eloquent\Model {

    /**
     * Parent object
     *
     * @var static
     */
    protected $_parent;
    /**
     * Collection for children elements
     *
     * @var \Illuminate\Database\Eloquent\Collection
     */
    public $children;
    /**
     * Database mapping tree fields
     *
     * @var Array
     */
    protected static $_tree_cols = array(
        'path'   => 'path',
        'parent' => 'parent_id',
        'level'  => 'level'
    );

    /**
     * Set node as root node
     *
     * @return $this
     */
    public function setAsRoot()
    {
        $this->_handleNewNodes();
        if (!$this->isRoot()) { // Only if it is not already root
            $oldDescendants                         = $this->_getOldDescendants();
            $this->{$this->getTreeColumn('path')}   = $this->{$this->getKeyName()} . '/';
            $this->{$this->getTreeColumn('parent')} = NULL;
            $this->{$this->getTreeColumn('level')}  = 0;
            $this->save();
            $this->_updateDescendants($this, $oldDescendants);
        }
        return $this;
    }

    /**
     * Set node as child of $parent node
     *
     * @param Tree $parent
     *
     * @return $this
     */
    public function setChildOf(Tree $parent)
    {
        $this->_handleNewNodes();
        if ($parent->{$this->getTreeColumn('path')} != $this->_removeLastNodeFromPath()) { // Only if new parent
            $oldDescendants                         = $this->_getOldDescendants();
            $this->{$this->getTreeColumn('path')}   = $this->_generateNewPath($parent);
            $this->{$this->getTreeColumn('parent')} = $parent->{$this->getKeyName()};
            $this->{$this->getTreeColumn('level')}  = $parent->{$this->getTreeColumn('level')} + 1;
            $this->save();
            $this->_updateDescendants($this, $oldDescendants);
        }
        return $this;
    }

    /**
     * Set node as sibling of $sibling node
     *
     * @param Tree $sibling
     *
     * @return $this
     */
    public function setSiblingOf(Tree $sibling)
    {
        $this->_handleNewNodes();
        if ($sibling->_removeLastNodeFromPath() != $this->_removeLastNodeFromPath()) { // Only if new parent
            $oldDescendants                         = $this->_getOldDescendants();
            $this->{$this->getTreeColumn('path')}   = $sibling->_removeLastNodeFromPath() . $this->{$this->getKeyName()} . '/';
            $this->{$this->getTreeColumn('parent')} = $sibling->{$this->getTreeColumn('parent')};
            $this->{$this->getTreeColumn('level')}  = $sibling->{$this->getTreeColumn('level')};
            $this->save();
            $this->_updateDescendants($this, $oldDescendants);
        }
        return $this;
    }

    /**
     * Check if node is root
     *
     * @return bool
     */
    public function isRoot()
    {
        return (empty($this->{$this->getTreeColumn('parent')})) ? TRUE : FALSE;
    }

    /**
     * Check if node is leaf
     *
     * @return bool
     */
    public function isLeaf()
    {
        return (bool) static::where($this->getTreeColumn('parent'), '=', $this->{$this->getKeyName()})->count();
    }

    /**
     * Get parent to specific node (if exist)
     *
     * @return static
     */
    public function getParent()
    {
        if ($this->{$this->getTreeColumn('parent')}) {
            if (!$this->_parent) {
                return $this->_parent = static::where($this->getKeyName(), '=', $this->{$this->getTreeColumn('parent')})
                    ->first();
            }
            return $this->_parent;
        }
        return NULL;
    }


    /**
     * Find all children for specific node
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function findChildren()
    {
        return $this->hasMany(get_class($this), $this->getTreeColumn('parent'));
    }

    /**
     * Find all descendants for specific node with this node as root
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function findDescendants()
    {
        return static::where($this->getTreeColumn('path'), 'LIKE', $this->{$this->getTreeColumn('path')} . '%')
            ->orderBy($this->getTreeColumn('level'), 'ASC');
    }

    /**
     * Find all ancestors for specific node with this node as leaf
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function findAncestors()
    {
        return static::whereIn($this->getKeyName(), $this->_extractPath())
            ->orderBy($this->getTreeColumn('level'), 'ASC');
    }

    /**
     * Find root for this node
     *
     * @return $this
     */
    public function findRoot()
    {
        if ($this->isRoot()) {
            return $this;
        } else {
            $extractedPath = $this->_extractPath();
            $root_id       = array_shift($extractedPath);
            return static::where($this->getKeyName(), '=', $root_id)->first();
        }
    }

    /**
     * Rebuilds sub-tree for this node
     *
     * @param \Illuminate\Database\Eloquent\Collection $nodes     Nodes from which we are build tree
     * @param string                                   $presenter Optional presenter class
     *
     * @return static Root node
     */
    public function buildTree(Collection $nodes, $presenter = '')
    {
        $count = 0;
        $refs  = array(); // Reference table to store records in the construction of the tree
        foreach ($nodes as &$node) {
            /* @var Tree $node */
            $refs[$node->{$node->getKeyName()}] = & $node; // Adding to ref table (we identify after the id)
            if ($count === 0) { // We use this condition as a factor in building subtrees, root node is always 1
                $root = & $node;
                $count++;
            } else { // This is not a root, so add them to the parent
                $index = $node->{$this->getTreeColumn('parent')};
                if (empty($refs[$index]) and $index == $this->id) { // If Parent not exist but is current node
                    $refs[$index] = & $this; // Current node is root
                    $root         = $this;
                }
                if (isset($presenter) and class_exists($presenter)) {
                    $refs[$index]->_addChildToCollection(new $presenter($node));
                } else {
                    $refs[$index]->_addChildToCollection($node);
                }
            }
        }
        return (!isset($root)) ? FALSE : $root;
    }

    /**
     * Displays a tree as html list
     *
     * @param string $field node property to display
     * @param Tree   $node  Optional from node
     *
     * @return string
     */
    public function renderTree($field, Tree $node = NULL)
    {
        $output = '';
        if (!$node) {
            $node = $this;
            $output .= '<ul class="level-' . $node->level . '">';
            $output .= '<li>';
            $output .= '<span>' . $node->{$field} . '</span>';
            $end = '</li></ul>';
        }
        if (count($node->children)) {
            $output .= '<ul class="level-' . ($node->level + 1) . '">';
            foreach ($node->children as $child) {
                $output .= '<li>';
                $output .= '<span>' . $child->{$field} . '</span>';
                $output .= $this->renderTree($field, $child);
            }
            $output .= '</ul>';
        } else {
            $output .= '</li>';
        }
        if (isset($end)) {
            $output .= $end;
        }
        return $output;
    }

    //---------------------------------------------------------------------------------------------------------------
    // START                                 STATIC
    //---------------------------------------------------------------------------------------------------------------

    protected static function boot()
    {
        parent::boot();
        static::observe(new Observer());
    }

    /**
     * ONLY FOR TESTS!
     * Metod resets static::$booted
     */
    public static function __resetBootedStaticProperty()
    {
        static::$booted = array();
    }

    /**
     * Get tree column for actual model
     *
     * @param string $name column name [path|parent|level]
     *
     * @return null
     */
    public static function getTreeColumn($name)
    {
        if (!empty(static::$_tree_cols[$name])) {
            return static::$_tree_cols[$name];
        }
        return NULL;
    }

    /**
     * Gets all root nodes
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function getRoots()
    {
        return static::where(static::getTreeColumn('parent'), 'IS', DB::raw('NULL'));
    }


    //---------------------------------------------------------------------------------------------------------------
    // END                                  STATIC
    //---------------------------------------------------------------------------------------------------------------

    //---------------------------------------------------------------------------------------------------------------
    // START                         PROTECTED/PRIVATE
    //---------------------------------------------------------------------------------------------------------------

    /**
     * Creating node if not exist
     */
    protected function _handleNewNodes()
    {
        if (!$this->exists) {
            $this->save();
        }
    }

    /**
     * Extract path to array
     *
     * @return array
     */
    protected function _extractPath()
    {
        $path = explode('/', $this->{$this->getTreeColumn('path')});
        array_pop($path); // Remove last empty element
        return $path;
    }

    /**
     * Removing last node id from path and returns it
     *
     * @return mixed Node path
     */
    protected function _removeLastNodeFromPath()
    {
        return preg_replace('/\d\/$/', '', $this->{$this->getTreeColumn('path')});
    }

    /**
     * Generating new path based on parent path
     *
     * @param Tree $parent Parent node
     *
     * @return string New path
     */
    protected function _generateNewPath(Tree $parent)
    {
        return $parent->{$this->getTreeColumn('path')} . $this->{$this->getKeyName()} . '/';
    }

    /**
     * Adds children for this node while building the tree structure in PHP
     *
     * @param Tree $child Child node
     */
    protected function _addChildToCollection(Tree &$child)
    {
        if (empty($this->children)) {
            $this->children = new Collection();
        }
        $this->children->add($child);
    }

    /**
     * Gets old descendants before modify parent
     *
     * @return Collection|static[]
     */
    protected function _getOldDescendants()
    {
        $collection = $this->findDescendants()->get();
        $collection->shift(); // Removing current node from update
        return $collection;
    }

    /**
     * Recursive node updating
     *
     * @param Tree       $node           Updated node
     * @param Collection $oldDescendants Old descendants collection (just before modify parent)
     */

    protected function _updateDescendants(Tree $node, $oldDescendants)
    {
        $refs                  = array();
        $refs[$node->getKey()] = & $node; // Updated node
        foreach ($oldDescendants as &$child) {
            $refs[$child->getKey()] = $child;
            $parent_id              = $child->{$this->getTreeColumn('parent')};
            if (!empty($refs[$parent_id])) {
                if ($refs[$parent_id]->path != $refs[$child->getKey()]->_removeLastNodeFromPath()) {
                    $refs[$child->getKey()]->level = $refs[$parent_id]->level + 1; // New level
                    $refs[$child->getKey()]->path  = $refs[$parent_id]->path . $child->getKey() . '/'; // New path
                    DB::table($this->getTable())
                        ->where($this->getKeyName(), '=', $child->getKey())
                        ->update(
                            array(
                                'level' => $refs[$child->getKey()]->level,
                                'path'  => $refs[$child->getKey()]->path
                            )
                        );
                }
            }
        }
    }

    //---------------------------------------------------------------------------------------------------------------
    // END                          PROTECTED/PRIVATE
    //---------------------------------------------------------------------------------------------------------------

}

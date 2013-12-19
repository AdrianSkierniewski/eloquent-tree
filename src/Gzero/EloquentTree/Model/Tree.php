<?php namespace Gzero\EloquentTree\Model;


class Tree extends \Illuminate\Database\Eloquent\Model {

    /**
     * Parent object
     *
     * @var static
     */
    protected $_parent;
    /**
     * Array for children elements
     *
     * @var array
     */
    protected $_children = array();
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

    protected static function boot()
    {
        parent::boot();
        static::observe(new Observer());
    }

    /**
     * Set node as root node
     *
     * @return $this
     */
    public function setAsRoot()
    {
        $this->_handleNewNodes();
        $this->{$this->getTreeColumn('path')}   = $this->{$this->getKeyName()} . '/';
        $this->{$this->getTreeColumn('parent')} = NULL;
        $this->{$this->getTreeColumn('level')}  = 0;
        $this->save();
        $this->_updateChildren($this);
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
        $this->{$this->getTreeColumn('path')}   = $parent->{$this->getTreeColumn('path')} . $this->{$this->getKeyName()} . '/';
        $this->{$this->getTreeColumn('parent')} = $parent->{$this->getKeyName()};
        $this->{$this->getTreeColumn('level')}  = $parent->{$this->getTreeColumn('level')} + 1;
        $this->save();
        $this->_updateChildren($this);
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
        $this->{$this->getTreeColumn('path')}   =
            preg_replace('/\d\/$/', '', $sibling->{$this->getTreeColumn('path')}) . $this->{$this->getKeyName()} . '/';
        $this->{$this->getTreeColumn('parent')} = $sibling->{$this->getTreeColumn('parent')};
        $this->{$this->getTreeColumn('level')}  = $sibling->{$this->getTreeColumn('level')};
        $this->save();
        $this->_updateChildren($this);
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
     * Get all children for specific node
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getChildren()
    {
        return static::where($this->getTreeColumn('parent'), '=', $this->{$this->getKeyName()});
    }

    /**
     * Get all descendants for specific node
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getDescendants()
    {
        return static::where($this->getTreeColumn('path'), 'LIKE', $this->{$this->getTreeColumn('path')} . '%')
            ->where($this->getKeyName(), '!=', $this->{$this->getKeyName()})
            ->orderBy($this->getTreeColumn('level'), 'ASC');
    }

    /**
     * Get all ancestors for specific node
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getAncestors()
    {
        return static::whereIn($this->getKeyName(), $this->_extractPath())
            ->where($this->getKeyName(), '!=', $this->{$this->getKeyName()})
            ->orderBy($this->getTreeColumn('level'), 'ASC');
    }

    /**
     * Get root for this node
     *
     * @return $this
     */
    public function getRoot()
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
     * Gets all root nodes
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected static function getRoots()
    {
        return static::where(static::getTreeColumn('parent'), 'IS', DB::raw('NULL'));
    }

    /**
     * Get all nodes in tree (with root node)
     *
     * @param int $root_id Root node id
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function fetchTree($root_id)
    {
        return static::where(static::getTreeColumn('path'), 'LIKE', "$root_id/%")
            ->orderBy(static::getTreeColumn('level'), 'ASC');
    }

    /**
     * Rebuilds the tree on the side of Php
     *
     * @param array  $records
     * @param string $presenter Optional presenter class
     *
     * @return bool
     * @throws \Exception
     */
    public static function buildTree(array $records, $presenter = '')
    {
        $count = 0;
        $refs  = array(); // Reference table to store records in the construction of the tree
        foreach ($records as &$record) {
            $refs[$record->{static::getKeyName()}] = & $record; // Adding to ref table (we identify after the id)
            if ($count === 0) { // We use this condition as a factor in building subtrees, root node is always 1
                $root = & $record;
                $count++;
            } else { // This is not a root, so add them to the parent
                if (!empty($presenter)) {
                    if (class_exists($presenter)) {
                        $refs[$record->{static::getTreeColumn('parent')}]->children[] = new $presenter($record);
                    } else {
                        throw new \Exception("No presenter class found: $presenter");
                    }
                } else {
                    $refs[$record->{static::getTreeColumn('parent')}]->children[] = $record;
                }
            }
        }
        return (!isset($root)) ? FALSE : $root;
    }

    //-----------------------------------------------------------------------------------------------
    // START                         PROTECTED/PRIVATE
    //-----------------------------------------------------------------------------------------------

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
     * Recursive node updating
     *
     * @param Tree $parent
     */
    protected function _updateChildren(Tree $parent)
    {
        foreach ($parent->getChildren()->get() as $child) {
            $child->{$this->getTreeColumn('level')} = $parent->{$this->getTreeColumn('level')} + 1;
            $child->{$this->getTreeColumn('path')}  = $parent->{$this->getTreeColumn('path')} .
                $child->{$this->getKeyName()} . '/';
            $child->save();
            $this->_updateChildren($child);
        }
    }

}

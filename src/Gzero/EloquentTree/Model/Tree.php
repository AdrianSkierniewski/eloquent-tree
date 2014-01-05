<?php namespace Gzero\EloquentTree\Model;


use DB;
use Gzero\EloquentTree\Model\Exception\SelfConnectionException;
use Illuminate\Database\Eloquent\Collection;


/**
 * Class Tree
 *
 * This class represents abstract tree model for inheritance
 *
 * @author  Adrian Skierniewski <adrian.skierniewski@gmail.com>
 * @package Gzero\EloquentTree\Model
 */
abstract class Tree extends \Eloquent {

    /**
     * Parent object
     *
     * @var static
     */
    protected $_parent;

    /**
     * Is leaf
     *
     * @var bool
     */
    protected $_isLeaf;

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
     * @inheritdoc
     */
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
        $this->_addTreeEvents(); // Adding tree events
    }

    /**
     * Set node as root node
     *
     * @return $this
     */
    public function setAsRoot()
    {
        $this->_handleNewNodes();
        if (!$this->isRoot()) { // Only if it is not already root
            if ($this->fireModelEvent('updatingParent') === FALSE) {
                return $this;
            }
            $oldDescendants                         = $this->_getOldDescendants();
            $this->{$this->getTreeColumn('path')}   = $this->getKey() . '/';
            $this->{$this->getTreeColumn('parent')} = NULL;
            $this->{$this->getTreeColumn('level')}  = 0;
            $this->save();
            $this->fireModelEvent('updatedParent', FALSE);
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
        if ($this->validateSetChildOf($parent)) {
            if ($this->fireModelEvent('updatingParent') === FALSE) {
                return $this;
            }
            $oldDescendants                         = $this->_getOldDescendants();
            $this->{$this->getTreeColumn('path')}   = $this->_generateNewPath($parent);
            $this->{$this->getTreeColumn('parent')} = $parent->getKey();
            $this->{$this->getTreeColumn('level')}  = $parent->{$this->getTreeColumn('level')} + 1;
            $this->save();
            $this->fireModelEvent('updatedParent', FALSE);
            $this->_updateDescendants($this, $oldDescendants);
        }
        return $this;
    }

    /**
     * Validate if parent change and prevent self connection
     *
     * @param Tree $parent New parent node
     *
     * @return bool
     * @throws Exception\SelfConnectionException
     */
    public function validateSetChildOf(Tree $parent)
    {
        if ($parent->getKey() == $this->getKey()) {
            throw new SelfConnectionException();
        }
        if ($parent->{$this->getTreeColumn('path')} != $this->_removeLastNodeFromPath()) { // Only if new parent
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Set node as sibling of $sibling node
     *
     * @param Tree $sibling New sibling node
     *
     * @return $this
     */
    public function setSiblingOf(Tree $sibling)
    {
        $this->_handleNewNodes();
        if ($this->validateSetSiblingOf($sibling)) {
            if ($this->fireModelEvent('updatingParent') === FALSE) {
                return $this;
            }
            $oldDescendants                         = $this->_getOldDescendants();
            $this->{$this->getTreeColumn('path')}   = $sibling->_removeLastNodeFromPath() . $this->getKey() . '/';
            $this->{$this->getTreeColumn('parent')} = $sibling->{$this->getTreeColumn('parent')};
            $this->{$this->getTreeColumn('level')}  = $sibling->{$this->getTreeColumn('level')};
            $this->save();
            $this->fireModelEvent('updatedParent', FALSE);
            $this->_updateDescendants($this, $oldDescendants);
        }
        return $this;
    }

    /**
     * Validate if parent change and prevent self connection
     *
     * @param Tree $sibling New sibling node
     *
     * @return bool
     */
    public function validateSetSiblingOf(Tree $sibling)
    {
        if ($sibling->_removeLastNodeFromPath() != $this->_removeLastNodeFromPath()) { // Only if new parent and != self
            return TRUE;
        }
        return FALSE;
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
        if (!isset($this->_isLeaf)) {
            return $this->_isLeaf = !(bool) static::where($this->getTreeColumn('parent'), '=', $this->getKey())->count();
        }
        return $this->_isLeaf;
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
            $refs[$node->getKey()] = & $node; // Adding to ref table (we identify after the id)
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
     * Displays a tree as html
     * Rendering function accept {sub-tree} tag, represents next tree level
     *
     * EXAMPLE:
     * $root->render(
     *    'ul',
     *    function ($node) {
     *       return '<li>' . $node->title . '{sub-tree}</li>';
     *   },
     *   TRUE
     * );
     *
     * @param string   $tag         HTML tag for level section
     * @param callable $render      Rendering function
     * @param bool     $displayRoot Is the root will be displayed
     *
     * @return string
     */
    public function render($tag, Callable $render, $displayRoot = TRUE)
    {
        $out = '';
        if ($displayRoot) {
            $out .= '<' . $tag . ' class="tree tree-level-' . $this->{$this->getTreeColumn('level')} . '">';
            $root      = $render($this);
            $nextLevel = $this->_renderRecursiveTree($this, $tag, $render);
            $out .= preg_replace('/{sub-tree}/', $nextLevel, $root);
            $out .= '</' . $tag . '>';
        } else {
            $out = $this->_renderRecursiveTree($this, $tag, $render);
        }
        return $out;
    }

    //---------------------------------------------------------------------------------------------------------------
    // START                                 STATIC
    //---------------------------------------------------------------------------------------------------------------

    /**
     * Adds observer inheritance
     */
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
     * Map array to tree structure in database
     * You must set $fillable attribute to use this function
     *
     * Example array:
     * array(
     *       'title'    => 'root',
     *       'children' => array(
     *                   array('title' => 'node1'),
     *                   array('title' => 'node2')
     *        )
     * );
     *
     * @param array $map Nodes recursive array
     */
    public static function mapArray(Array $map)
    {
        foreach ($map as $item) {
            $root = new static($item);
            $root->setAsRoot();
            if (isset($item['children'])) {
                static::mapDescendantsArray($root, $item['children']);
            }
            array(
                'title'    => 'root',
                'children' => array(
                    array('title' => 'node1'),
                    array('title' => 'node2')
                )
            );
        }

    }

    /**
     * Map array as descendants nodes in database to specific parent node
     * You must set $fillable attribute to use this function
     *
     * @param Tree  $parent Parent node
     * @param array $map    Nodes recursive array
     */
    public static function mapDescendantsArray(Tree $parent, Array $map)
    {
        foreach ($map as $item) {
            $node = new static($item);
            $node->setChildOf($parent);
            if (isset($item['children'])) {
                static::mapDescendantsArray($node, $item['children']);
            }
        }

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
        return static::whereNull(static::getTreeColumn('parent'));
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
     * Adds tree specific events
     *
     * @return array
     */
    protected function _addTreeEvents()
    {
        $this->observables = array_merge(
            array(
                'updatedParent',
                'updatingParent',
                'updatedDescendants'
            ),
            $this->observables
        );
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
        return $parent->{$this->getTreeColumn('path')} . $this->getKey() . '/';
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
     * Recursive render descendants
     *
     * @param          $node
     * @param          $tag
     * @param callable $render
     *
     * @return string
     */
    protected function _renderRecursiveTree($node, $tag, Callable $render)
    {
        $out = '';
        $out .= '<' . $tag . ' class="tree tree-level-' . ($node->{$node->getTreeColumn('level')} + 1) . '">';
        foreach ($node->children as $child) {
            if (!empty($child->children)) {
                $level     = $render($child);
                $nextLevel = $this->_renderRecursiveTree($child, $tag, $render);
                $out .= preg_replace('/{sub-tree}/', $nextLevel, $level);
            } else {
                $out .= preg_replace('/{sub-tree}/', '', $render($child));
            }
        }
        return $out . '</' . $tag . '>';
    }

    /**
     * Updating descendants nodes
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
                        ->where($child->getKeyName(), '=', $child->getKey())
                        ->update(
                            array(
                                'level' => $refs[$child->getKey()]->level,
                                'path'  => $refs[$child->getKey()]->path
                            )
                        );
                }
            }
        }
        $this->fireModelEvent('updatedDescendants', FALSE);
    }

    //---------------------------------------------------------------------------------------------------------------
    // END                          PROTECTED/PRIVATE
    //---------------------------------------------------------------------------------------------------------------

}

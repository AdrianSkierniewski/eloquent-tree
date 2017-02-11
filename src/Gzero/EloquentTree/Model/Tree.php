<?php namespace Gzero\EloquentTree\Model;


use DB;
use Gzero\EloquentTree\Model\Exception\MissingParentException;
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
     * Database tree fields
     *
     * @var array
     */
    protected static $treeColumns = [
        'path'   => 'path',
        'parent' => 'parent_id',
        'level'  => 'level'
    ];

    /**
     * @inheritdoc
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->addTreeEvents(); // Adding tree events
    }

    /**
     * Set node as root node
     *
     * @return $this
     */
    public function setAsRoot()
    {
        $this->handleNewNodes();
        if (!$this->isRoot()) { // Only if it is not already root
            if ($this->fireModelEvent('updatingParent') === false) {
                return $this;
            }
            $oldDescendants                         = $this->getOldDescendants();
            $this->{$this->getTreeColumn('path')}   = $this->getKey() . '/';
            $this->{$this->getTreeColumn('parent')} = null;
            $this->setRelation('parent', null);
            $this->{$this->getTreeColumn('level')} = 0;
            $this->save();
            $this->fireModelEvent('updatedParent', false);
            $this->updateDescendants($this, $oldDescendants);
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
        $this->handleNewNodes();
        if ($this->validateSetChildOf($parent)) {
            if ($this->fireModelEvent('updatingParent') === false) {
                return $this;
            }
            $oldDescendants                         = $this->getOldDescendants();
            $this->{$this->getTreeColumn('path')}   = $this->generateNewPath($parent);
            $this->{$this->getTreeColumn('parent')} = $parent->getKey();
            $this->{$this->getTreeColumn('level')}  = $parent->{$this->getTreeColumn('level')} + 1;
            $this->save();
            $this->fireModelEvent('updatedParent', false);
            $this->updateDescendants($this, $oldDescendants);
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
        if ($parent->{$this->getTreeColumn('path')} != $this->removeLastNodeFromPath()) { // Only if new parent
            return true;
        }
        return false;
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
        $this->handleNewNodes();
        if ($this->validateSetSiblingOf($sibling)) {
            if ($this->fireModelEvent('updatingParent') === false) {
                return $this;
            }
            $oldDescendants                         = $this->getOldDescendants();
            $this->{$this->getTreeColumn('path')}   = $sibling->removeLastNodeFromPath() . $this->getKey() . '/';
            $this->{$this->getTreeColumn('parent')} = $sibling->{$this->getTreeColumn('parent')};
            $this->{$this->getTreeColumn('level')}  = $sibling->{$this->getTreeColumn('level')};
            $this->save();
            $this->fireModelEvent('updatedParent', false);
            $this->updateDescendants($this, $oldDescendants);
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
        if ($sibling->removeLastNodeFromPath() != $this->removeLastNodeFromPath()) { // Only if new parent and != self
            return true;
        }
        return false;
    }

    /**
     * Check if node is root
     * This function check foreign key field
     *
     * @return bool
     */
    public function isRoot()
    {
        return (empty($this->{$this->getTreeColumn('parent')})) ? true : false;
    }

    /**
     * Check if node is sibling for passed node
     *
     * @param Tree $node
     *
     * @return bool
     */
    public function isSibling(Tree $node)
    {
        return $this->{$this->getTreeColumn('parent')} === $node->{$this->getTreeColumn('parent')};
    }

    /**
     * Get parent to specific node (if exist)
     *
     * @return static
     */
    public function parent()
    {
        return $this->belongsTo(get_class($this), $this->getTreeColumn('parent'), $this->getKeyName());
    }

    /**
     * Get children to specific node (if exist)
     *
     * @return static
     */
    public function children()
    {
        return $this->hasMany(get_class($this), $this->getTreeColumn('parent'), $this->getKeyName());
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
        return static::whereIn($this->getKeyName(), $this->extractPath())
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
            $extractedPath = $this->extractPath();
            $root_id       = array_shift($extractedPath);
            return static::where($this->getKeyName(), '=', $root_id)->first();
        }
    }

    /**
     * Rebuilds trees from passed nodes
     *
     * @param Collection $nodes  Nodes from which we are build tree
     * @param bool       $strict If we want to make sure that there are no orphan nodes
     *
     * @return static Root node
     * @throws MissingParentException
     */
    public function buildTree(Collection $nodes, $strict = true)
    {
        $refs  = []; // Reference table to store records in the construction of the tree
        $count = 0;
        $roots = new Collection();
        foreach ($nodes as &$node) {
            /* @var Tree $node */
            $node->initChildrenRelation(); // We need to init relation to avoid LAZY LOADING in future
            $refs[$node->getKey()] = &$node; // Adding to ref table (we identify after the id)
            if ($count === 0) {
                $roots->add($node);
                $count++;
            } else {
                if ($this->siblingOfRoot($node, $roots)) { // We use this condition as a factor in building subtrees
                    $roots->add($node);
                } else { // This is not a root, so add them to the parent
                    $index = $node->{$this->getTreeColumn('parent')};
                    if (!empty($refs[$index])) { // We should already have parent for our node added to refs array
                        $refs[$index]->addChildToCollection($node);
                    } else {
                        if ($strict) { // We don't want to ignore orphan nodes
                            throw new MissingParentException();
                        }
                    }
                }
            }
        }

        if (!empty($roots)) {
            if (count($roots) > 1) {
                return $roots;
            } else {
                return $roots->first();
            }
        } else {
            return false;
        }
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
    public function render($tag, Callable $render, $displayRoot = true)
    {
        $out = '';
        if ($displayRoot) {
            $out .= '<' . $tag . ' class="tree tree-level-' . $this->{$this->getTreeColumn('level')} . '">';
            $root      = $render($this);
            $nextLevel = $this->renderRecursiveTree($this, $tag, $render);
            $out .= preg_replace('/{sub-tree}/', $nextLevel, $root);
            $out .= '</' . $tag . '>';
        } else {
            $out = $this->renderRecursiveTree($this, $tag, $render);
        }
        return $out;
    }


    /**
     * Determine if we've already loaded the children
     * Used to prevent lazy loading on children
     *
     * @return bool
     */
    public function isChildrenLoaded()
    {
        return isset($this->relations['children']);
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
     * Gets all root nodes
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function getRoots()
    {
        return static::whereNull(static::getTreeColumn('parent'));
    }

 /**
     * Gets all leaf nodes
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function getLeafs()
    {
        $parents  = static::select('parent_id')->whereNotNull('parent_id')->distinct()->get()->pluck('parent_id')->all();
        return static::wherenotin('id',$parents);
    }

    /**
     * @param null $name
     *
     * @throws \Exception
     */
    public static function getTreeColumn($name = null)
    {
        if (empty($name)) {
            return static::$treeColumns;
        } elseif (!empty(static::$treeColumns[$name])) {
            return static::$treeColumns[$name];
        }
        throw new \Exception('Tree column: ' . $name . ' undefined');
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

    //---------------------------------------------------------------------------------------------------------------
    // END                                  STATIC
    //---------------------------------------------------------------------------------------------------------------

    //---------------------------------------------------------------------------------------------------------------
    // START                         PROTECTED/PRIVATE
    //---------------------------------------------------------------------------------------------------------------

    /**
     * Creating node if not exist
     */
    protected function handleNewNodes()
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
    protected function addTreeEvents()
    {
        $this->observables = array_merge(
            [
                'updatedParent',
                'updatingParent',
                'updatedDescendants'
            ],
            $this->observables
        );
    }

    /**
     * Extract path to array
     *
     * @return array
     */
    protected function extractPath()
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
    protected function removeLastNodeFromPath()
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
    protected function generateNewPath(Tree $parent)
    {
        return $parent->{$this->getTreeColumn('path')} . $this->getKey() . '/';
    }

    /**
     * Adds children for this node while building the tree structure in PHP
     *
     * @param Tree $child Child node
     */
    protected function addChildToCollection(&$child)
    {
        $this->setRelation('children', $this->getRelation('children')->add($child));
    }

    /**
     * Gets old descendants before modify parent
     *
     * @return Collection|static[]
     */
    protected function getOldDescendants()
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
    protected function renderRecursiveTree($node, $tag, Callable $render)
    {
        $out = '';
        $out .= '<' . $tag . ' class="tree tree-level-' . ($node->{$node->getTreeColumn('level')} + 1) . '">';
        foreach ($node->children as $child) {
            if (!empty($child->children)) {
                $level     = $render($child);
                $nextLevel = $this->renderRecursiveTree($child, $tag, $render);
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

    protected function updateDescendants(Tree $node, $oldDescendants)
    {
        $refs                  = [];
        $refs[$node->getKey()] = &$node; // Updated node
        foreach ($oldDescendants as &$child) {
            $refs[$child->getKey()] = $child;
            $parent_id              = $child->{$this->getTreeColumn('parent')};
            if (!empty($refs[$parent_id])) {
                if ($refs[$parent_id]->path != $refs[$child->getKey()]->removeLastNodeFromPath()) {
                    $refs[$child->getKey()]->level = $refs[$parent_id]->level + 1; // New level
                    $refs[$child->getKey()]->path  = $refs[$parent_id]->path . $child->getKey() . '/'; // New path
                    DB::table($this->getTable())
                        ->where($child->getKeyName(), '=', $child->getKey())
                        ->update(
                            [
                                $this->getTreeColumn('level') => $refs[$child->getKey()]->level,
                                $this->getTreeColumn('path')  => $refs[$child->getKey()]->path
                            ]
                        );
                }
            }
        }
        $this->fireModelEvent('updatedDescendants', false);
    }

    /**
     * Check if node is sibling for roots in collection
     *
     * @param Tree       $node  Tree node
     * @param Collection $roots Collection of roots
     *
     * @return bool
     */
    private function siblingOfRoot(Tree $node, Collection $roots)
    {
        return (bool) $roots->filter(
            function ($item) use ($node) {
                return $node->isSibling($item);
            }
        )->first();
    }

    /**
     * Init children relation to avoid empty LAZY LOADING
     */
    protected function initChildrenRelation()
    {
        $relations = $this->getRelations();
        if (empty($relations['children'])) {
            $this->setRelation('children', new Collection());
        }
    }

    //---------------------------------------------------------------------------------------------------------------
    // END                          PROTECTED/PRIVATE
    //---------------------------------------------------------------------------------------------------------------

}

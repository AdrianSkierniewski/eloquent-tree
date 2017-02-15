<?php
spl_autoload_register( // Autoload because we're using \Eloquent alias provided by Orchestra
    function ($class) {
        require_once 'Model/Tree.php';
    }
);

class Test extends Orchestra\Testbench\TestCase {


    /**
     * Default preparation for each test
     */
    public function setUp()
    {

        parent::setUp();
        $this->loadMigrationsFrom(
            [
                '--database' => 'testbench',
                '--realpath' => realpath(__DIR__ . '/migrations'),
            ]
        );
    }

    public function tearDown()
    {
        parent::tearDown();
        Tree::__resetBootedStaticProperty();
    }

    /**
     * New node saved as root
     *
     * @test
     */
    public function can_create_new_node_as_root()
    {
        $root = new Tree();
        $this->assertNotEmpty($root->setAsRoot()); // Should return this
        $this->assertTrue($root->isRoot(), 'Assert root node');
        // Assert path and level set properly
        $this->assertEquals($root->id, 1);
        $this->assertEquals($root->path, $root->id . '/');
        $this->assertEquals($root->level, 0);
        $this->assertEmpty($root->parent, 'Expected no parent');

        $root2 = new Tree();
        $this->assertNotEmpty($root2->save()); // Standard save - we expect root node
        $this->assertTrue($root2->isRoot(), 'Assert root node');
        // Assert path, level and parent set properly
        $this->assertEquals($root2->id, 2);
        $this->assertEquals($root2->path, $root2->id . '/');
        $this->assertEquals($root2->level, 0);
        $this->assertEmpty($root2->parent, 'Expected no parent');
    }

    /**
     * New node saved as child
     *
     * @test
     */
    public function can_create_new_node_as_child()
    {
        $root  = with(new Tree())->setAsRoot();
        $child = with(new Tree())->setChildOf($root);

        // Assert path, level and parent set properly
        $this->assertEquals($root->path . $child->id . '/', $child->path, 'Wrong children path!');
        $this->assertEquals($root->level + 1, $child->level, 'Wrong children level!');
        $this->assertEquals($root->id, $child->parent_id, 'Wrong children parent!');
        $this->assertEquals($root->path, $child->parent->path, 'Wrong children parent!');
    }

    /**
     * New node saved as sibling
     *
     * @test
     */
    public function can_create_new_node_as_sibling()
    {
        $sibling = with(new Tree())->setAsRoot();
        $node    = with(new Tree())->setSiblingOf($sibling);

        // Assert path, level and parent set properly
        $this->assertEquals($node->id . '/', $node->path, 'Wrong sibling path!');
        $this->assertEquals($sibling->level, $node->level, 'Wrong sibling level!');
        $this->assertEquals($sibling->parent_id, $node->parent_id, 'Wrong sibling parent!');
        $this->assertEquals($sibling->parent, $node->parent, 'Wrong sibling parent!');
    }

    /**
     * Change existing node to root node
     *
     * @test
     */
    public function testChangeNodeToRoot()
    {
        $root = with(new Tree())->setAsRoot();
        $node = with(new Tree())->setChildOf($root);

        $this->assertEquals($root->path, $node->parent->path);
        $node->setAsRoot(); // Change node to became root
        $this->assertEmpty($node->parent, 'New root expected to have no parent');
        $this->assertEquals($node->level, 0, 'Root node should have level set to 0');
        $this->assertEquals($node->id . '/', $node->path, ' Root path should look like - root_id/');
        $this->assertEquals($node->parent_id, null, 'New root parent_id expected to be NULL');
        $this->assertEquals($node->parent, null, 'New root parent relation should be set to NULL');
    }

    /**
     * Get all children for specific node
     *
     * @test
     */
    public function can_find_children_for_node()
    {
        $root      = with(new Tree())->setAsRoot();
        $node      = with(new Tree())->setChildOf($root);
        $node2     = with(new Tree())->setChildOf($root);
        $correct[] = $node;
        $correct[] = $node2;

        // Getting all children for this root
        foreach ($root->children as $key => $child) {
            $this->assertEquals($correct[$key]->path, $child->path);    // Child path same as returned from children relation
            $this->assertEquals($correct[$key]->parent, $child->parent);// Child parent same as returned from children relation
        }

        // children becomes root node
        $newRoot = $node->setAsRoot();
        $this->assertTrue($newRoot->isRoot(), 'Assert root node');
        $this->assertEmpty($newRoot->parent, 'Expected no parent');

        // Modify correct pattern
        $correct[0] = $node2;
        unset($correct[1]);

        // Getting all children for old root
        foreach ($root->children()->get() as $key => $child) { // We must refresh children relation
            $this->assertEquals($correct[$key]->path, $child->path);    // Child path same as returned from children relation
            $this->assertEquals($correct[$key]->parent, $child->parent);// Child parent same as returned from children relation
        }
        $this->assertEquals([], $newRoot->children->toArray(), 'New Root expects to have no children');
    }

    /**
     * Get all ancestors for specific node
     *
     * @test
     */
    public function can_find_ancestors_for_node()
    {
        extract($this->_createSampleTree()); // Build sample data
        $this->assertEquals($child1_1->id, $child1_1_1->parent->id, 'Node expects to have a specific parent');
        $correct = [
            $root,
            $child1,
            $child1_1,
            $child1_1_1
        ];
        foreach ($child1_1_1->findAncestors()->get() as $key => $ancestor) {
            $this->assertEquals($correct[$key]->path, $ancestor->path);    // Ancestor path same as returned from findAncestors()
            $this->assertEquals($correct[$key]->parent, $ancestor->parent);// Ancestor path same as returned from findAncestors()
        }
    }

    /**
     * Get all descendants for specific node
     *
     * @test
     */
    public function can_get_all_descendants_for_node()
    {
        extract($this->_createSampleTree());
        $this->assertEquals(0, $child1_1_1->children()->count(), 'Node expected to be leaf');
        $correct = [
            $child1,
            $child1_1,
            $child1_1_1
        ];
        foreach ($child1->findDescendants()->get() as $key => $descendant) {
            $this->assertEquals($correct[$key]->path, $descendant->path);    // Same as returned from findDescendants()
            $this->assertEquals($correct[$key]->parent, $descendant->parent);// Same as returned from findDescendants()
        }
    }

    /**
     * Get root for specific node
     *
     * @test
     */
    public function cat_find_root_node()
    {
        extract($this->_createSampleTree());
        $this->assertEquals($root->toArray(), $child1_1_1->findRoot()->toArray(), 'Expected root node');
    }

    /**
     * Recursive node updating
     *
     * @test
     */
    public function can_move_sub_tree()
    {
        extract($this->_createAdvancedTree());
        $this->assertEquals($child2_2->toArray(), $child2_2_1->parent->toArray(), 'Node expects to have a specific parent');
        $this->assertEquals($child2_2_1->level, 3, 'Node expects to have a specific level');

        // Move whole subtree
        $child2_2->setAsRoot();
        $this->assertEquals(0, $child2_2->level, 'Node expects to have a specific level');
        $this->assertEquals(1, with(Tree::find($child2_2_1->id))->level, 'Node expects to have a specific level');
        $this->assertEquals(1, with(Tree::find($child2_2_2->id))->level, 'Node expects to have a specific level');
        $this->assertEquals(2, with(Tree::find($child2_2_2_1->id))->level, 'Node expects to have a specific level');
        $this->assertEquals(
            $child2_2->id,
            preg_replace('/\/.+/', '', with(Tree::find($child2_2_2_1->id))->path),
            'Node expects to have a specific path'
        );
    }


    /**
     * Tree building on PHP side
     *
     * @test
     */
    public function can_build_complete_tree()
    {
        extract($this->_createAdvancedTree());
        $treeRoot = $root->buildTree($root->findDescendants()->get());
        $this->assertEquals($root->id, $treeRoot->id, 'Specific child expected');
        $this->assertEquals($treeRoot->children[0]->id, $child1->id, 'Specific child expected');
        $this->assertEquals($treeRoot->children[0]->children[0]->id, $child1_1->id, 'Specific child expected');
        $this->assertEquals($treeRoot->children[0]->children[0]->children[0]->id, $child1_1_1->id, 'Specific child expected');
        $this->assertEquals($treeRoot->children[1]->id, $child2->id, 'Specific child expected');
        $this->assertEquals($treeRoot->children[1]->children[1]->children[0]->id, $child2_2_1->id, 'Specific child expected');
    }

    /**
     * Tree building on PHP side
     *
     * @test
     */
    public function can_build_sub_tree()
    {
        extract($this->_createAdvancedTree());
        $treeRoot = $child1->buildTree($child1->findDescendants()->get());
        $this->assertEquals($child1->id, $treeRoot->id, 'Specific child expected');
        $this->assertEquals($treeRoot->children[0]->id, $child1_1->id, 'Specific child expected');
        $this->assertEquals($treeRoot->children[0]->children[0]->id, $child1_1_1->id, 'Specific child expected');
    }

    /**
     * Tree building on PHP side
     *
     * @test
     */
    public function can_build_complete_trees()
    {
        extract($this->_createAdvancedTrees());
        $nodes     = $root->orderBy('level', 'ASC')->get(); // We get all nodes
        $treeRoots = $root->buildTree($nodes); // And we should build two trees
        $this->assertEquals(count($treeRoots), 2, 'We shoul have exactly 2 roots');

        $this->assertEquals($treeRoots[0]->children[0]->id, $child1->id, 'Specific child expected');
        $this->assertEquals($treeRoots[0]->children[0]->children[0]->id, $child1_1->id, 'Specific child expected');
        $this->assertEquals($treeRoots[0]->children[0]->children[0]->children[0]->id, $child1_1_1->id, 'Specific child expected');
        $this->assertEquals($treeRoots[0]->children[1]->id, $child2->id, 'Specific child expected');
        $this->assertEquals($treeRoots[0]->children[1]->children[1]->children[0]->id, $child2_2_1->id, 'Specific child expected');

        $this->assertEquals($treeRoots[1]->children[0]->id, $child4->id, 'Specific child expected');
        $this->assertEquals($treeRoots[1]->children[0]->children[0]->id, $child4_1->id, 'Specific child expected');
        $this->assertEquals($treeRoots[1]->children[0]->children[0]->children[0]->id, $child4_1_1->id, 'Specific child expected');
        $this->assertEquals($treeRoots[1]->children[1]->id, $child5->id, 'Specific child expected');
        $this->assertEquals($treeRoots[1]->children[1]->children[1]->children[0]->id, $child5_2_1->id, 'Specific child expected');
    }

    /**
     * Tree building on PHP side
     *
     * @test
     */
    public function it_returns_null_if_cant_build_tree()
    {
        extract($this->_createSampleTree());
        $treeRoots = $root->buildTree(new \Illuminate\Database\Eloquent\Collection()); // Empty collection, so we can't build tree
        $this->assertNull($treeRoots);
    }

    /**
     * Tree building from array
     *
     * @test
     */
    public function can_map_array()
    {
        Tree::mapArray(
            [
                [
                    'children' => [
                        [
                            'children' => [
                                [
                                    'children' => [
                                        [
                                            'children' => []
                                        ],
                                        [
                                            'children' => []
                                        ]
                                    ]
                                ],
                                [
                                    'children' => []
                                ]
                            ]
                        ],
                        [
                            'children' => []
                        ]
                    ]
                ],
                [
                    'children' => []
                ],
                [
                    'children' => []
                ]
            ]
        );
        $this->assertEquals(3, Tree::getRoots()->count(), 'Expected numer of Roots');
        $this->assertEquals(7, Tree::find(1)->findDescendants()->count(), 'Expected numer of Descendants');
        $this->assertEquals(2, Tree::find(1)->children()->count(), 'Expected numer of Children');
        $this->assertEquals(4, Tree::find(5)->findAncestors()->count(), 'Expected numer of Ancestors'); // Most nested
    }

     /**
     * getting leaf nodes
     *
     * @test
     */
     public function get_leaf_nodes()
	{
	        extract($this->_createSampleTree());
		$correct = [
			$child2,
			$child3,
			$child1_1_1
		];
		foreach($root->getLeaves()->get() as $key=>$node )
		{
			$this->assertEquals($correct[$key]->toArray(),$node->toArray());
		}
	}


    /**
     * getting leaf nodes if the tree is only one node(root)
     *
     * @test
     */
     public function get_leaf_nodes_root_only()
        {
		$root= with(new Tree())->setAsRoot();
		$correct = [
			$root->toArray()		
		];
		$this->assertEquals($correct,$root->getLeaves()->get()->toArray());
	}


    /**
     * Define environment setup.
     *
     * @param  Illuminate\Foundation\Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // reset base path to point to our package's src directory
        $app['path.base'] = __DIR__ . '/../src';

        $app['config']->set('database.default', 'testbench');
        $app['config']->set(
            'database.connections.testbench',
            [
                'driver'   => 'sqlite',
                'database' => ':memory:',
                'prefix'   => '',
            ]
        );
    }

    /**
     * Helper function
     *
     * @return array
     */
    protected function _createSampleTree()
    {
        $tree               = [];
        $tree['root']       = with(new Tree())->setAsRoot();
        $tree['child1']     = with(new Tree())->setChildOf($tree['root']);
        $tree['child2']     = with(new Tree())->setChildOf($tree['root']);
        $tree['child3']     = with(new Tree())->setChildOf($tree['root']);
        $tree['child1_1']   = with(new Tree())->setChildOf($tree['child1']);
        $tree['child1_1_1'] = with(new Tree())->setChildOf($tree['child1_1']);
        return $tree;
    }

    /**
     * Helper function
     *
     * @return array
     */
    protected function _createAdvancedTree()
    {
        $tree                 = [];
        $tree['root']         = with(new Tree())->setAsRoot();
        $tree['child1']       = with(new Tree())->setChildOf($tree['root']);
        $tree['child2']       = with(new Tree())->setChildOf($tree['root']);
        $tree['child3']       = with(new Tree())->setChildOf($tree['root']);
        $tree['child1_1']     = with(new Tree())->setChildOf($tree['child1']);
        $tree['child2_1']     = with(new Tree())->setChildOf($tree['child2']);
        $tree['child2_2']     = with(new Tree())->setChildOf($tree['child2']);
        $tree['child1_1_1']   = with(new Tree())->setChildOf($tree['child1_1']);
        $tree['child2_2_1']   = with(new Tree())->setChildOf($tree['child2_2']);
        $tree['child2_2_2']   = with(new Tree())->setChildOf($tree['child2_2']);
        $tree['child2_2_2_1'] = with(new Tree())->setChildOf($tree['child2_2_2']);
        return $tree;
    }

    /**
     * Helper function
     *
     * @return array
     */
    protected function _createAdvancedTrees()
    {
        $tree                 = [];
        $tree['root']         = with(new Tree())->setAsRoot();
        $tree['child1']       = with(new Tree())->setChildOf($tree['root']);
        $tree['child2']       = with(new Tree())->setChildOf($tree['root']);
        $tree['child3']       = with(new Tree())->setChildOf($tree['root']);
        $tree['child1_1']     = with(new Tree())->setChildOf($tree['child1']);
        $tree['child2_1']     = with(new Tree())->setChildOf($tree['child2']);
        $tree['child2_2']     = with(new Tree())->setChildOf($tree['child2']);
        $tree['child1_1_1']   = with(new Tree())->setChildOf($tree['child1_1']);
        $tree['child2_2_1']   = with(new Tree())->setChildOf($tree['child2_2']);
        $tree['child2_2_2']   = with(new Tree())->setChildOf($tree['child2_2']);
        $tree['child2_2_2_1'] = with(new Tree())->setChildOf($tree['child2_2_2']);
        $tree['root2']        = with(new Tree())->setAsRoot();
        $tree['child4']       = with(new Tree())->setChildOf($tree['root2']);
        $tree['child5']       = with(new Tree())->setChildOf($tree['root2']);
        $tree['child6']       = with(new Tree())->setChildOf($tree['root']);
        $tree['child4_1']     = with(new Tree())->setChildOf($tree['child4']);
        $tree['child5_1']     = with(new Tree())->setChildOf($tree['child5']);
        $tree['child5_2']     = with(new Tree())->setChildOf($tree['child5']);
        $tree['child4_1_1']   = with(new Tree())->setChildOf($tree['child4_1']);
        $tree['child5_2_1']   = with(new Tree())->setChildOf($tree['child5_2']);
        $tree['child5_2_2']   = with(new Tree())->setChildOf($tree['child5_2']);
        $tree['child5_2_2_1'] = with(new Tree())->setChildOf($tree['child5_2_2']);
        return $tree;
    }
}

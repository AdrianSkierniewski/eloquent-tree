<?php
spl_autoload_register( // Autoload because we're using \Eloquent alias provided by Orchestra
    function ($class) {
        require_once 'tests/Model/Tree.php';
    }
);

class Test extends Orchestra\Testbench\TestCase {


    /**
     * Default preparation for each test
     */
    public function setUp()
    {

        parent::setUp();
        $artisan = $this->app->make('artisan');
        $artisan->call(
            'migrate',
            array(
                '--database' => 'testbench',
                '--path'     => 'migrations',
            )
        );
    }

    public function tearDown()
    {
        parent::tearDown();
        Tree::__resetBootedStaticProperty();
    }


    /**
     * New node saved as root
     */
    public function testCreateNewNodeAsRoot()
    {
        $root = new Tree();
        $this->assertNotEmpty($root->setAsRoot());
        $this->assertTrue($root->isRoot(), 'Assert root node');
        $this->assertEquals($root->id, 1);
        $this->assertEquals($root->path, $root->id . '/');
        $this->assertEquals($root->level, 0);
        $this->assertEmpty($root->getParent(), 'Expected no parent');
        $root2 = new Tree();
        $this->assertNotEmpty($root2->save()); // Standard save - we expect root node
        $this->assertTrue($root2->isRoot(), 'Assert root node');
        $this->assertEquals($root2->id, 2);
        $this->assertEquals($root2->path, $root2->id . '/');
        $this->assertEquals($root2->level, 0);
        $this->assertEmpty($root2->getParent(), 'Expected no parent');
    }

    /**
     * New node saved as child
     */
    public function testCreateNewNodeAsChildren()
    {
        $root  = with(new Tree())->setAsRoot();
        $child = with(new Tree())->setChildOf($root);
        $this->assertEquals($root->path . $child->id . '/', $child->path, 'Wrong children path!');
        $this->assertEquals($root->level + 1, $child->level, 'Wrong children level!');
        $this->assertEquals($root->id, $child->parent_id, 'Wrong children parent!');
    }

    /**
     * New node saved as sibling
     */
    public function testCreateNewNodeAsSibling()
    {
        $sibling = with(new Tree())->setAsRoot();
        $node    = with(new Tree())->setSiblingOf($sibling);
        $this->assertEquals($node->id . '/', $node->path, 'Wrong sibling path!');
        $this->assertEquals($sibling->level, $node->level, 'Wrong sibling level!');
        $this->assertEquals($sibling->parent_id, $node->parent_id, 'Wrong sibling parent!');
    }

    /**
     * Change existing node to root node
     */
    public function testChangeNodeToRoot()
    {
        $root = with(new Tree())->setAsRoot();
        $node = with(new Tree())->setChildOf($root);
        $this->assertEquals($root->toArray(), $node->getParent()->toArray());
        $node->setAsRoot();
        $this->assertEmpty($node->getParent(), 'New root expected to have no parent');
        $this->assertEquals(0, $node->level);
        $this->assertEquals($node->id . '/', $node->path);
        $this->assertEquals($node->parent_id, NULL, 'New root parent_id expected to be NULL');
    }

    /**
     * Get all children for specific node
     */
    public function testfindChildrenForNode()
    {
        $root         = with(new Tree())->setAsRoot();
        $node         = with(new Tree())->setChildOf($root);
        $node2        = with(new Tree())->setChildOf($root);
        $collection[] = $node->toArray();
        $collection[] = $node2->toArray();
        $this->assertNotEmpty($node->getParent(), 'Node expects to have a parent');
        $this->assertEquals($collection, $root->findChildren()->get()->toArray(), 'Root expects to have children');

        // children becomes root node
        $newRoot = $node->setAsRoot();
        $this->assertTrue($newRoot->isRoot(), 'Assert root node');
        $this->assertEmpty($newRoot->getParent(), 'Expected no parent');
        $collection[0] = $node2->toArray();
        unset($collection[1]);
        $this->assertEquals($collection, $root->findChildren()->get()->toArray(), 'Root expects to have children');
        $this->assertEquals(array(), $newRoot->findChildren()->get()->toArray(), 'New Root expects to have no children');
    }

    /**
     * Get all ancestors for specific node
     */
    public function testfindAncestorsForNode()
    {
        extract($this->_createSampleTree());
        $this->assertEquals($child1_1->toArray(), $child1_1_1->getParent()->toArray(), 'Node expects to have a specific parent');
        $this->assertEquals( // Ancestors same as returned from findAncestors()
            array(
                $root->toArray(),
                $child1->toArray(),
                $child1_1->toArray(),
                $child1_1_1->toArray() // Last node is for which we are looking for Ancestors
            ),
            $child1_1_1->findAncestors()->get()->toArray()
        );
    }

    /**
     * Get all descendants for specific node
     */
    public function testGetAllDescendantsForNode()
    {
        extract($this->_createSampleTree());
        $this->assertEquals(TRUE, $child1_1_1->isLeaf(), 'Node expected to be leaf');
        $this->assertEquals( // Descendants same as returned from findDescendants()
            array(
                $child1->toArray(),
                $child1_1->toArray(),
                $child1_1_1->toArray()
            ),
            $child1->findDescendants()->get()->toArray()
        );
    }

    /**
     * Get root for specific node
     */
    public function testfindRootNode()
    {
        extract($this->_createSampleTree());
        $this->assertEquals($root->toArray(), $child1_1_1->findRoot()->toArray(), 'Expected root node');
    }

    /**
     * Recursive node updating
     */
    public function testMoveSubTree()
    {
        extract($this->_createAdvancedTree());
        $this->assertEquals($child2_2->toArray(), $child2_2_1->getParent()->toArray(), 'Node expects to have a specific parent');
        $this->assertEquals($child2_2_1->level, 3, 'Node expects to have a specific level');
        $child2_2->setAsRoot();
        $this->assertEquals(0, $child2_2->level, 'Node expects to have a specific level');
        $this->assertEquals(1, with(Tree::find($child2_2_1->id))->level, 'Node expects to have a specific level');
        $this->assertEquals(1, with(Tree::find($child2_2_2->id))->level, 'Node expects to have a specific level');
        $this->assertEquals(2, with(Tree::find($child2_2_2_1->id))->level, 'Node expects to have a specific level');
    }


    /**
     * Tree building on PHP side
     */
    public function testBuildCompleteTree()
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
     * Tree building from array
     */
    public function testMapArray()
    {
        Tree::mapArray(
            array(
                array(
                    'children' => array(
                        array(
                            'children' => array(
                                array(
                                    'children' => array(
                                        array(
                                            'children' => array()
                                        ),
                                        array(
                                            'children' => array()
                                        )
                                    )
                                ),
                                array(
                                    'children' => array()
                                )
                            )
                        ),
                        array(
                            'children' => array()
                        )
                    )
                ),
                array(
                    'children' => array()
                ),
                array(
                    'children' => array()
                )
            )
        );
        $this->assertEquals(3, Tree::getRoots()->count(), 'Expected numer of Roots');
        $this->assertEquals(7, Tree::find(1)->findDescendants()->count(), 'Expected numer of Descendants');
        $this->assertEquals(2, Tree::find(1)->findChildren()->count(), 'Expected numer of Children');
        $this->assertEquals(4, Tree::find(5)->findAncestors()->count(), 'Expected numer of Ancestors'); // Most nested
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
            array(
                'driver'   => 'sqlite',
                'database' => ':memory:',
                'prefix'   => '',
            )
        );
    }

    /**
     * Helper function
     *
     * @return array
     */
    protected function _createSampleTree()
    {
        $tree               = array();
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
        $tree                 = array();
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
}

<?php
/**
 * Created by PhpStorm.
 * User: dmn
 * Date: 17.12.13
 * Time: 11:26
 */
use Gzero\EloquentTree\Model\Tree;

class Test extends \Illuminate\Foundation\Testing\TestCase {

    /**
     * Default preparation for each test
     */
    public function setUp()
    {
        parent::setUp();

        $this->prepareForTests();
    }

    public function tearDown()
    {
        parent::tearDown();
        Tree::__resetBootedStaticProperty();
    }

    /**
     * Creates the application.
     *
     * @return Symfony\Component\HttpKernel\HttpKernelInterface
     */
    public function createApplication()
    {
        $unitTesting = TRUE;

        $testEnvironment = 'testing';

        return require __DIR__ . '/../../../../bootstrap/start.php';
    }

    /**
     * Migrate the database
     */
    private function prepareForTests()
    {
        Artisan::call('migrate', array('--bench' => 'gzero/eloquent-tree'));
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
    public function testGetChildrenForNode()
    {
        $root         = with(new Tree())->setAsRoot();
        $node         = with(new Tree())->setChildOf($root);
        $node2        = with(new Tree())->setChildOf($root);
        $collection[] = $node->toArray();
        $collection[] = $node2->toArray();
        $this->assertNotEmpty($node->getParent(), 'Node expects to have a parent');
        $this->assertEquals($collection, $root->getChildren()->get()->toArray(), 'Root expects to have children');

        // children becomes root node
        $newRoot = $node->setAsRoot();
        $this->assertTrue($newRoot->isRoot(), 'Assert root node');
        $this->assertEmpty($newRoot->getParent(), 'Expected no parent');
        $collection[0] = $node2->toArray();
        unset($collection[1]);
        $this->assertEquals($collection, $root->getChildren()->get()->toArray(), 'Root expects to have children');
        $this->assertEquals(array(), $newRoot->getChildren()->get()->toArray(), 'New Root expects to have no children');
    }

    /**
     * Get all ancestors for specific node
     */
    public function testGetAncestorsForNode()
    {
        extract($this->_createSampleTree());
        $this->assertEquals($child1_1->toArray(), $child1_1_1->getParent()->toArray(), 'Node expects to have a specific parent');
        $this->assertEquals( // Ancestors same as returned from getAncestors()
            array(
                $root->toArray(),
                $child1->toArray(),
                $child1_1->toArray()
            ),
            $child1_1_1->getAncestors()->get()->toArray()
        );
    }

    /**
     * Get all descendants for specific node
     */
    public function testGetAllDescendantsForNode()
    {
        extract($this->_createSampleTree());
        $this->assertEquals( // Descendants same as returned from getDescendants()
            array(
                $child1_1->toArray(),
                $child1_1_1->toArray()
            ),
            $child1->getDescendants()->get()->toArray()
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
}

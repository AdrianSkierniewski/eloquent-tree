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
        $root  = (new Tree())->setAsRoot();
        $child = (new Tree())->setChildOf($root);
        $this->assertEquals($root->path . $child->id . '/', $child->path, 'Wrong children path!');
        $this->assertEquals($root->level + 1, $child->level, 'Wrong children level!');
        $this->assertEquals($root->id, $child->parent_id, 'Wrong children parent!');
    }

    /**
     * New node saved as sibling
     */
    public function testCreateNewNodeAsSibling()
    {
        $sibling = (new Tree())->setAsRoot();
        $node    = (new Tree())->setSiblingOf($sibling);
        $this->assertEquals($node->id . '/', $node->path, 'Wrong sibling path!');
        $this->assertEquals($sibling->level, $node->level, 'Wrong sibling level!');
        $this->assertEquals($sibling->parent_id, $node->parent_id, 'Wrong sibling parent!');
    }

    public function testChangeNodeToRoot()
    {
        $root = (new Tree())->setAsRoot();
        $node = (new Tree())->setChildOf($root);
        $this->assertEquals($root->toArray(), $node->getParent()->toArray());
        $node->setAsRoot();
        $this->assertEmpty($node->getParent(), 'New root expected to have no parent');
        $this->assertEquals(0, $node->level);
        $this->assertEquals($node->id . '/', $node->path);
        $this->assertEquals($node->parent_id, NULL, 'New root parent_id expected to be NULL');
    }
}

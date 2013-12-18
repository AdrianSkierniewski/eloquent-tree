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
        $this->assertEquals($root->id, 1);
        $this->assertEquals($root->path, $root->id . '/');
        $this->assertEquals($root->level, 0);
        $root2 = new Tree();
        $this->assertNotEmpty($root2->save()); // Standard save - we expect root node
        $this->assertEquals($root2->id, 2);
        $this->assertEquals($root2->path, $root2->id . '/');
        $this->assertEquals($root2->level, 0);
    }

    public function testCreateNewNodeAsChildren()
    {
        $root  = (new Tree())->setAsRoot();
        $child = (new Tree())->setChildOf($root);
        $this->assertEquals($root->path . $child->id . '/', $child->path, 'Wrong children path!');
        $this->assertEquals($root->level + 1, $child->level, 'Wrong children level!');
        $this->assertEquals($root->id, $child->parent_id, 'Wrong children parent!');
    }
}

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
    public function testCreateNewNode()
    {
        $node = new Tree();
        $this->assertNotEmpty($node->save());
        $this->assertEquals($node->id, 1);
        $this->assertEquals($node->path, $node->id . '/');
        $this->assertEquals($node->level, 0);
    }
}

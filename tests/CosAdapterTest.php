<?php
/**
 * Created by PhpStorm.
 * User: Liukaho
 * Date: 2018-12-26
 * Time: 15:43
 */

namespace Liukaho\Flysystem\Cos\Tests;


use Liukaho\Flysystem\Cos\CosAdapter;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;

class CosAdapterTest extends TestCase
{

    public $flysystem;

    public function setUp()
    {
        $adapter = new CosAdapter('secretId', 'secretKey', 'bucket', 'region');

        $this->flysystem = new Filesystem($adapter);

        //create necessary file
        if (!$this->flysystem->has('foo.md')){
            $this->flysystem->write('foo.md', 'test');
        }

    }

    public function testHas()
    {
        $this->assertTrue(true, $this->flysystem->has('foo.md'));
        $this->assertFalse(false, $this->flysystem->has('foo2.md'));
    }

    public function testRead()
    {
        $this->assertEquals('test', $this->flysystem->read('foo.md'));
    }

    public function testUpdate()
    {
        $this->flysystem->update('foo.md', 'test update');
        $this->assertEquals('test update', $this->flysystem->read('foo.md'));
    }



    public function testCopy()
    {
        $this->flysystem->copy('foo.md', 'foo_copy.md');
        $this->assertEquals('test update', $this->flysystem->read('foo_copy.md'));
    }

    public function testRename()
    {
        $this->flysystem->rename('foo.md', 'foo_rename.md');
        $this->assertFalse($this->flysystem->has('foo.md'));
        $this->assertTrue($this->flysystem->has('foo_rename.md'));
    }

    public function testDelete()
    {
        $this->flysystem->delete('foo_copy.md');
        $this->flysystem->delete('foo_rename.md');
        $this->assertFalse($this->flysystem->has('foo_copy.md'));
        $this->assertFalse($this->flysystem->has('foo_rename.md'));
    }
}
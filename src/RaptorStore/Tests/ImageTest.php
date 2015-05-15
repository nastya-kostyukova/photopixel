<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 15.5.15
 * Time: 21.02
 */

use Silex\Provider\DoctrineServiceProvider;
use RaptorStore\Image;

class ImageTest extends PHPUnit_Framework_TestCase{

    public function testCountLikes()
    {
        $image = new Image();
        //$this->assertArrayHasKey('foo', array('foo' => 'baz'));
        //$this->assertArrayHasKey('foo', array('qwe' => 'baz'));
    }

    public function testLoadFeed() {
        $app = new Silex\Application();

        $app->register(new DoctrineServiceProvider(), array(
            'db.options' => array(
                'driver'   => 'pdo_mysql',
                'host'  => 'localhost',
                'dbname' => 'Photos_local',
                'user' => 'root',
                'password' => '123',
            ),
        ));

        //$sql = "SELECT * FROM users LIMIT 1";
        $arr = $app['db']->getSchemaManager()->listTables();
        $this->assertTrue(is_array($arr));
    }
}
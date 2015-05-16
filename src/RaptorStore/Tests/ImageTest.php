<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 15.5.15
 * Time: 21.02
 */

use Silex\Provider\DoctrineServiceProvider;

use RaptorStore\Image;
use RaptorStore\Social;
class ImageTest extends PHPUnit_Framework_TestCase{

    public function testDBConnection() {
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

        $arr = $app['db']->getSchemaManager()->listTables();
        $this->assertTrue(is_array($arr));
    }

    public  function testCreateFile() {
        $dir = "/var/www/photopixel/web/upload/";
        $user = "user123";

        file_put_contents("$dir$user/1.txt", "yesss!");
        $this->assertFileExists("$dir$user/1.txt");
        unlink("$dir$user/1.txt");
    }

    public function testGetUserImages()
    {
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
        $image = new Image($app['db']);
        $images = $image->getUserImages(1);
        $this->assertTrue(is_array($images));
    }

    public function testLoadFeed()
    {
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
        $id_follower = 4;

        $social = new Social($app['db']);
        $posts = $social->loadFeed($id_follower);
        $image_is_liked = 'FALSE';
        $countLikes = 0;

        foreach($posts as $post) {
            $url = 'c550f7125e2adb62014dd55aa334c52e';
            if ( $url === $post['url']) {
                $image_is_liked = $post['image_is_liked'] ;
                $countLikes = $post['count_likes'];
            }
        }

        $this->assertEquals(5, count($posts));
        $this->assertSame('TRUE', $image_is_liked);
        $this->assertEquals(2, $countLikes);
    }

    public  function testCountSocial()
    {
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
        $id = 2;
        $social = new Social($app['db']);
        $count = $social->countFollower($id);
        $this->assertEquals(3, $count);

        $count = $social->countFollowing($id);
        $this->assertEquals(2, $count);

        $login = 'user123';
        $count= $social->countImages($login);
        $this->assertEquals(5, $count);
    }

    public function  testUserIsFollowed()
    {
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
        $social = new Social($app['db']);
        $user = 'masha';
        $userSession['login'] = 'user123';
        $userSession['id'] = 2;
        $id_following = 4;
        $result = $social->userIsFollowed($user, $userSession, $id_following);
        $this->assertSame('FALSE', $result);
    }

    public  function  testSaveComment()
    {
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
        $social = new Social($app['db']);
        $login = 'user123';
        $url = 'c550f7125e2adb62014dd55aa334c52e';
        $comment = "new";
        $id_author = 4;
        $res = $social->saveComment($login, $url, $comment, $id_author);
        $this->assertTrue($res);


        $sql = "SELECT id FROM users WHERE login = ?";
        $id_user = $app['db']->fetchAssoc($sql, array((string) $login));
        $sql = "SELECT id FROM images WHERE url = ?";
        $id_image = $app['db']->fetchAssoc($sql, array((string) $url));
        $sql = "SELECT * FROM comments WHERE id_user=? && id_image=? && comment=?";
        $post = $app['db']->fetchAll($sql, array( (int) $id_user['id'], (int) $id_image['id'] , (string) $comment));

        $app['db']->delete('comments', array('id_author' => $id_author, 'id_user' => $id_user['id'], 'id_image' => $id_image['id'], 'comment' => $comment));
    }

    public function testSaveLike()
    {
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
        $social = new Social($app['db']);
        $login = 'user123';
        $url = '3d635b9c2c358647d3253b479fe82df7';
        $id_author = 5;
        $res = $social->saveLike($login, $url, $id_author);
        $this->assertTrue($res);

        $social->saveLike($login, $url, $id_author);
    }
}
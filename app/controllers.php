<?php
/** @var $app \Silex\Application */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use RaptorStore\User;
use RaptorStore\DB;
use RaptorStore\Image;
/**
 * Controllers and routes
 */

$app->get('/', function(\Silex\Application $app) {
    if (null !== $userSession = $app['session']->get('user'))
    {
        return $app->redirect('/'.$userSession['login']);
    }
    return $app['twig']->render('homepage.twig', array('message_login' => '', 'message_register' => ''));
});

$app->get('/{user}/exit', function($user) use ($app){
    $app['session']->remove('user');
    return $app->redirect('/');
})->bind('exit');

$app->post('/register', function(Request $request) use ($app) {
    $login = $request->get('login-reg');
    $password = $request->get('password-reg');
    $password_again = $request->get('password-reg-again');
    $result['message']='';

    $sql = "SELECT * FROM users WHERE login = ?";
    $post = $app['db']->fetchAssoc($sql, array((string) $login));
    if ($login === $post['login'])
    {
        $result['message']='This login is used';
        return $app['twig']->render('homepage.twig', array('message_register' => $result['message'], 'message_login' => ''));
    }

    if (!($password === $password_again))
    {
        $result['message']='Password and password again dont identical';
        return $app['twig']->render('homepage.twig', array('message_register' => $result['message'], 'message_login' => ''));
    }

    if (isset($login) && isset($password) && isset($password_again)) {
        $result = $app['user_repository']->check($login, $password);
        if ( $result['status']=== 'ok')
        {
            $password = md5($password);
            $app['db']->insert('users', array('login' => $login, 'password' => $password));
        }
    }
    return $app['twig']->render('homepage.twig', array(
        'message_register' => $result['message'],
        'message_login' => ''));
});

$app->post('/login', function(Request $request) use ($app){
    $login = $request->get('login');
    $password = $request->get('password');
    $result['message']='';

    $sql = "SELECT * FROM users WHERE login = ?";
    $post = $app['db']->fetchAssoc($sql, array((string) $login));
    if (($login === $post['login']) && (md5($password) == $post['password']))
    {
        $app['session']->set('user' , array('login' => $login, 'id' => $post['id']));
        return $app->redirect('/'.$login.'');
    }
    else
    {
        $result['message']='Invalid login or password';
        return $app['twig']->render('homepage.twig', array(
            'message_register' => '',
            'message_login' => $result['message']));
    }
});

$app->get('/admin', function(\Silex\Application $app) {
    return $app['twig']->render('admin.twig');
})->bind('admin');

$app->get('/register', function(\Silex\Application $app) {
    return $app->redirect('/');
})->bind('register');

$app->get('/{user}', function (Request $request, $user) use ($app) {
    $sql = "SELECT * FROM users WHERE login = ?";
    $post = $app['db']->fetchAssoc($sql, array((string) $user));
    if ($user === $post['login']) {
        $images = $app['image']->getArrayUserImages($post);
        if (null === $userSession = $app['session']->get('user'))
        {
            $app->redirect('/');
        }
        if  ($user === $userSession['login']){
            $user_is_followed = '';
        }else {
            $sql = "SELECT * FROM users WHERE login=?";
            $post = $app['db']->fetchAssoc($sql, array((string) $user));
            $id_followed = $post['id'];//его страница открыта
            #$login = $userSession['login'];
            #$post = $app['db']->fetchAssoc($sql, array((string)$login));
            $id_follower = $userSession['id'];

            $sql = "SELECT * FROM followers WHERE id_follower=?";
            $post = $app['db']->fetchAll($sql, array((int)$id_follower));

            foreach ($post as $value) {
                foreach ($value as $key => $type) {
                    if ('id_followed' === $key) {
                        if ($id_followed === $type) {
                            $user_is_followed = 'TRUE';
                        }

                    }
                }
            }
            if (!isset($user_is_followed)){
                $user_is_followed = 'FALSE';
            }

        }

        if ($user != $userSession['login']){
            $user_check_flag = 'TRUE';
        } else $user_check_flag = 'FALSE';

        if (isset($images['error'])){
            $flag_no_image = 'TRUE';
        }
        else $flag_no_image = 'FALSE';

        return $app['twig']->render('image.twig', array(
            'user_name' => $user,
            'message' => '',
            'images' => $images,
            'select' => 'home',
            'user'=> $userSession['login'],
            'user_is_followed' => $user_is_followed,
            'user_check_flag' => $user_check_flag,
            'flag_no_image' => $flag_no_image,
        ));
    }
    else return "Error.This user doesnt exist";
})->bind('user_account');

$app->get('/{user}/follow', function($user) use ($app) {
    $sql = "SELECT * FROM users WHERE login=?";
    $post = $app['db']->fetchAssoc($sql, array((string) $user));
    $id_followed = $post['id'];
    $userSession = $app['session']->get('user');
    $id_follower = $userSession['id'];

    #$app['db']->insert('users', array('login' => $login, 'password' => $password));
    $app['db']->insert('followers', array('id_follower' => $id_follower, 'id_followed' => $id_followed));

    return $app->redirect('/'.$user.'');
})->bind('follow_user');
/*
$app->get('/{user}/follow', function ($user) use ($app){
    echo $user;
    return $app->redirect('/'.$user.'');
})->bind('follow_user');
*/
$app->get('/{user}/followed', function($user) use ($app)
{
    $sql = "SELECT * FROM users WHERE login = ?";
    $post = $app['db']->fetchAssoc($sql, array((string) $user));
    $id_followed = $post['id'];
    $userSession = $app['session']->get('user');
    $id_follower = $userSession['id'];

    $sql = "DELETE FROM followers WHERE id_follower=?, id_followed=?";
    $post = $app['db']->fetchAssoc($sql, array((int) $id_follower, (int) $id_followed));

    if ($userSession['login'] === $post['login']) {
        $images = $app['image']->getArrayUserImages($post);
    }
    if ($user != $userSession['login']){
        $user_check_flag = true;
    }
    else $user_check_flag = false;

    return $app['twig']->render('image.twig', array(
        'user_name' => $user,
        'message' => '',
        'images' => $images,
        'select' => 'home',
        'user'=> 'user',
        'user_is_followed' => 'FALSE',
        'user_check_flag' => $user_check_flag,
    ));
})->bind('followed_user');

$app->get('/{user}/upload', function ($user) use ($app) {
    return $app['twig']->render('upload.twig', array(
        'user_name' => $user,
        'message' => '',
        'select' => 'upload',
        'user'=> 'user',
        'user_is_followed' => ''));
})->bind('user_upload');

$app->post('/{user}/upload',  function(Request $request, $user) use ($app) {
    $image = new Image($app['db']);
    $result = $image->uploadImage($user,  __DIR__."/../web/upload/");

    $title = $request->get('title');
    $description = $request->get('description');

    #$app['filters']->filter( __DIR__."/../web/upload/".$result['url'], 'my_thumb');

    $image->saveImageInDB($user, $result['url'], $title, $description);
    return $app['twig']->render('upload.twig', array(
        'user_name' => $user,
        'message' => $result['message'],
        'select' => 'upload',
        'user'=> 'user',
        'user_is_followed' => ''
        ));
});

$app->get('/{user}/settings', function ($user) use ($app){
    return $app['twig']->render('settings.twig', array(
        'user_name' => $user,
        'message' => '',
        'select' => 'settings',
        'user'=> 'user',
    ));
})->bind('settings');

$app->get('/{user}/tape', function ($user) use ($app){
    return $app['twig']->render('tape.twig');
});

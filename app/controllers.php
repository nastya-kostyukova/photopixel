<?php
/** @var $app \Silex\Application */

use Symfony\Component\HttpFoundation\Request;
use RaptorStore\Image;
/**
 * Controllers and routes
 */

$app->get('/', function(\Silex\Application $app) {
    if (null !== $userSession = $app['session']->get('user'))
    {
        return $app->redirect('/'.$userSession['login']);
    }
    return $app['twig']->render('homepage

    .twig', array('message_login' => '', 'message_register' => ''));
});

$app->get('/exit', function(\Silex\Application $app) {
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

$app->get('/feed', function(\Silex\Application $app) use ($app){
    $userSession = $app['session']->get('user');
    $id_follower = $userSession['id'];
    $sql = "SELECT u.login, i.url, i.title, i.description, i.published_date FROM users u INNER JOIN followers f JOIN images i ON f.id_following = u.id && i.id_author = u.id WHERE f.id_follower=? ORDER BY i.published_date DESC ";
    $images = $app['db']->fetchAll($sql, array( (int) $id_follower));

    return $app['twig']->render('tape.twig', array(
        'user_name'=> $userSession['login'],
        'posts'=> $images,
    ));
})->bind('feed');

$app->get('/{user}', function ( $user) use ($app) {
    $sql = "SELECT * FROM users WHERE login = ?";
    $post = $app['db']->fetchAssoc($sql, array((string) $user));
    $id_following = $post['id'];//его страница открыта;
    if (isset($id_following)) {
        $images = $app['image']->getArrayUserImages($post);
        if (!($app['session']->has('user')))
        {
            $app->redirect('/');
        }
        $userSession = $app['session']->get('user');
        if  ($user === $userSession['login']){
            $user_is_followed = '';
        }else {
            $id_follower = $userSession['id'];
            $sql= "SELECT id_follower, id_following FROM followers WHERE id_follower=? AND id_following=?";
            $post = $app['db']->fetchAssoc($sql, array((int) $id_follower, (int) $id_following));
            if (isset($post['id_follower'])){
                $user_is_followed = 'TRUE';
            }else{
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
    $id_following = $post['id'];
    $userSession = $app['session']->get('user');
    $id_follower = $userSession['id'];

    $app['db']->insert('followers', array('id_following' => $id_following, 'id_follower' => $id_follower));

    return $app->redirect('/'.$user.'');
})->bind('follow_user');

$app->get('/{user}/followed', function($user) use ($app)
{
    $sql = "SELECT * FROM users WHERE login = ?";
    $post = $app['db']->fetchAssoc($sql, array((string) $user));
    $id_following = $post['id'];
    $userSession = $app['session']->get('user');
    $id_follower = $userSession['id'];
    $sql = "DELETE FROM followers WHERE id_follower=? AND id_following=?";
    $post = $app['db']->fetchAssoc($sql, array((int) $id_follower, (int) $id_following));
    $images = array();
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
        'user'=> $userSession['login'],
        'user_is_followed' => 'FALSE',
        'user_check_flag' => $user_check_flag,
    ));
})->bind('followed_user');

$app->get('/{user}/upload', function(\Silex\Application $app) use ($app) {
    $userSession= $app['session']->get('user');
    return $app['twig']->render('upload.twig', array(
        'user_name' => $userSession['login'],
        'message' => '',
        'select' => 'upload',
        'user'=> '',
        'user_is_followed' => '',
        'user_check_flag'=> '',
        ));
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
        'user_check_flag' => '',
        'user_is_followed' => '',
    ));
})->bind('settings');

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
    return $app['twig']->render('homepage.twig', array('message_login' => '', 'message_register' => ''));
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
    if (($login === $post['login']) && (md5($password) == $post['password'])) {
        $app['session']->set('user' , array('login' => $login, 'id' => $post['id']));
        return $app->redirect('/'.$login.'');
    }
    else {
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
    if (!isset($userSession)) {
        return $app['twig']->redirect('/');
    }
    $id_follower = $userSession['id'];
    $images = $app['social']->loadFeed($id_follower);

    return $app['twig']->render('tape.twig', array(
        'userSession'=> $userSession['login'],
        'posts'=> $images,
    ));
})->bind('feed');

$app->post('/feed', function(Request $request) use ($app) {
    $userSession = $app['session']->get('user');
    if (!isset($userSession)) {
        return $app['twig']->redirect('/');
    }

    $login = $request->get('login');
    $url = $request->get('url');
    $comment = $request->get('comment');
    $userSession = $app['session']->get('user');

    if ($request->get('comment')) {
        $app['social']->saveComment($login, $url, $comment, $userSession['id']);

    } else {
        $app['social']->saveLike($login, $url, $userSession['id']);
    }
    $images = $app['social']->loadFeed($userSession['id']);
    return $app['twig']->render('tape.twig', array(
        'userSession' => $userSession['login'],
        'posts' => $images,
    ));
});

$app->get('/{user}', function ( $user) use ($app) {
    $sql = "SELECT * FROM users WHERE login = ?";
    $post = $app['db']->fetchAssoc($sql, array((string) $user));
    $id_following = $post['id'];
    if (isset($id_following)) {
        $images = $app['image']->getArrayUserImages($post);
        if (!($app['session']->has('user'))) {
            $app->redirect('/');
        }
        $userSession = $app['session']->get('user');
        $user_is_followed = $app['social']->userIsFollowed($user, $userSession, $id_following);

        if ($user != $userSession['login']){
            $user_check_flag = 'TRUE';
        } else $user_check_flag = 'FALSE';

        if (isset($images['error'])){
            $flag_no_image = 'TRUE';
        } else $flag_no_image = 'FALSE';
        $count_follower = $app['social']->countFollower($id_following);
        $count_following = $app['social']->countFollowing($id_following);
        $count_images = $app['social']->countImages($user);
        return $app['twig']->render('image.twig', array(
            'userPage' => $user,
            'message' => '',
            'count_follower' => $count_follower,
            'count_following' => $count_following,
            'avatar' => "upload/".$user.'/avatar',
            'images' => $images,
            'select' => 'home',
            'userSession'=> $userSession['login'],
            'user_is_followed' => $user_is_followed,
            'user_check_flag' => $user_check_flag,
            'flag_no_image' => $flag_no_image,
            'count_images' => $count_images
        ));
    }
    else return "Error.This user doesnt exist";
})->bind('user_account');



$app->get('/{user}/followers', function($user) use ($app){
    $sql = "SELECT id FROM users WHERE login=?";
    $id_following = $app['db']->fetchAssoc($sql, array( (string) $user));
    $sql = "SELECT id_follower FROM followers WHERE id_following =?";
    $followers = $app['db']->fetchAll($sql, array((int) $id_following['id']));
    $id_followers = array();
    foreach ($followers as $value){
        foreach ($value as $key => $type){
            $id_followers[] = $type;
        }
    }
    $usersFollower = array();
    $sql = "SELECT id, login FROM users WHERE id=?";
    foreach($id_followers as $value){
        $usersFollower[] = $app['db']->fetchAssoc($sql, array((int) $value));
    }
    $userSession = $app['session']->get('user');
    if ($user != $userSession['login']){
        $user_check_flag = 'TRUE';
    } else $user_check_flag = 'FALSE';
    $user_is_followed = $app['social']->userIsFollowed($user, $userSession, $id_following['id']);
    $count_follower = $app['social']->countFollower($id_following['id']);
    $count_following = $app['social']->countFollowing($id_following['id']);
    $count_images = $app['social']->countImages($user);


    foreach($usersFollower as &$value) {
        $value['count_follower_user'] = $app['social']->countFollower($value['id']);
        $value['count_following_user'] = $app['social']->countFollowing($value['id']);
        $value['count_images_user'] = $app['social']->countImages($value['login']);
    }
    return $app['twig']->render('follow.twig', array(
        'users' => $usersFollower,
        'userSession'=> $userSession['login'],
        'userPage' => $user,
        'count_follower' => $count_follower,
        'select' => 'follower',
        'count_following' => $count_following,
        'user_check_flag' => $user_check_flag,
        'user_is_followed' => $user_is_followed,
        'count_images' => $count_images
    ));
})->bind('followers');

$app->get('/{user}/following', function($user) use ($app){
    $sql = "SELECT id FROM users WHERE login=?";
    $id_follower = $app['db']->fetchAssoc($sql, array( (string) $user));
    $sql = "SELECT id_following FROM followers WHERE id_follower =?";
    $following = $app['db']->fetchAll($sql, array((int) $id_follower['id']));

    $id_following = array();
    foreach ($following as $value){
        foreach ($value as $key => $type){
            $id_following[] = $type;
        }
    }
    $usersFollowing = array();
    $sql = "SELECT id, login FROM users WHERE id=?";
    foreach($id_following as $value){
        $usersFollowing[] = $app['db']->fetchAssoc($sql, array((int) $value));
    }
    $userSession = $app['session']->get('user');
    if ($user != $userSession['login']){
        $user_check_flag = 'TRUE';
    } else $user_check_flag = 'FALSE';
    $user_is_followed = $app['social']->userIsFollowed($user, $userSession, $id_follower['id']);
    $count_follower = $app['social']->countFollower($id_follower['id']);
    $count_following = $app['social']->countFollowing($id_follower['id']);
    $count_images = $app['social']->countImages($user);

    foreach($usersFollowing as &$value) {
        $value['count_follower_user'] = $app['social']->countFollower($value['id']);
        $value['count_following_user'] = $app['social']->countFollowing($value['id']);
        $value['count_images_user'] = $app['social']->countImages($value['login']);
    }

    return $app['twig']->render('follow.twig', array(
        'users' => $usersFollowing,
        'userPage' => $user,
        'select' => 'following',
        'userSession'=> $userSession['login'],
        'user_check_flag' => $user_check_flag,
        'user_is_followed' => $user_is_followed,
        'count_follower' => $count_follower,
        'count_following' => $count_following,
        'count_images' => $count_images
    ));
})->bind('following');

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
    $sql = "SELECT idfollowers FROM followers WHERE id_follower=? AND id_following=?";
    $idfollowers = $app['db']->fetchAssoc($sql, array((int) $id_follower, (int) $id_following));

    $app['db']->delete('followers', array( 'idfollowers' => (int)$idfollowers['idfollowers']));
    return $app->redirect('/'.$user.'');
})->bind('followed_user');

$app->get('/{user}/upload', function(\Silex\Application $app) use ($app) {
    $userSession= $app['session']->get('user');

    return $app['twig']->render('upload.twig', array(
        'userSession' => $userSession['login'],
        'message' => '',
        'avatar' => "upload/".$userSession['login'].'/avatar',
        ));
})->bind('user_upload');

$app->post('/{user}/upload',  function(Request $request, $user) use ($app) {
    $result = $app['upload']->uploadImage($user,  __DIR__."/../web/upload/");
    $userSession = $app['session']->get('user');
    $title = $request->get('title');
    $description = $request->get('description');

    #$app['filters']->filter( __DIR__."/../web/upload/".$result['url'], 'my_thumb');

    $app['image']->saveImageInDB($user, $result['url'], $title, $description);
    return $app['twig']->render('upload.twig', array(
        'userSession' => $userSession['login'],
        'message' => $result['message'],
        ));
});

$app->get('/{user}/settings', function ($user) use ($app){
    $userSession = $app['session']->get('user');
    return $app['twig']->render('settings.twig', array(
        'avatar' => "upload/".$userSession['login'].'/avatar',
        'userSession'=> $userSession['login'],
        'message' => ''
    ));
})->bind('settings');

$app->post('/{user}/settings',  function(Request $request, $user) use ($app) {
    $result = $app['upload']->uploadAvatar($user,  __DIR__."/../web/upload/");
    $userSession= $app['session']->get('user');
    $app['image']->saveAvatarInDB($user, $result['url']);

    return $app['twig']->render('settings.twig', array(
        'userSession' => $userSession['login'],
        'message' => $result['message'],
        'avatar' => "upload/".$userSession['login'].'/avatar',
    ));
})->bind('avatar_upload');

$app->get('/{user}/{image}', function ($user, $image) use ($app) {
    $userSession= $app['session']->get('user');
    $sql = "SELECT id FROM users WHERE login=?";
    $id = $app['db']->fetchAssoc($sql, array((string) $user));
    if ($id['id'] === $userSession['id'])
        $app['db']->delete('images', array('url' => $image, 'id_author' => $id['id']));
    return $app->redirect('/'.$user);
})->bind('delete_image');
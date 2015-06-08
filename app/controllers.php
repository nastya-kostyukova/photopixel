<?php
/** @var $app \Silex\Application */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    $login = $request->get('login');
    $password = $request->get('password');
    $password_again = $request->get('password-again');
    $result['message']='';


    $sql = "SELECT * FROM users WHERE login = ?";
    $post = $app['db']->fetchAssoc($sql, array((string) $login));
    if (!isset($login)) {
        $result['message']='This login empty';
    } elseif ((isset($login)) && ($login === $post['login'])) {
        $result['message']='This login is used';
        $result['status']='error';
    }
    if (($password !== $password_again)) {
        $result['message']="Password and password again don't identical";
        $result['status']='error';
    }
    elseif (isset($login) && isset($password) && isset($password_again)) {
        $result = $app['user_repository']->check($login, $password);

        if ( $result['status']=== 'ok') {
            $password = md5($password);
            $app['db']->insert('users', array('login' => $login, 'password' => $password));
            mkdir("__DIR__.'/../web/upload/'".$login);
            copy("__DIR__.'/../web/upload/avatar.jpg'", "__DIR__.'/../web/upload/'".$login.'/avatar.jpg');
        }
    }
    file_put_contents('data.txt', var_export($result, true), FILE_APPEND);

    $response = array("status" => $result['status'], "message" => $result['message']);

    return new Response(json_encode($response),
        200,
        ['Content-Type' => 'application/json']);
});

$app->post('/login', function(Request $request) use ($app){
    $login = $request->get('login');
    $password = $request->get('password');
    $result['message']='';

    $sql = "SELECT * FROM users WHERE login = ?";
    $post = $app['db']->fetchAssoc($sql, array((string) $login));
    if (($login === $post['login']) && (md5($password) == $post['password'])) {
        //file_put_contents('data.txt', '   Login '.$login, FILE_APPEND);
        $app['session']->set('user' , array('login' => $login, 'id' => $post['id']));
        $response = array("status" => "ok", "url" => '/'.$login);
        //file_put_contents('data.txt', var_export($response, true), FILE_APPEND);
    }
    else {
        $response = array("status" => "error", "message" => 'Invalid login or password');
    }
    return new Response(json_encode($response),
        200,
        ['Content-Type' => 'application/json']);
});



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
    $response = array();

    if (isset($_POST['submit-like'])) {
        $result = $app['social']->saveLike($login, $url, $userSession['id']);
        $response = array("status" => $result['status'], "count" => $result['count']);
    }elseif (isset($_POST['submit-comment'])){
        $app['social']->saveComment($login, $url, $comment, $userSession['id']);
        $response = $app['social']->getComment($login, $url, $comment, $userSession['id']);

    } else if (isset($_POST['submit-favorites'])) {

        $result = $app['social']->saveFavorites($login, $url, $userSession['id']);
        $response = array("status" => $result['status'], "count" => $result['count']);
    }
        return new Response(json_encode($response),
            200,
            ['Content-Type' => 'application/json']);
});

$app->get('/{user}', function ( $user) use ($app) {
    $sql = "SELECT * FROM users WHERE login = ?";
    $post = $app['db']->fetchAssoc($sql, array((string) $user));
    $userSession = $app['session']->get('user');
    if (1 == $post['ROLE']) {
        $sql = "SELECT id, login FROM users WHERE ROLE=0";
        $users = $app['db']->fetchAll($sql);
        foreach ($users as &$user) {
            $user['count_follower'] = $app['social']->countFollower($user['id']);
            $user['count_following'] = $app['social']->countFollowing($user['id']);
            $user['count_images'] = $app['social']->countImages($user['login']);
        }
        return $app['twig']->render('admin.twig', array(
            'userSession' => $userSession['login'],
            'users'=> $users,
            'images'=> '',
        ));
    } else {
        $id_following = $post['id'];
        if (isset($id_following)) {
            $images = $app['image']->getArrayUserImages($post);
            if (!($app['session']->has('user'))) {
                $app->redirect('/');
            }

            $user_is_followed = $app['social']->userIsFollowed($user, $userSession, $id_following);

            if ($user != $userSession['login']) {
                $user_check_flag = 'TRUE';
            } else $user_check_flag = 'FALSE';

            if (isset($images['error'])) {
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
                'avatar' => "upload/" . $user . '/avatar',
                'images' => $images,
                'select' => 'home',
                'userSession' => $userSession['login'],
                'user_is_followed' => $user_is_followed,
                'user_check_flag' => $user_check_flag,
                'flag_no_image' => $flag_no_image,
                'count_images' => $count_images
            ));
        } else {
            return "Error.This user doesnt exist";}
    }
})->bind('user_account');

$app->post('/{user}', function(Request $request) use ($app){
    $userSession = $app['session']->get('user');

    if (isset($_POST['deleteUser'])) {
        $app['admin']->deleteUser($_POST['selectedUser']);
    } elseif(isset($_POST['giveAdminRoots'])) {
        $app['admin']->giveAdmin($_POST['selectedUser']);
    }


    $sql = "SELECT id, login FROM users WHERE ROLE=0";
    $users = $app['db']->fetchAll($sql);
    foreach ($users as &$user) {
        $user['count_follower'] = $app['social']->countFollower($user['id']);
        $user['count_following'] = $app['social']->countFollowing($user['id']);
        $user['count_images'] = $app['social']->countImages($user['login']);
    }
    return $app['twig']->render('admin.twig', array(
        'userSession' => $userSession['login'],
        'users'=> $users,

    ));

    //return $app->redirect('/'.$userSession['login']);
})->bind('delete_user');

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
    $sql = "
SELECT id, login FROM users WHERE id=?";
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

$app->get('/{user}/favorites', function($user) use ($app){
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
    $images = $app['social']->loadFavorites($id_follower['id']);

    if (0 == count($images)) $flag_no_image = 'TRUE'; else $flag_no_image = 'FALSE';

    return $app['twig']->render('favorites.twig', array(
        'flag_no_image' => $flag_no_image,
        'images' => $images,
        'users' => $usersFollowing,
        'userPage' => $user,
        'select' => 'favorites',
        'userSession'=> $userSession['login'],
        'user_check_flag' => $user_check_flag,
        'user_is_followed' => $user_is_followed,
        'count_follower' => $count_follower,
        'count_following' => $count_following,
        'count_images' => $count_images
    ));
})->bind('favorites');

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
    $settings = $app['settings']->loadSettings($userSession['id']);

    return $app['twig']->render('settings.twig', array(
        'avatar' => "upload/".$userSession['login'].'/avatar',
        'userSession'=> $userSession['login'],
        'settings' => $settings,
        'message' => ''
    ));
})->bind('settings');

$app->post('/{user}/settings',  function(Request $request, $user) use ($app) {
    $result = $app['upload']->uploadAvatar($user,  __DIR__."/../web/upload/");
    $userSession= $app['session']->get('user');
    $forename = $request->get('forename');
    $surname = $request->get('surname');
    $gender = $request->get('gender');
    $birthdayDay = $request->get('birthday-day');
    $birthdayMonth = $request->get('birthday-month');
    $birthdayYear = $request->get('birthday-year');
    $country = $request->get('country');
    $city = $request->get('city');
    $description = $request->get('description');
    $result = $app['settings']->saveSettings($userSession['id'], $forename, $surname, $gender, $birthdayDay, $birthdayMonth, $birthdayYear, $country, $city, $description);

    //$app['image']->saveAvatarInDB($user, $result['url']);
    $settings = $app['settings']->loadSettings($userSession['id']);

    return $app['twig']->render('settings.twig', array(
        'userSession' => $userSession['login'],
        'message' => $result['message'],
        'avatar' => "upload/".$userSession['login'].'/avatar',
        'settings' => $settings,
    ));
})->bind('avatar_upload');

$app->get('/{admin}/{user}', function($admin, $user) use ($app) {
    $userSession = $app['session']->get('user');

    if ($userSession['login'] === $admin) {
        $sql = "SELECT id FROM users WHERE login=?";
        $id = $app['db']->fetchAssoc($sql, array((string)$user));
        $images = $app['admin']->showImages($id['id']);

        return $app['twig']->render('userInAdmin.twig', array(
            'images' => $images,
            'userSession' => $userSession['login'],
        ));
    } else return "You have no admin roots";
})->bind('user_settings_admin');

$app->get('/{user}/{image}', function ($user, $image) use ($app) {
    $userSession= $app['session']->get('user');
    $sql = "SELECT id FROM users WHERE login=?";
    $id = $app['db']->fetchAssoc($sql, array((string) $user));
    $sql = "SELECT ROLE FROM users WHERE id=?";
    $role = $app['db']->fetchAssoc($sql, array((int) $userSession['id']));
    $userSession['ROLE'] = $role['ROLE'];

    file_put_contents('data.txt', var_export($userSession ,true), FILE_APPEND);
    if (($id['id'] === $userSession['id']) || ($userSession['ROLE'] == 1)) {
        file_put_contents('data.txt', 111, FILE_APPEND);
        $app['db']->delete('images', array('url' => $image, 'id_author' => $id['id']));
    }
    return $app->redirect('/'.$userSession['login']);
})->bind('delete_image');
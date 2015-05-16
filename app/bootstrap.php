<?php

require_once __DIR__.'/../vendor/autoload.php';

use Silex\Provider\DoctrineServiceProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\SecurityServiceProvider;
use RaptorStore\User;
use Avalanche\Bundle\ImagineBundle\Controller;

$app = new Silex\Application();
$app['debug'] = true;

/*
 * Services
 */

/*$app['filters'] = $app->share(function($app){
    var_dump(get_class_methods(new \Avalanche\Bundle\ImagineBundle\Controller\ImagineController()));die;
    return new AvalancheImagineBundle();
});*/


$app['image'] = $app->share(function($app){
    return new \RaptorStore\Image($app['db']);
});

$app['upload'] = $app->share(function($app){
    return new \RaptorStore\Upload($app['db']);
});

$app['social'] = $app->share(function($app){
    return new \RaptorStore\Social($app['db']);
});

$app['user_repository'] = $app->share(function($app) {
    // create a dummy user to get the encoder
    $user = new User();
    return new \RaptorStore\UserRepository($app['db'], $app['security.encoder_factory']->getEncoder($user)
    );
});

/*
 * Register the providers
 */
$app->register(new DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver'   => 'pdo_mysql',
        'host'  => 'localhost',
        'dbname' => 'Photos_local',
        'user' => 'root',
        'password' => '123',
    ),
));

$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new UrlGeneratorServiceProvider());
$app->register(new SessionServiceProvider());
$app->register(new TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
));

$app->register(new Silex\Provider\SecurityServiceProvider(), array(
    'security.firewalls' => array(
        'admin' => array(
            'anonymous' => true,
            'pattern' => '^/',
            'form' => array('login_path' => '/login', 'check_path' => '/admin/login_check'),
            // lazily load the user_repository
            'users' => $app->share(function () use ($app) {
                return $app['user_repository'];
            }),
            'logout' => array('logout_path' => '/admin/logout'),
        ),
    )
));
// access controls
$app['security.access_rules'] = array(
    array('^/admin', 'ROLE_ADMIN'),
);
return $app;
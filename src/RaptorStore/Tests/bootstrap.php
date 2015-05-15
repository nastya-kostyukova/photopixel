<?php

require __DIR__.'/../../../vendor/autoload.php';
use Silex\Provider\DoctrineServiceProvider;

/*
$app = new Silex\Application();
/*
$app->register(new DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver'   => 'pdo_mysql',
        'host'  => 'localhost',
        'dbname' => 'Photos_local',
        'user' => 'root',
        'password' => '123',
    ),
));

return $app;*/
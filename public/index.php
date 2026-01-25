<?php

require_once __DIR__ . "/../vendor/autoload.php";

use Slim\Factory\AppFactory;
use DI\Container;

$container = new Container();
$container->set('renderer', function () {
    // As a parameter the base directory is used to contain a templates
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
});

$usersFilePath = __DIR__ . '/../storage/users.json';

$app->get('/users', function ($request, $response) use ($usersFilePath) {
    $users = [];
    if (file_exists($usersFilePath)) {
        $jsonFileData = file_get_contents($usersFilePath);
        $users = json_decode($jsonFileData, flags:JSON_OBJECT_AS_ARRAY);
    }

    $params = [
        'users' => $users,
        'term' => $request->getQueryParam('term')
    ];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
});

$app->post('/users', function ($request, $response) use ($usersFilePath) {
    $user = $request->getParsedBodyParam('user');
    $users = [];
    if (file_exists($usersFilePath)) {
        $jsonFileData = file_get_contents($usersFilePath);
        $users = json_decode($jsonFileData, flags:JSON_OBJECT_AS_ARRAY);
    }

    $users[] = $user;
    $jsonUsers = json_encode($users);
    $type = gettype($jsonUsers);
    file_put_contents(
        $usersFilePath, $jsonUsers
    );
    
    return $response->withRedirect('/users', 302)
                    ->write("nickname{$users[-1]['nickname']}, email = {$users[-1]['email']} registered!");
});

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
});
/*
$app->get('/users/{id}', function ($request, $response, array $args) {
    $params = ['id' => $args['id'], 'nickname' => "user-{$args['id']}" ];
    // The specified path is considered relative to the base directory for the
    // templates specified at the configuration stage.
    // $this is available thanks to https://php.net/manual/ru/closure.bindto.php
    // $this is a dependency container in Slim.
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
});
*/

$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['nickname' => '', 'email' => '']
    ];
    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
});

$app->run();

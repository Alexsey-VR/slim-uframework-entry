<?php

namespace App;

require_once __DIR__ . "/../vendor/autoload.php";

use Slim\Factory\AppFactory;
use DI\Container;
use App\Validator as Validator;

session_start();

$container = new Container();
$container->set('renderer', function () {
    // As a parameter the base directory is used to contain a templates
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
})->setName('mainPage');

$usersFilePath = __DIR__ . '/../storage/users.json';

$app->get('/users', function ($request, $response) use ($usersFilePath, $router) {
    $messages = $this->get('flash')->getMessages();
    $users = [];
    if (file_exists($usersFilePath)) {
        $jsonFileData = file_get_contents($usersFilePath);
        $users = json_decode($jsonFileData, flags:JSON_OBJECT_AS_ARRAY);
    }

    $params = [
        'users' => $users,
        'term' => $request->getQueryParam('term'),
        'router' => $router,
        'flash' => $messages
    ];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('usersGet');

$app->post('/users', function ($request, $response) use ($usersFilePath, $router) {
    $user = $request->getParsedBodyParam('user');
    $users = [];

    if (file_exists($usersFilePath)) {
        $jsonFileData = file_get_contents($usersFilePath);
        $users = json_decode($jsonFileData, flags:JSON_OBJECT_AS_ARRAY);
    }

    $validator = new Validator();
    $errors = $validator->validate($user);
    if (count($errors) === 0) {
        $this->get('flash')->addMessage('success', 'User was added successfully');
        $users[] = $user;
        $jsonUsers = json_encode($users);
        file_put_contents(
            $usersFilePath, $jsonUsers
        );
        
        return $response->withRedirect($router->urlFor('usersGet'), 302);
    }

    $this->get('flash')->addMessage('error', 'Form data has an error');
    $params = [
        'user' => ['nickname' => '', 'email' => ''],
        'errors' => $errors
    ];

    return $this->get('renderer')->render(
        $response->withStatus(422),
        'users/new.phtml',
        $params);
})->setName('usersPost');

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
})->setName('coursesIdGet');

$app->get('/users/new', function ($request, $response) use ($router) {
    $params = [
        'user' => ['nickname' => '', 'email' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
})->setName('newUsersGet');

$app->get('/users/{id}', function ($request, $response, array $args) use ($usersFilePath) {
    $fileContent = json_decode(file_get_contents($usersFilePath), JSON_OBJECT_AS_ARRAY);
    $userId = $args['id'];
    if (isset($fileContent[$userId])) {
        $params = [
            'id' => $args['id'],
            'nickname' => "{$fileContent[$args['id']]['nickname']}"
        ];
        // The specified path is considered relative to the base directory for the
        // templates specified at the configuration stage.
        // $this is available thanks to https://php.net/manual/ru/closure.bindto.php
        // $this is a dependency container in Slim.
        return $this->get('renderer')->render($response, 'users/show.phtml', $params);
    } else {
        // The specified path is considered relative to the base directory for the
        // templates specified at the configuration stage.
        // $this is available thanks to https://php.net/manual/ru/closure.bindto.php
        // $this is a dependency container in Slim.
        return $response->withStatus(404)
                        ->write("введён несуществующий id");
    }
});

$app->run();

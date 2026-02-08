<?php

namespace App;

require_once __DIR__ . "/../vendor/autoload.php";

use Slim\Factory\AppFactory;
use DI\Container;
use App\Validator as Validator;
use Slim\Middleware\MethodOverrideMiddleware;

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
$app->add(MethodOverrideMiddleware::class);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response->withRedirect('/users');
})->setName('mainPage');

$app->get('/users', function ($request, $response) use ($router) {
    $messages = $this->get('flash')->getMessages();

    $jsonUsers = $request->getCookieParam('users', json_encode([]));
    $users = json_decode($jsonUsers, JSON_OBJECT_AS_ARRAY);

    $params = [
        'users' => $users,
        'term' => $request->getQueryParam('term'),
        'router' => $router,
        'flash' => $messages
    ];
    return $this->get('renderer')->render(
        $response->withHeader('set-cookie', "users={$jsonUsers}"),
        'users/index.phtml',
        $params);
})->setName('usersGet');

$app->post('/users', function ($request, $response) use ($usersFilePath, $router) {
    $user = $request->getParsedBodyParam('user');

    $users = json_decode($request->getCookieParam('users', json_encode([])), JSON_OBJECT_AS_ARRAY);
    $jsonUsers = json_encode($users);

    $validator = new Validator();
    $errors = $validator->validate($user);
    if (count($errors) === 0) {
        $user['id'] = array_last($users ?? [])['id'] + 1;
        $users[] = $user;
        $jsonUsers = json_encode($users);
        
        $this->get('flash')->addMessage('success', 'User was added successfully');
        
        return $response->withHeader('set-cookie', "users={$jsonUsers}")
                        ->withRedirect($router->urlFor('usersGet'), 302);
    } else {
        return $response->getBody()->write("$errors[-1]")
                        ->withRedirect($router->urlFor('usersGet'), 404);
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

$app->get('/users/{id}/edit', function ($request, $response, array $args) use ($usersFilePath) {
    $id = $args['id'];

    $users = json_decode($request->getCookieParam('users', json_encode([])), JSON_OBJECT_AS_ARRAY);

    $message = $this->get('flash')->getMessages();
    $user = $users[$id];
    if (array_key_exists($id, $users)) {
        $params = [
            'user' => $user,
            'flash' => $message,
            'errors' => []
        ];
        $jsonUsers = json_encode($users);
        return $this->get('renderer')->render(
            $response,
            'users/patch.phtml',
            $params
        );
    } else {
        return $response->withStatus(404)
                        ->write('заданный ID пользователя не существует');
    }
})->setName('userEdit');

$app->get('/users/new', function ($request, $response) use ($router) {
    $params = [
        'user' => ['nickname' => '', 'email' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
})->setName('newUsersGet');

$app->patch('/users/{id}', function ($request, $response, array $args) use ($router, $usersFilePath) {
    $id = $args['id'];

    $users = json_decode($request->getCookieParam('users', json_encode([])), JSON_OBJECT_AS_ARRAY);

    $user = $request->getParsedBodyParam('user');
    $validator = new Validator();
    $errors = $validator->validate($user);
    if (count($errors) === 0) {
        $updatedUsers = array_filter(
            $users,
            function ($user) use ($id) {
                return $user['id'] != $id;
            }
        );

        $updatedUsers[$id]['id'] = $id;
        $updatedUsers[$id]['nickname'] = $user['nickname'];
        $updatedUsers[$id]['email'] = $user['email'];

        $jsonUsers = json_encode($updatedUsers);

        $this->get('flash')->addMessage('success', 'user data successfully updated');
        return $response->withHeader('set-cookie', "users={$jsonUsers}; MAX-AGE=1")
                        ->withRedirect($router->urlFor('usersGet')
        );
    } else {
        $params = [
            'user' => $user,
            'errors' => $errors
        ];
        return $this->get('renderer')->render(
            $response->withStatus(422),
            "users/patch.phtml",
            $params
        );
    }
});

$app->get('/users/{id}/delete', function ($request, $response, array $args) use ($usersFilePath) {
    $id = $args['id'];

    $jsonUsers = $request->getCookieParam('users', json_encode([]));
    $users = json_decode($jsonUsers, JSON_OBJECT_AS_ARRAY);
    if (array_key_exists($id, $users)) {
        $params = [
            'user' => $users[$id],
            'flash' => $message,
            'errors' => []
        ];
        return $this->get('renderer')->render(
            $response,
            'users/remove.phtml',
            $params
        );
    } else {
        return $response->withStatus(404)
                        ->write('заданный ID пользователя не существует');
    }
})->setName('userDelete');

$app->delete('/users/{id}', function ($request, $response, array $args) use ($usersFilePath, $router) {
    $id = $args['id'];

    $users = json_decode($request->getCookieParam('users', json_encode([])), JSON_OBJECT_AS_ARRAY);

    $user = $request->getParsedBodyParam('user');
    $url = $router->urlFor('usersGet');
    if ($user['remove'] === 'true') {
        $updatedUsers = array_filter(
            $users,
            function ($user) use ($id) {
                return $user['id'] != $id;
            }
        );

        $jsonUsers = json_encode($updatedUsers);
        $this->get('flash')->addMessage('success', "User has been deleted");
        return $response->withHeader('set-cookie', "users={$jsonUsers}; MAX-AGE=1")
                        ->withRedirect($url); 
    }
    return $response->withRedirect($url);
});

$app->get('/users/{id}', function ($request, $response, array $args) use ($usersFilePath) {
    $users = json_decode($request->getCookieParam('users', json_encode([])), JSON_OBJECT_AS_ARRAY);
    $userId = $args['id'];
    if (isset($users[$userId])) {
        $params = [
            'id' => $args['id'],
            'nickname' => "{$users[$args['id']]['nickname']}"
        ];
        // The specified path is considered relative to the base directory for the
        // templates specified at the configuration stage.
        // $this is available thanks to https://php.net/manual/ru/closure.bindto.php
        // $this is a dependency container in Slim.
        return $this->get('renderer')->render(
            $response,
            'users/show.phtml',
            $params
        );
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

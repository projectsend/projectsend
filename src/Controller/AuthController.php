<?php

namespace ProjectSend\Controller;

use Awurth\Slim\Helper\Controller\Controller;
use Slim\Http\Request;
use Slim\Http\Response;

class AuthController extends Controller
{
    public function login(Request $request, Response $response)
    {
        if ($request->isPost()) {
            $credentials = [
                'username' => $request->getParam('username'),
                'password' => $request->getParam('password')
            ];
            $remember = $request->getParam('remember') ? true : false;

            try {
                if ($this->auth->authenticate($credentials, $remember)) {
                    $this->flash('success', 'You are now logged in.');

                    return $this->redirect($response, 'home');
                } else {
                    $this->validator->addError('auth', 'Bad username or password');
                }
            } catch (ThrottlingException $e) {
                $this->validator->addError('auth', 'Too many attempts!');
            }
        }

        return $this->render($response, 'auth/login.twig');
    }

    public function register(Request $request, Response $response)
    {
        if ($request->isPost()) {
            $username = $request->getParam('username');
            $email = $request->getParam('email');
            $password = $request->getParam('password');

            $this->validator->request($request, [
                'username' => V::length(3, 25)->alnum('_')->noWhitespace(),
                'email' => V::noWhitespace()->email(),
                'password' => [
                    'rules' => V::noWhitespace()->length(6, 25),
                    'messages' => [
                        'length' => 'The password length must be between {{minValue}} and {{maxValue}} characters'
                    ]
                ],
                'password_confirm' => [
                    'rules' => V::equals($password),
                    'messages' => [
                        'equals' => 'Passwords don\'t match'
                    ]
                ]
            ]);

            if ($this->auth->findByCredentials(['login' => $username])) {
                $this->validator->addError('username', 'This username is already used.');
            }

            if ($this->auth->findByCredentials(['login' => $email])) {
                $this->validator->addError('email', 'This email is already used.');
            }

            if ($this->validator->isValid()) {
                /** @var \Cartalyst\Sentinel\Roles\EloquentRole $role */
                $role = $this->auth->findRoleByName('User');

                $user = $this->auth->registerAndActivate([
                    'username' => $username,
                    'email' => $email,
                    'password' => $password,
                    'permissions' => [
                        'user.delete' => 0
                    ]
                ]);

                $role->users()->attach($user);

                $this->flash('success', 'Your account has been created.');

                return $this->redirect($response, 'login');
            }
        }

        return $this->render($response, 'auth/register.twig');
    }

    public function logout(Request $request, Response $response)
    {
        $this->auth->logout();

        return $this->redirect($response, 'home');
    }

    public function unauthorized(Request $request, Response $response)
    {
        return $this->render($response, 'auth/unauthorized.twig');
    }
}

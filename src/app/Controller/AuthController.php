<?php

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController extends BaseController
{
    public function login(Request $request, Response $response, array $args = []): Response
    {
        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();

            if (empty($data['user_id']) || empty($data['user_pwd'])) {
                return $response->withStatus(302)->withHeader('Location', '/admin/login');
            }

            // Check the user username / pass
            if ($this->auth($data['user_id'], $data['user_pwd'])) {
                $session = $request->getAttribute('session');
                $session['logged'] = true;
                $session['user'] = [
                    'username' => $data['user_id'],
                    'is_admin' => true,
                ];

                return $response->withStatus(302)->withHeader('Location', '/admin');
            }

            return $response->withStatus(302)->withHeader('Location', '/admin/login');
        }
        return $this->view->render($response, 'admin/login.twig', ['user' => $request->getAttribute('user')]);
    }

    public function logout(Request $request, Response $response, array $args = []): Response
    {
        $session = $request->getAttribute('session');
        $session['logged'] = false;
        unset($session['user']);

        return $response->withStatus(302)->withHeader('Location', '/admin');
    }

    public function hash(Request $request, Response $response, array $args = []): Response
    {
        $response->getBody()->write(password_hash($args['pwd'], PASSWORD_BCRYPT));

        return $response->withHeader('Content-Type', 'text/plain');
    }

    private function auth(string $uname, string $pswd)
    {
        return $uname === $this->settings['admin_user'] && password_verify($pswd, $this->settings['admin_pass']);
    }
}

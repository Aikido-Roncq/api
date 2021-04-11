<?php

namespace App\Controllers;

use App\Attributes\Route;
use App\Exceptions\LoggedOutException;
use App\Models\Connections;
use App\Config;
use App\Exceptions\UnknownException;
use App\Exceptions\ValidationException;
use App\Middlewares\AuthMiddleware;
use Utils\Validation;
use PHPMailer\PHPMailer\PHPMailer;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Utils\Arrays;
use Utils\Http;

class UsersController extends Controller
{
  #[Route('/login', 'POST')]
  public function login(Request $req, Response $res)
  {
    if (AuthMiddleware::isLoggedIn($req))
      return $res->withStatus(Http::OK);

    $credentials = [];

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
      $authorization = substr($_SERVER['HTTP_AUTHORIZATION'], 6);
      [$login, $password] = explode(':', base64_decode($authorization));
      $credentials = compact('login', 'password');
    }

    $v = Validation::validate($credentials, [
      'required' => ['login', 'password']
    ], [
      'login' => 'Le login',
      'password' => 'Le mot de passe'
    ]);

    if (!$v->validate())
      throw new ValidationException($v->errors());

    [$user, $pw] = [$credentials['login'], $credentials['password']];

    if ($user != $_ENV['ADMIN_USER'] || $pw != $_ENV['ADMIN_PW']) {
      sleep(2); // prevent brutforce attacks
      return $res
        ->withHeader('WWW-Authenticate', 'Basic realm="Dashboard"')
        ->withStatus(Http::UNAUTHORIZED);
    }

    $connection = Connections::insert();
    $cookie = self::tokenToCookie($connection->token);

    return $res
      ->withHeader('Set-Cookie', $cookie)
      ->withStatus(Http::OK);
  }

  #[Route('/logout', 'POST')]
  public function logout(Request $req, Response $res)
  {
    try {
      Connections::revoke(self::extractToken($req));
    } catch (LoggedOutException $e) {
    }

    $cookie = self::tokenToCookie();

    return $res
      ->withHeader('Set-Cookie', $cookie)
      ->withStatus(Http::RESET_CONTENT);
  }

  private static function extractToken(Request $req): string
  {
    $cookies = $req->getCookieParams();

    if (!array_key_exists('token', $cookies))
      throw new LoggedOutException();

    return $cookies['token'];
  }

  private static function tokenToCookie(string $token = ''): string
  {
    $maxAge = Config::TOKEN_LIFETIME;
    $https = 'Secure';

    if (empty($token))
      $maxAge = 0;

    if (Config::ENV_IS_DEV())
      $https = null;

    return "token=$token; Max-Age=$maxAge; HttpOnly; $https";
  }

  #[Route('/contact', 'POST')]
  public function contact(Request $req, Response $res)
  {
    $data = $req->getParsedBody();

    $v = Validation::validate($data, [
      'required' => ['name', 'email', 'content'],
      'email' => ['email']
    ], [
      'name' => "Le prénom",
      'email' => "L'adresse email",
      'content' => "Le message"
    ]);

    if (!$v->validate())
      throw new ValidationException($v->errors());

    self::sendMail($data, [
      'from' => $data['email'],
      'to' => 'contact@aikido-roncq.fr',
      'subject' => 'Nouveau message via aikido-roncq.fr',
      'view' => 'mail',
    ]);

    self::sendMail($data, [
      'from' => 'no-reply@aikido-roncq.fr',
      'to' => $data['email'],
      'subject' => 'Prise en compte de votre message',
      'view' => 'confirm',
    ]);

    return self::send($res, [
      'message' => 'Votre message a été envoyé avec succès.'
    ]);
  }

  /**
   * @throws UnknownException
   * @throws Exception
   */
  private static function sendMail(array $data, array $options)
  {
    $data = Arrays::filterKeys($data, ['name', 'email', 'content']);
    $data['content'] = htmlentities($data['content']);

    $mail = new PHPMailer();
    $mail->CharSet = 'UTF-8';
    $mail->Mailer = $_ENV['MAILER'];
    $mail->Host = $_ENV['MAIL_HOST'];
    $mail->Port = $_ENV['MAIL_PORT'];
    $mail->SMTPAuth = false;
    $mail->SMTPAutoTLS = false;
    $mail->setFrom($options['from']);
    $mail->addAddress($options['to']);
    $mail->isHTML();
    $mail->Subject = $options['subject'];
    $mail->Body = self::getView($options['view'], $data);

    if (!$mail->send())
      throw new UnknownException();
  }
}

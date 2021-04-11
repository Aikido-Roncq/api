<?php

namespace App\Controllers;

use App\Models\Articles;
use App\Attributes\Route;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Utils\Arrays;
use Utils\Http;

#[Route('/articles')]
class ArticlesController extends Controller
{
  #[Route('[/]', 'GET')]
  public function all(Request $req, Response $res)
  {
    $articles = Articles::orderBy('date', 'desc');
    return self::send($res, $articles);
  }

  #[Route('/{slug}', 'GET')]
  public function find(Request $req, Response $res, array $args)
  {
    $article = Articles::find($args['slug']);
    return self::send($res, $article);
  }

  #[Route('[/]', 'POST', admin: true)]
  public function add(Request $req, Response $res)
  {
    $data = $req->getParsedBody();
    $article = Articles::insert($data);
    return self::send($res, $article, Http::CREATED);
  }

  #[Route('/{slug}', 'PATCH', admin: true)]
  public function edit(Request $req, Response $res, array $args)
  {
    $data = $req->getParsedBody();
    $data = Arrays::filterKeys($data, ['title', 'content']);
    $article = Articles::update($args['slug'], array_filter($data));
    return self::send($res, $article);
  }

  #[Route('/{slug}', 'DELETE', admin: true)]
  public function delete(Request $req, Response $res, array $args)
  {
    $article = Articles::delete($args['slug']);
    return self::send($res, $article);
  }
}

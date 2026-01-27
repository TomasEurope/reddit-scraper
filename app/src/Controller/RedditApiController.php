<?php

namespace App\Controller;

use App\Service\OpenSearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_', format: 'json')]
class RedditApiController extends AbstractController
{
    public function __construct(private readonly OpenSearchService $openSearch)
    {
    }

    #[Route('/posts', name: 'posts', methods: ['GET'])]
    public function posts(Request $request): JsonResponse
    {
        $q    = $request->query->get('q');
        $page = (int) ($request->query->get('page', 1));
        $size = (int) ($request->query->get('size', 16));

        $result = $this->openSearch->searchPosts($q, $page, $size);

        $response = new JsonResponse($result);
        // Simple CORS - allows cross-origin requests for the API.
        $response->headers->set('Access-Control-Allow-Origin', '*');
        return $response;
    }

    #[Route('/suggest', name: 'suggest', methods: ['GET'])]
    public function suggest(Request $request): JsonResponse
    {
        $q    = (string) $request->query->get('q', '');
        $size = (int) ($request->query->get('size', 8));

        $suggestions = $this->openSearch->suggestTitles($q, $size);
        $response    = new JsonResponse(['suggestions' => $suggestions]);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        return $response;
    }
}

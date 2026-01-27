<?php

namespace App\Controller;

use App\Entity\RedditPost;
use App\Service\OpenSearchService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class IndexController extends AbstractController
{
    public function __construct(
        private readonly OpenSearchService $openSearchService,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/', name: 'app_index')]
    #[Route('/page/{page}', name: 'app_index_page', requirements: ['page' => '\\d+'])]
    public function index(int $page = 1): Response
    {
        $pageSize = 16;
        $result = $this->openSearchService->searchPosts(null, $page, $pageSize);

        return $this->render('index.html.twig', [
            'posts'      => $result['items'],
            'total'      => $result['total'],
            'totalPages' => $result['totalPages'],
            'page'       => $result['page'],
        ]);
    }
}

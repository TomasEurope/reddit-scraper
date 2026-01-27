<?php

namespace App\EventListener;

use App\Entity\RedditPost;
use App\Service\OpenSearchService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: RedditPost::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: RedditPost::class)]
#[AsEntityListener(event: Events::postRemove, method: 'postRemove', entity: RedditPost::class)]
/**
 * Automatically indexes posts into OpenSearch when they are saved or changed in the database.
 */
class RedditPostIndexerListener
{
    public function __construct(
        private readonly OpenSearchService $openSearchService,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function postPersist(RedditPost $post): void
    {
        $this->index($post);
    }

    public function postUpdate(RedditPost $post): void
    {
        $this->index($post);
    }

    public function postRemove(RedditPost $post): void
    {
        try {
            $this->openSearchService->deletePost($post->getId());
        } catch (\Throwable $e) {
            $this->logger?->error('RedditPostIndexerListener: OpenSearch delete failed', [
                'id'    => $post->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function index(RedditPost $post): void
    {
        try {
            $this->openSearchService->indexRedditPost($post);
        } catch (\Throwable $e) {
            $this->logger?->error('RedditPostIndexerListener: OpenSearch index failed', [
                'id'    => $post->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}

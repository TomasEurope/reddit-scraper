<?php

namespace App\Service;

use App\Entity\RedditPost;
use App\Repository\RedditPostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class RedditPostManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RedditPostRepository   $posts,
        private readonly MediaService           $mediaService,
        private readonly ?LoggerInterface       $logger = null,
    ) {
    }

    /**
     * Processes Reddit payload, creates a post entity, downloads media, and saves to DB.
     * Checks for duplicates by 'fullname' and 'title'.
     */
    public function processPayload(array $payload): ?RedditPost
    {
        $fullname = (string)($payload['fullname'] ?? '');
        $title    = (string)($payload['title'] ?? '');
        $createdUtc = (int)($payload['created_utc'] ?? time());

        if ($fullname === '') {
            $this->logger?->warning('RedditPostManager: Missing fullname, skipping');
            return null;
        }

        if ($this->posts->findOneByFullname($fullname) || $this->posts->findOneBy(['title' => $title])) {
            $this->logger?->info('RedditPostManager: Duplicate found, skipping', ['fullname' => $fullname, 'title' => $title]);
            return null;
        }

        $createdAt = (new \DateTimeImmutable('@' . $createdUtc))->setTimezone(new \DateTimeZone('UTC'));

        $post = (new RedditPost())
            ->setFullname($fullname)
            ->setTitle($title)
            ->setUpvoteRatio((int)($payload['upvote_ratio'] ?? 0))
            ->setUps((int)($payload['ups'] ?? 0))
            ->setCreatedAtUtc($createdAt);

        $this->em->persist($post);

        if (!$this->mediaService->downloadAndProcess($post, $payload)) {
            $this->em->detach($post);
            if ($this->logger) {
                $this->logger->info('RedditPostManager: Media download failed, skipping', ['fullname' => $fullname]);
            }
            return null;
        }

        $this->em->flush();

        return $post;
    }
}

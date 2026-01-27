<?php

namespace App\Repository;

use App\Entity\RedditPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RedditPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RedditPost::class);
    }

    public function findOneByFullname(string $fullname): ?RedditPost
    {
        return $this->findOneBy(['fullname' => $fullname]);
    }
}

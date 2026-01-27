<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\\Repository\\RedditPostRepository')]
#[ORM\Table(name: 'reddit_post', options: ['collate' => 'utf8mb4_unicode_ci'])]
#[ORM\UniqueConstraint(name: 'uniq_reddit_fullname', columns: ['fullname'])]
#[ORM\UniqueConstraint(name: 'uniq_reddit_title', columns: ['title'], options: ['lengths' => [191]])]
class RedditPost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 30)]
    private string $fullname;

    #[ORM\Column(type: 'string', length: 512)]
    private string $title;

    #[ORM\Column(type: 'integer')]
    private int $upvoteRatio = 0;

    #[ORM\Column(type: 'integer')]
    private int $ups = 0;

    #[ORM\Column(type: 'string', length: 1024, nullable: true)]
    private ?string $localThumbnail = null;

    #[ORM\Column(type: 'string', length: 1024, nullable: true)]
    private ?string $localMp4 = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAtUtc;

    public function __construct()
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullname(): string
    {
        return $this->fullname;
    }
    public function setFullname(string $fullname): self
    {
        $this->fullname = $fullname;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getUpvoteRatio(): int
    {
        return $this->upvoteRatio;
    }
    public function setUpvoteRatio(int $upvoteRatio): self
    {
        $this->upvoteRatio = $upvoteRatio;
        return $this;
    }

    public function getUps(): int
    {
        return $this->ups;
    }
    public function setUps(int $ups): self
    {
        $this->ups = $ups;
        return $this;
    }

    public function getLocalThumbnail(): ?string
    {
        return $this->localThumbnail;
    }
    public function setLocalThumbnail(?string $localThumbnail): self
    {
        $this->localThumbnail = $localThumbnail;
        return $this;
    }

    public function getLocalMp4(): ?string
    {
        return $this->localMp4;
    }
    public function setLocalMp4(?string $localMP4): self
    {
        $this->localMp4 = $localMP4;
        return $this;
    }

    public function getCreatedAtUtc(): DateTimeImmutable
    {
        return $this->createdAtUtc;
    }
    public function setCreatedAtUtc(DateTimeImmutable $createdAtUtc): self
    {
        $this->createdAtUtc = $createdAtUtc;
        return $this;
    }
}

<?php

namespace App\Service;

use App\Entity\RedditPost;
use OpenSearch\Client;
use OpenSearch\SymfonyClientFactory;

class OpenSearchService
{
    private Client $client;

    public function __construct(
        string $host,
        private readonly string $indexName
    ) {
        $factory      = new SymfonyClientFactory();
        $this->client = $factory->create([
            'base_uri' => $host,
        ]);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function indexRedditPost(RedditPost $post): array
    {
        return $this->indexPost($this->mapPost($post));
    }

    /**
     * Maps a RedditPost entity to OpenSearch document.
     */
    public function mapPost(RedditPost $post): array
    {
        return [
            'id'              => $post->getId(),
            'fullname'        => $post->getFullname(),
            'title'           => $post->getTitle(),
            'ups'             => $post->getUps(),
            'upvote_ratio'    => $post->getUpvoteRatio(),
            'local_thumbnail' => $post->getLocalThumbnail(),
            'local_mp4'       => $post->getLocalMp4(),
            'created_at_utc'  => $post->getCreatedAtUtc()->format('c'),
        ];
    }

    public function indexPost(array $postData): array
    {
        return $this->client->index([
            'index' => $this->indexName,
            'id'    => $postData['id'] ?? null,
            'body'  => $postData,
        ]);
    }

    public function deletePost(int|string $id): array
    {
        return $this->client->delete([
            'index' => $this->indexName,
            'id'    => $id,
        ]);
    }

    /**
     * Removes the current OpenSearch index.
     */
    public function deleteIndex(): void
    {
        if ($this->client->indices()->exists(['index' => $this->indexName])) {
            $this->client->indices()->delete(['index' => $this->indexName]);
        }
    }

    /**
     * Initializes the OpenSearch index with proper mappings if it doesn't already exist.
     */
    public function createIndexIfNotExists(): void
    {
        if (!$this->client->indices()->exists(['index' => $this->indexName])) {
            $this->client->indices()->create([
                'index' => $this->indexName,
                'body'  => [
                    'mappings' => [
                        'properties' => [
                            'fullname'        => ['type' => 'keyword'],
                            'title'           => ['type' => 'text'],
                            'ups'             => ['type' => 'integer'],
                            'upvote_ratio'    => ['type' => 'integer'],
                            'local_thumbnail' => ['type' => 'keyword'],
                            'local_mp4'       => ['type' => 'keyword'],
                            'created_at_utc'  => ['type' => 'date'],
                        ]
                    ]
                ]
            ]);
        }
    }


    /**
     * Fulltext search in `title` field with pagination support.
     * If $q is empty, it uses match_all and sorting by date desc.
     * Otherwise, it uses match on title and relevance score.
     */
    public function searchPosts(?string $q, int $page = 1, int $size = 16): array
    {
        $page = max(1, $page);
        $size = min(max(1, $size), 50);
        $from = ($page - 1) * $size;

        $hasQuery = $q !== null && trim($q) !== '';
        $body     = [
            'from'  => $from,
            'size'  => $size,
            'query' => $hasQuery
                ? ['match' => ['title' => ['query' => $q, 'operator' => 'and']]]
                : ['match_all' => (object)[]],
        ];

        if (!$hasQuery) {
            $body['sort'] = [['created_at_utc' => ['order' => 'desc']]];
        }

        $res = $this->client->search([
            'index' => $this->indexName,
            'body'  => $body,
        ]);

        $total = is_array($res['hits']['total'] ?? null)
            ? (int)($res['hits']['total']['value'] ?? 0)
            : (int)($res['hits']['total'] ?? 0);

        $items = [];
        foreach (($res['hits']['hits'] ?? []) as $hit) {
            if (isset($hit['_source'])) {
                $items[] = $hit['_source'];
            }
        }

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'size'       => $size,
            'totalPages' => (int)max(1, ceil($total / $size)),
        ];
    }

    /**
     * Autocomplete suggestions over the `title` field using match_phrase_prefix.
     * Returns unique texts, limited by $size parameter.
     */
    public function suggestTitles(string $q, int $size = 8): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $size = min(max(1, $size), 20);

        $res = $this->client->search([
            'index' => $this->indexName,
            'body'  => [
                'size'  => $size,
                'query' => [
                    'match_phrase' => [
                        'title' => [
                            'query' => $q,
                            'slop'  => 2,
                        ]
                    ]
                ],
                '_source' => ['title']
            ]
        ]);

        $titles = [];
        foreach (($res['hits']['hits'] ?? []) as $hit) {
            $t = $hit['_source']['title'] ?? null;
            if (is_string($t)) {
                $titles[] = $t;
            }
        }
        // deduplication while preserving order
        $unique = [];
        $seen   = [];
        foreach ($titles as $t) {
            if (!isset($seen[$t])) {
                $seen[$t] = true;
                $unique[] = $t;
            }
        }
        return array_slice($unique, 0, $size);
    }
}

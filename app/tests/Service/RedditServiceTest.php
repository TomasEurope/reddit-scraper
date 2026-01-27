<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\RedditService;
use PHPUnit\Framework\TestCase;

final class RedditServiceTest extends TestCase
{
    private RedditService $redditService;

    protected function setUp(): void
    {
        $this->redditService = new RedditService('https://example.com', 500);
    }

    public function testMapPostToPayloadReturnsNullIfNameMissing(): void
    {
        $this->assertNull($this->redditService->mapPostToPayload([]));
    }

    public function testMapPostToPayloadReturnsNullIfThumbnailIsSelf(): void
    {
        $data = [
            'name' => 't3_123',
            'thumbnail' => 'self'
        ];
        $this->assertNull($this->redditService->mapPostToPayload($data));
    }

    public function testMapPostToPayloadMapsBasicData(): void
    {
        $data = [
            'name' => 't3_123',
            'title' => 'Test Title',
            'upvote_ratio' => 0.85,
            'ups' => 100,
            'thumbnail' => 'https://thumb.com/img.jpg',
            'created_utc' => 1600000000
        ];

        $result = $this->redditService->mapPostToPayload($data);

        $this->assertSame('t3_123', $result['fullname']);
        $this->assertSame('Test Title', $result['title']);
        $this->assertSame(85, $result['upvote_ratio']);
        $this->assertSame(100, $result['ups']);
        $this->assertSame('https://thumb.com/img.jpg', $result['thumbnail']);
        $this->assertSame(1600000000, $result['created_utc']);
    }

    public function testMapPostToPayloadParsesResolutions(): void
    {
        $data = [
            'name' => 't3_123',
            'preview' => [
                'images' => [
                    [
                        'resolutions' => [
                            ['url' => 'https://img1.com&amp;s=1', 'width' => 100, 'height' => 100],
                        ],
                        'variants' => [
                            'mp4' => [
                                'resolutions' => [
                                    ['url' => 'https://mp4-100.com', 'width' => 100, 'height' => 100],
                                    ['url' => 'https://mp4-600.com', 'width' => 600, 'height' => 600],
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->redditService->mapPostToPayload($data);

        $this->assertCount(1, $result['image_resolutions']);
        $this->assertSame('https://img1.com&s=1', $result['image_resolutions'][0]['url']);

        $this->assertCount(1, $result['mp4_resolutions']);
        $this->assertSame('https://mp4-600.com', $result['mp4_resolutions'][0]['url']);
        $this->assertSame(600, $result['mp4_resolutions'][0]['width']);
    }
}

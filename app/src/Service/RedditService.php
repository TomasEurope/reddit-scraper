<?php

namespace App\Service;

use App\Utils\RedditResolutionPicker;
use function is_array;

readonly class RedditService
{
    public function __construct(
        private string $baseUrl,
        private int $targetWidth
    ) {
    }
    /**
     * Fetches the latest posts from Reddit in JSON format.
     */
    public function fetchNewMp4s(?string $after, string $userAgent): ?array
    {
        $url = $this->baseUrl;
        if ($after) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'after=' . rawurlencode($after);
        }

        return $this->fetchJson($url, $userAgent);
    }

    private function fetchJson(string $url, string $userAgent): ?array
    {
        $context = stream_context_create([
            'http' => [
                'timeout'       => 20,
                'ignore_errors' => true,
                'header'        => [
                    'Accept: application/json',
                    'User-Agent: ' . $userAgent,
                ],
            ],
            'https' => [
                'timeout'       => 20,
                'ignore_errors' => true,
                'header'        => [
                    'Accept: application/json',
                    'User-Agent: ' . $userAgent,
                ],
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($data) ? $data : null;
    }


    /**
     * Maps Reddit API data into a simplified payload for further processing.
     */
    public function mapPostToPayload(array $cdata): ?array
    {
        $fullname = (string) ($cdata['name'] ?? '');
        if ($fullname === '') {
            return null;
        }

        $title       = (string) ($cdata['title'] ?? '');
        $upvoteRatio = (int) (round((float) ($cdata['upvote_ratio'] ?? 0) * 100));
        $ups         = (int) ($cdata['ups'] ?? 0);
        $thumbnail   = isset($cdata['thumbnail']) && is_string($cdata['thumbnail']) ? $cdata['thumbnail'] : null;

        if ($thumbnail === 'self' || $thumbnail === 'nsfw') {
            return null;
        }

        $createdUtc = (int) ($cdata['created'] ?? $cdata['created_utc'] ?? time());

        $imageResolutions = [];
        if (isset($cdata['preview']['images'][0]['resolutions']) && is_array($cdata['preview']['images'][0]['resolutions'])) {
            $parsed = $this->parseResolutions($cdata['preview']['images'][0]['resolutions']);
            if (!empty($parsed)) {
                $imageResolutions = [$parsed[0]];
            }
        }

        $mp4Data = $cdata['preview']['variants']['mp4']['resolutions']
            ?? $cdata['preview']['images'][0]['variants']['mp4']['resolutions']
            ?? [];

        $gifData = $cdata['preview']['variants']['gif']['resolutions']
            ?? $cdata['preview']['images'][0]['variants']['gif']['resolutions']
            ?? [];

        $mp4Resolutions = [];
        if (is_array($mp4Data) && !empty($mp4Data)) {
            $parsed = $this->parseResolutions($mp4Data);
            $closest = RedditResolutionPicker::findClosest($parsed, $this->targetWidth);
            if ($closest) {
                $mp4Resolutions = [$closest];
            }
        }

        $gifResolutions = [];
        if (is_array($gifData) && !empty($gifData)) {
            $parsed = $this->parseResolutions($gifData);
            $closest = RedditResolutionPicker::findClosest($parsed, $this->targetWidth);
            if ($closest) {
                $gifResolutions = [$closest];
            }
        }

        return [
            'fullname'          => $fullname,
            'title'             => $title,
            'upvote_ratio'      => $upvoteRatio,
            'ups'               => $ups,
            'thumbnail'         => $thumbnail,
            'created_utc'       => $createdUtc,
            'image_resolutions' => $imageResolutions,
            'mp4_resolutions'   => $mp4Resolutions,
            'gif_resolutions'   => $gifResolutions,
        ];
    }

    /**
     * Extracts available resolutions from the data array and returns an array of URLs and dimensions.
     */
    private function parseResolutions(array $resolutions): array
    {
        $payload = [];
        $pos     = 0;
        foreach ($resolutions as $res) {
            $url = isset($res['url']) ? html_entity_decode((string) $res['url']) : null;
            $w   = isset($res['width']) ? (int) $res['width'] : null;
            $h   = isset($res['height']) ? (int) $res['height'] : null;

            if (!$url || $w === null || $h === null) {
                $pos++;
                continue;
            }

            $payload[] = [
                'url'      => $url,
                'width'    => $w,
                'height'   => $h,
                'position' => $pos,
            ];
            $pos++;
        }

        return $payload;
    }
}

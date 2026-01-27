<?php

declare(strict_types=1);

namespace App\Tests\Utils;

use App\Utils\RedditResolutionPicker;
use PHPUnit\Framework\TestCase;

final class RedditResolutionPickerTest extends TestCase
{
    public function testFindClosestReturnsNullForEmptyArray(): void
    {
        $this->assertNull(RedditResolutionPicker::findClosest([], 100));
    }

    public function testFindClosestReturnsExactMatch(): void
    {
        $resolutions = [
            ['width' => 100, 'height' => 100],
            ['width' => 200, 'height' => 200],
            ['width' => 300, 'height' => 300],
        ];

        $result = RedditResolutionPicker::findClosest($resolutions, 200);
        $this->assertSame($resolutions[1], $result);
    }

    public function testFindClosestReturnsClosestMatch(): void
    {
        $resolutions = [
            ['width' => 100, 'height' => 100],
            ['width' => 200, 'height' => 200],
            ['width' => 500, 'height' => 500],
        ];

        $result = RedditResolutionPicker::findClosest($resolutions, 240);
        $this->assertSame($resolutions[1], $result);

        $result = RedditResolutionPicker::findClosest($resolutions, 400);
        $this->assertSame($resolutions[2], $result);
    }

    public function testFindClosestHandlesMissingWidth(): void
    {
        $resolutions = [
            ['height' => 100],
            ['width' => 200, 'height' => 200],
        ];

        // First item has width 0 (default in Picker), so 200 is closer to 150 than 0
        $result = RedditResolutionPicker::findClosest($resolutions, 150);
        $this->assertSame($resolutions[1], $result);
    }
}

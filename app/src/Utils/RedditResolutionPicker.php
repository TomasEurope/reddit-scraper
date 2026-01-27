<?php

namespace App\Utils;

class RedditResolutionPicker
{
    /**
     * Finds the resolution closest in width to the target value (targetWidth).
     */
    public static function findClosest(array $resolutions, int $targetWidth): ?array
    {
        $closest = null;
        $minDiff = null;

        foreach ($resolutions as $res) {
            $width = $res['width'] ?? 0;
            $diff  = abs($width - $targetWidth);

            if ($minDiff === null || $diff < $minDiff) {
                $minDiff = $diff;
                $closest = $res;
            }
        }

        return $closest;
    }
}

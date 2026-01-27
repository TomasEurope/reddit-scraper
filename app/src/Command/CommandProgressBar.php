<?php

namespace App\Command;

/**
 * Renders a simple progress bar in the terminal.
 */
class CommandProgressBar
{
    private int $total;

    private int $width;

    private float $startTime;

    private int $lastLength = 0;

    public function __construct(int $total, int $width = 50)
    {
        $this->total     = max(1, $total);
        $this->width     = $width;
        $this->startTime = microtime(true);
        echo "\r\n";
    }

    public function render(int $current): void
    {
        $current = min($current, $this->total);

        $percent = $current / $this->total;
        $filled  = (int) round($percent * $this->width);
        $empty   = $this->width - $filled;

        $bar = str_repeat('|', $filled) . str_repeat(' ', $empty);

        $barColor = $current >= $this->total ? '0;32' : '0;36';
        $reset    = "\033[0m";

        $line = sprintf(
            "\r%s / %s \033[%sm#%s#%s %5.1f%%",
            str_pad($current, 6),
            str_pad($this->total, 6),
            $barColor,
            $bar,
            $reset,
            $percent * 100
        );

        $pad = max(0, $this->lastLength - strlen($line));
        echo "\r" . $line . str_repeat(' ', $pad + 3);

        $this->lastLength = strlen($line);

        if ($current >= $this->total) {
            echo PHP_EOL;
        }
    }

}

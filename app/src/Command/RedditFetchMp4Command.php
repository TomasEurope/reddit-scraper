<?php

namespace App\Command;

use App\Message\RedditPayloadMessage;
use App\Service\RedditService;

use function is_array;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:reddit:fetch-mp4',
    description: 'Downloads JSON from /r/gifs (new) in a loop using "after" and saves the required data to the queue (MP4 variants).'
)]
class RedditFetchMp4Command extends Command
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly RedditService $redditService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('pages', null, InputOption::VALUE_REQUIRED, 'Maximum number of pages (API calls). 0 = no limit', '1')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of items to fetch. 0 = no limit', '0')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Pause between calls (seconds, can be float)', '1.0')
            ->addOption('user-agent', null, InputOption::VALUE_REQUIRED, 'HTTP User-Agent for Reddit', 'cc-fe-bot/1.0')
            ->addOption('debug', null, InputOption::VALUE_NONE, 'Prints debug information (page numbers)')
            ->addOption('output', null, InputOption::VALUE_NONE, 'Prints the full downloaded JSON to the console')
            ->addOption('progress', null, InputOption::VALUE_NONE, 'Displays graphical progress (globally for --limit or --pages, otherwise per page)');
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pagesLimit   = max(0, (int) $input->getOption('pages'));
        $itemLimit    = max(0, (int) $input->getOption('limit'));
        $sleep        = (float) $input->getOption('sleep');
        $userAgent    = (string) $input->getOption('user-agent');
        $debug        = (bool) $input->getOption('debug');
        $showOutput   = (bool) $input->getOption('output');
        $showProgress = (bool) $input->getOption('progress');

        $after          = null;
        $queued         = 0;
        $processedTotal = 0;
        $page           = 0;

        $globalBar   = null;
        $globalTotal = 0;
        if ($showProgress) {
            if ($itemLimit > 0) {
                $globalTotal = $itemLimit;
                $globalBar   = new CommandProgressBar($globalTotal);
            } elseif ($pagesLimit > 0) {
                $globalTotal = $pagesLimit * 25;
                $globalBar   = new CommandProgressBar($globalTotal);
            }
        }

        while (true) {
            if ($pagesLimit > 0 && $page >= $pagesLimit) {
                break;
            }

            if ($itemLimit > 0 && $processedTotal >= $itemLimit) {
                break;
            }

            $json = $this->redditService->fetchNewMp4s($after, $userAgent);
            if ($json === null) {
                $output->writeln('<error>Error downloading or parsing JSON</error>');
                break;
            }

            if ($debug) {
                $output->writeln("\n<comment>Page:</comment> " . ($page + 1));
            }

            if ($showOutput) {
                $output->writeln(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            $data = $json['data'] ?? null;
            if (!is_array($data)) {
                $output->writeln('<comment>Unexpected response format.</comment>');
                break;
            }

            $children = $data['children'] ?? [];
            if (!is_array($children) || count($children) === 0) {
                $output->writeln('<info>No more items.</info>');
                break;
            }

            $pageBar         = null;
            $processedOnPage = 0;
            if ($showProgress && $globalBar === null) {
                $pageBar = new CommandProgressBar(count($children));
            }

            foreach ($children as $child) {
                if ($itemLimit > 0 && $processedTotal >= $itemLimit) {
                    break 2;
                }
                $cdata = $child['data'] ?? null;
                $processedTotal++;

                if (!is_array($cdata)) {
                    if ($showProgress) {
                        if ($globalBar) {
                            $globalBar->render(min($processedTotal, $globalTotal));
                        } elseif ($pageBar) {
                            $processedOnPage++;
                            $pageBar->render($processedOnPage);
                        }
                    }
                    continue;
                }

                if ($showProgress) {
                    if ($globalBar) {
                        $globalBar->render(min($processedTotal, $globalTotal));
                    } elseif ($pageBar) {
                        $processedOnPage++;
                        $pageBar->render($processedOnPage);
                    }
                }

                $payload = $this->redditService->mapPostToPayload($cdata);
                if ($payload === null) {
                    continue;
                }

                $this->bus->dispatch(new RedditPayloadMessage($payload));
                $queued++;

            }
            $page++;

            $after = $data['after'] ?? null;
            if (!$after) {
                $output->writeln('<info>No more "after" - end.</info>');
                break;
            }

            if ($sleep > 0) {
                usleep((int) round($sleep * 1_000_000));
            }
        }

        if ($showProgress && $globalBar !== null && $processedTotal < $globalTotal) {
            $output->writeln('');
        }

        $output->writeln(sprintf('<info>Done. %d items dispatched to Messenger.</info>', $queued));
        return Command::SUCCESS;
    }
}

<?php

namespace App\Command;

use App\Service\OpenSearchService;
use App\Service\RabbitMQClient;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:reddit:flush',
    description: 'Deletes all data: PostgreSQL (reddit_post), OpenSearch index, RabbitMQ queue and files in public/reddit.'
)]
class RedditFlushCommand extends Command
{
    public function __construct(
        private readonly Connection       $db,
        private readonly OpenSearchService $openSearch,
        private readonly RabbitMQClient    $rabbit,
        #[Autowire('%kernel.project_dir%/public/reddit')]
        private readonly string $mediaDir,
        #[Autowire('%reddit.queue_name%')]
        private readonly string $queueName,
        #[Autowire('%opensearch.index_name%')]
        private readonly string $indexName,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>PostgreSQL:</info> TRUNCATE reddit_post ...');
        try {
            $this->db->executeStatement('TRUNCATE TABLE reddit_post RESTART IDENTITY CASCADE');
            $output->writeln('  -> OK');
        } catch (\Throwable $e) {
            $output->writeln('<error>  -> TRUNCATE error: ' . $e->getMessage() . '</error>');
            try {
                $this->db->executeStatement('DELETE FROM reddit_post');
                $output->writeln('  -> Fallback DELETE OK');
            } catch (\Throwable $e2) {
                $output->writeln('<error>  -> DELETE failed: ' . $e2->getMessage() . '</error>');
            }
        }

        $output->writeln(sprintf('<info>OpenSearch:</info> deleting %s index ...', $this->indexName));
        try {
            $this->openSearch->deleteIndex();
            $this->openSearch->createIndexIfNotExists();
            $output->writeln('  -> OK');
        } catch (\Throwable $e) {
            $output->writeln('<error>  -> OpenSearch error: ' . $e->getMessage() . '</error>');
        }

        $output->writeln('<info>RabbitMQ:</info> purge queue ' . $this->queueName . ' ...');
        try {
            $ch = $this->rabbit->getChannel();
            $ch->queue_declare($this->queueName, false, true, false, false);
            $ch->queue_purge($this->queueName);
            $output->writeln('  -> OK');
        } catch (\Throwable $e) {
            $output->writeln('<error>  -> RabbitMQ error: ' . $e->getMessage() . '</error>');
        }

        $output->writeln('<info>Files:</info> cleaning directory ' . $this->mediaDir . ' ...');
        try {
            $this->cleanDirectory($this->mediaDir);
            $output->writeln('  -> OK');
        } catch (\Throwable $e) {
            $output->writeln('<error>  -> File cleaning error: ' . $e->getMessage() . '</error>');
        }

        $output->writeln('<info>Done.</info>');
        return Command::SUCCESS;
    }

    private function cleanDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
            return;
        }

        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path) && !is_link($path)) {
                $this->recursiveRemove($path);
            } else {
                @unlink($path);
            }
        }
    }

    private function recursiveRemove(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            $items = scandir($path) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $this->recursiveRemove($path . DIRECTORY_SEPARATOR . $item);
            }
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}

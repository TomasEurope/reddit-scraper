<?php

namespace App\Command;

use App\Repository\RedditPostRepository;
use App\Service\OpenSearchService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:reddit:reindex',
    description: 'Reindex all posts from Doctrine to OpenSearch'
)]
class RedditReindexCommand extends Command
{
    public function __construct(
        private readonly RedditPostRepository $posts,
        private readonly OpenSearchService $openSearchService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Initializing index...');
        $this->openSearchService->deleteIndex();
        $this->openSearchService->createIndexIfNotExists();

        $allPosts = $this->posts->findAll();
        $count    = count($allPosts);
        $output->writeln(sprintf('Reindexing %d posts...', $count));

        $indexed = 0;
        foreach ($allPosts as $post) {
            $this->openSearchService->indexRedditPost($post);
            $indexed++;
            if ($indexed % 10 === 0) {
                $output->write('.');
            }
        }

        $output->writeln("\nDone.");
        return Command::SUCCESS;
    }
}

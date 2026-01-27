<?php

namespace App\MessageHandler;

use App\Message\RedditPayloadMessage;
use App\Service\RedditPostManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'async')]
readonly class RedditPayloadHandler
{
    public function __construct(
        private RedditPostManager $postManager,
    ) {
    }

    public function __invoke(RedditPayloadMessage $message): void
    {
        $this->postManager->processPayload($message->getPayload());
    }
}

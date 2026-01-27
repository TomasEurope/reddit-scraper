<?php

namespace App\Command;

use App\Service\RedditPostManager;
use App\Service\RabbitMQClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:reddit:import-mp4',
    description: 'Consume Reddit payloads from RabbitMQ and save to DB + OpenSearch'
)]
class RedditImportMp4Command extends Command
{
    public function __construct(
        private readonly RabbitMQClient     $rabbit,
        private readonly RedditPostManager  $postManager,
        #[Autowire('%reddit.queue_name%')]
        private readonly string $queueName,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of items to process (0 = no limit)', '0')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Pause between attempts when the queue is empty (seconds, can be float)', '0.2')
            ->addOption('debug', null, InputOption::VALUE_NONE, 'Prints debug information (skips, errors)')
            ->addOption('output', null, InputOption::VALUE_NONE, 'Prints the item payload (JSON) during processing')
            ->addOption('progress', null, InputOption::VALUE_NONE, 'Displays progress (graphical if --limit is set, otherwise text)');
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit      = max(0, (int) $input->getOption('limit'));
        $sleep      = (float) $input->getOption('sleep');
        $debug      = (bool) $input->getOption('debug');
        $showOutput = (bool) $input->getOption('output');

        $channel = $this->rabbit->getChannel();
        $channel->queue_declare($this->queueName, false, true, false, false);

        $processed = 0;
        $saved = 0;
        $savedSinceRefill = 0;
        $queueEmpty = true;
        $showProgress = (bool) $input->getOption('progress');

        $progressBar = null;
        if ($showProgress && $limit > 0) {
            $progressBar = new CommandProgressBar($limit);
        }

        $output->writeln(sprintf('<info>Starting import from queue: %s</info>', $this->queueName));

        while (true) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            if ($debug) {
                $output->writeln('<comment>Fetching next message...</comment>');
            }
            $msg = $channel->basic_get($this->queueName);
            $raw = $msg?->getBody();

            if ($raw === null) {
                if ($debug) {
                    $output->writeln('<comment>No message received.</comment>');
                }
                // nothing in the queue
                if ($queueEmpty === false) {
                    $output->writeln('<info>Queue is empty.</info>');
                }
                $queueEmpty = true;
                if ($sleep > 0) {
                    usleep((int) round($sleep * 1_000_000));
                }

                if ($limit === 0) {
                    continue;
                }
                break;
            }

            if ($queueEmpty) {
                $output->writeln('<info>Starting processing queue...</info>');
                $savedSinceRefill = 0;
                $queueEmpty       = false;
            }

            $processed++;
            if ($showProgress) {
                if ($progressBar) {
                    $progressBar->render($processed);
                } else {
                    $output->write(sprintf(
                        "\rTotal saved: %d, Last processed: %d   ",
                        $saved,
                        $savedSinceRefill
                    ));
                }
            }

            try {
                $payload = null;
                $data = json_decode($raw, true);

                // Attempt to decode the payload, which may be in various formats (clean JSON, Symfony Messenger Envelope, or PHP serialized object)
                if (is_array($data)) {
                    $payload = $data;
                    // Handle Symfony Messenger envelope (JSON version)
                    if (isset($data['body'])) {
                        $body = base64_decode($data['body']);
                        try {
                            $unserialized = unserialize($body, ['allowed_classes' => true]);
                            if ($unserialized instanceof \Symfony\Component\Messenger\Envelope) {
                                $message = $unserialized->getMessage();
                                if ($message instanceof \App\Message\RedditPayloadMessage) {
                                    $payload = $message->getPayload();
                                }
                            } elseif ($unserialized instanceof \App\Message\RedditPayloadMessage) {
                                $payload = $unserialized->getPayload();
                            }
                        } catch (\Throwable) {
                            $innerData = json_decode($body, true);
                            if (is_array($innerData)) {
                                $payload = $innerData['payload'] ?? $innerData;
                            }
                        }
                    }
                } else {
                    // Try direct PHP deserialization (for AMQP transport with default serializer)
                    try {
                        // If it looks like a serialized PHP object
                        if (str_starts_with($raw, 'O:')) {
                            $unserialized = @unserialize($raw, ['allowed_classes' => true]);

                            // If it failed and looks escaped, try to unescape it
                            if ($unserialized === false && (str_contains($raw, '\\0') || str_contains($raw, '\\"'))) {
                                $unserialized = @unserialize(stripcslashes($raw), ['allowed_classes' => true]);
                            }

                            if ($unserialized instanceof \Symfony\Component\Messenger\Envelope) {
                                $message = $unserialized->getMessage();
                                if ($message instanceof \App\Message\RedditPayloadMessage) {
                                    $payload = $message->getPayload();
                                }
                            } elseif ($unserialized instanceof \App\Message\RedditPayloadMessage) {
                                $payload = $unserialized->getPayload();
                            }
                        }
                    } catch (\Throwable $e) {
                        if ($debug) {
                            $output->writeln('<error>Unserialize failed: ' . $e->getMessage() . '</error>');
                        }
                    }
                }

                if (!is_array($payload) || !isset($payload['fullname'])) {
                    if ($debug) {
                        $output->writeln('<comment>Invalid payload - message discarded.</comment>');
                    }
                    if ($showOutput) {
                        $output->writeln('Raw body: ' . $raw);
                    }
                    $channel->basic_ack($msg->getDeliveryTag());
                    continue;
                }

                if ($showOutput) {
                    $output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                }

                $savedSinceRefill++;

                if ($debug) {
                    $output->writeln('<comment>Processing payload...</comment>');
                }
                if ($this->postManager->processPayload($payload)) {
                    $saved++;
                    if (!$showProgress && !$showOutput) {
                         $output->writeln(sprintf(' <info>✓</info> Saved: <comment>%s</comment> - %s', $payload['fullname'] ?? 'unknown', $payload['title'] ?? 'no title'));
                    }
                } else {
                    if ($debug) {
                        $output->writeln('<comment>Payload processed but NOT saved (duplicate, missing media, or download error).</comment>');
                    }
                }

                if ($debug) {
                    $output->writeln('<comment>Sending ACK...</comment>');
                }
                $channel->basic_ack($msg->getDeliveryTag());

                if ($showProgress && !$progressBar) {
                    $output->write(sprintf(
                        "\rTotal saved: %d, Last processed: %d   ",
                        $saved,
                        $savedSinceRefill
                    ));
                }
            } catch (\Throwable $e) {
                try {
                    $channel->basic_nack($msg->getDeliveryTag(), false, true);
                } catch (\Throwable $_) {
                }
                if ($debug) {
                    $output->writeln('<error>Error during saving, item returned to queue: ' . $e->getMessage() . '</error>');
                }

                if ($sleep > 0) {
                    usleep((int) round($sleep * 1_000_000));
                }
                continue;
            }
        }
        if ($showProgress && !$progressBar) {
            $output->writeln('');
        }
        $output->writeln(sprintf('<info>Done. Processed: %d, saved: %d.</info>', $processed, $saved));
        return Command::SUCCESS;
    }
}

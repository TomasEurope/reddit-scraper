<?php

namespace App\Service;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

final class RabbitMQClient
{
    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel             = null;

    public function __construct()
    {
    }

    /**
     * @throws \Exception
     */
    public function getChannel(): AMQPChannel
    {
        if ($this->channel !== null) {
            return $this->channel;
        }

        $host = getenv('RABBITMQ_HOST') ?: 'rabbitmq';
        $port = (int) (getenv('RABBITMQ_PORT') ?: 5672);
        $user = getenv('RABBITMQ_USER') ?: 'app';
        $pass = getenv('RABBITMQ_PASS') ?: 'app';

        $this->connection = new AMQPStreamConnection($host, $port, $user, $pass);
        $this->channel    = $this->connection->channel();

        return $this->channel;
    }

    /**
     * @throws \Exception
     */
    public function __destruct()
    {
        if ($this->channel !== null) {
            $this->channel->close();
        }
        if ($this->connection !== null) {
            $this->connection->close();
        }
    }
}

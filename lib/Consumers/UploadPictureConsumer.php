<?php

namespace Proklung\RabbitMq\Consumers;

use PhpAmqpLib\Message\AMQPMessage;
use Proklung\RabbitMq\RabbitMq\ConsumerInterface;

class UploadPictureConsumer implements ConsumerInterface
{
    public function execute(AMQPMessage $msg)
    {
        @unlink($_SERVER['DOCUMENT_ROOT'] . '/test.log');
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/test.log', $msg->getBody());

        echo ' [x] Received ', $msg->body, "\n";
    }
}
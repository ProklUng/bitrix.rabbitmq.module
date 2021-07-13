<?php

namespace Proklung\RabbitMq\Examples;

use PhpAmqpLib\Message\AMQPMessage;

/**
 * Class RandomIntServer
 * @package Proklung\RabbitMq\Examples
 */
class RandomIntServer
{
    public function execute(AMQPMessage $request)
    {
        return ['request_id' => mt_rand(1, 123)];
    }
}
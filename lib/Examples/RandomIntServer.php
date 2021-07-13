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
        $params = json_decode($request->getBody(), true);

        return ['request_id' => mt_rand(1, 123)];
    }
}
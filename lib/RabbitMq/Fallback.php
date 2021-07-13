<?php

namespace Proklung\RabbitMq\RabbitMq;

class Fallback implements ProducerInterface
{
    public function publish($msgBody, $routingKey = '', $additionalProperties = array())
    {
        return false;
    }
}

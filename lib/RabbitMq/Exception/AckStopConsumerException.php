<?php

namespace Proklung\RabbitMq\RabbitMq\Exception;


use Proklung\RabbitMq\RabbitMq\ConsumerInterface;

class AckStopConsumerException extends StopConsumerException
{
    public function getHandleCode()
    {
        return ConsumerInterface::MSG_ACK;
    }

}

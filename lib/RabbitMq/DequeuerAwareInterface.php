<?php

namespace Proklung\RabbitMq\RabbitMq;

interface DequeuerAwareInterface
{
    public function setDequeuer(DequeuerInterface $dequeuer);
}

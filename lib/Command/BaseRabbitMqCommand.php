<?php

namespace Proklung\RabbitMq\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;

abstract class BaseRabbitMqCommand extends Command
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @param ?ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }
}

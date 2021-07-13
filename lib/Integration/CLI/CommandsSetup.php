<?php

namespace Proklung\RabbitMq\Integration\CLI;

use Proklung\RabbitMq\Command;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CommandsSetup
 * @package Proklung\RabbitMq\CLI
 */
class CommandsSetup
{
    /**
     * @param ContainerInterface $container
     *
     * @return array
     */
    public static function load(ContainerInterface $container)
    {
        $commands = [
            new Command\ConsumerCommand(),
            new Command\DeleteCommand(),
            new Command\PurgeConsumerCommand(),
            new Command\SetupFabricCommand(),
            new Command\StdInProducerCommand(),
            new Command\RpcServerCommand(),
        ];

        foreach ($commands as $command) {
            if (!$command instanceof Command\BaseRabbitMqCommand) {
                continue;
            }

            $command->setContainer($container);
        }

        return $commands;
    }
}

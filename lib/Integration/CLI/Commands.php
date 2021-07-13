<?php
/**
 * @author RG. <rg.archuser@gmail.com>
 */

namespace Proklung\RabbitMq\Integration\CLI;

use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\DI\ServiceLocator;
use Proklung\RabbitMq\Command;

/**
 * Class Commands
 * @package Proklung\RabbitMq\CLI
 */
class Commands
{
    public static function onCommandsLoad(Event $event)
    {
        $container = ServiceLocator::getInstance();

        $commands = [
            new Command\ConsumerCommand(),
            new Command\DeleteCommand(),
            new Command\PurgeConsumerCommand(),
            new Command\SetupFabricCommand(),
            new Command\StdInProducerCommand(),
        ];

        foreach ($commands as $command) {
            if (!$command instanceof Command\BaseRabbitMqCommand) {
                continue;
            }

            $command->setContainer($container);
        }

        return new EventResult(EventResult::SUCCESS, $commands, 'yngc0der.rabbitmq');
    }
}

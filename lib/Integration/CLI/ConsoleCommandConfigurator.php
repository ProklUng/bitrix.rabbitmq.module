<?php

namespace Proklung\RabbitMq\Integration\CLI;

use Exception;
use IteratorAggregate;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

/**
 * Class ConsoleCommandConfigurator
 * @package Proklung\RabbitMq\Integration\CLI
 *
 */
class ConsoleCommandConfigurator
{
    /**
     * @var Application $application Конфигуратор консольных команд.
     */
    private $application;

    /**
     * @var Command[] $commands Команды.
     */
    private $commands;

    /**
     * ConsoleCommandConfigurator constructor.
     *
     * @param Application $application Конфигуратор консольных команд.
     * @param Command     ...$commands Команды.
     */
    public function __construct(
        Application $application,
        Command ...$commands
    ) {
        $this->application = $application;
        $this->commands = $commands;
    }

    /**
     * Инициализация команд.
     *
     * @return $this
     */
    public function init() : self
    {
        foreach ($this->commands as $command) {
            $this->application->add($command);
        }

        return $this;
    }

    /**
     * Запуск команд.
     *
     * @throws Exception
     */
    public function run() : void
    {
        $this->application->run();
    }

    /**
     * Добавить команды.
     *
     * @param mixed $commands Команды
     *
     * @return void
     *
     * @throws Exception
     * @since 24.12.2020 Рефакторинг.
     */
    public function add(...$commands) : void
    {
        $result = [];

        foreach ($commands as $command) {
            $array = $command;
            if ($command instanceof IteratorAggregate) {
                $iterator = $command->getIterator();
                $array = iterator_to_array($iterator);
            }

            $result[] = $array;
        }

        /** @psalm-suppress InvalidPropertyAssignmentValue */
        $this->commands = array_merge($this->commands, $result);
    }
}

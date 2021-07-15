<?php

namespace Proklung\RabbitMq\Command;

use Proklung\RabbitMq\RabbitMq\DynamicConsumer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SetupFabricCommand extends BaseRabbitMqCommand
{
    protected function configure()
    {
        $this
            ->setName('rabbitmq:setup-fabric')
            ->setDescription('Sets up the Rabbit MQ fabric')
            ->addOption('debug', 'd', InputOption::VALUE_NONE, 'Enable Debugging')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (defined('AMQP_DEBUG') === false) {
            define('AMQP_DEBUG', (bool) $input->getOption('debug'));
        }

        $output->writeln('Setting up the Rabbit MQ fabric');

        $partsHolder = $this->getContainer()->get('rabbitmq.parts_holder');

        foreach (array('base_amqp', 'binding') as $key) {
            foreach ($partsHolder->getParts('rabbitmq.' . $key) as $baseAmqp) {
                if ($baseAmqp instanceof DynamicConsumer) {
                    continue;
                }
                $baseAmqp->setupFabric();
            }
        }

    }
}

<?php
/**
 * @author RG. <rg.archuser@gmail.com>
 */

return [
    'parameters' => [
        'value' => [
            'rabbitmq.connection.class' => 'PhpAmqpLib\Connection\AMQPConnection',
            'rabbitmq.socket_connection.class' => 'PhpAmqpLib\Connection\AMQPSocketConnection',
            'rabbitmq.lazy.class' => 'PhpAmqpLib\Connection\AMQPLazyConnection',
            'rabbitmq.lazy.socket_connection.class' => 'PhpAmqpLib\Connection\AMQPLazySocketConnection',
            'rabbitmq.connection_factory.class' => 'Proklung\RabbitMq\RabbitMq\AMQPConnectionFactory',
            'rabbitmq.binding.class' => 'Proklung\RabbitMq\RabbitMq\Binding',
            'rabbitmq.producer.class' => 'Proklung\RabbitMq\RabbitMq\Producer',
            'rabbitmq.consumer.class' => 'Proklung\RabbitMq\RabbitMq\Consumer',
            'rabbitmq.multi_consumer.class' => '',
            'rabbitmq.dynamic_consumer.class' => '',
            'rabbitmq.batch_consumer.class' => 'Proklung\RabbitMq\RabbitMq\BatchConsumer',
            'rabbitmq.anon_consumer.class' => 'Proklung\RabbitMq\RabbitMq\AnonConsumer',
            'rabbitmq.rpc_client.class' => 'Proklung\RabbitMq\RabbitMq\RpcClient',
            'rabbitmq.rpc_server.class' => 'Proklung\RabbitMq\RabbitMq\RpcServer',
            'rabbitmq.logged.channel.class' => '',
            'rabbitmq.parts_holder.class' => 'Proklung\RabbitMq\RabbitMq\AmqpPartsHolder',
            'rabbitmq.fallback.class' => 'Proklung\RabbitMq\RabbitMq\Fallback',
            // Внутренние параметры модуля
            'cache_path' => '/bitrix/cache/s1/proklung.rabbitmq', // Путь к закешированному контейнеру
            'compile_container_envs' => ['prod'], // Окружения при которых компилировать контейнер
            'container.dumper.inline_factories' => false, // Дампить контейнер как одиночные файлы
        ],
        'readonly' => false,
    ],
    'services' => [
        'value' => [
            'rabbitmq.service_loader' => [
                'className' => 'Proklung\RabbitMq\Integration\DI\Services',
            ],
        ],
        'readonly' => false,
    ],
];

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
            'rabbitmq.batch_consumer.class' => '',
            'rabbitmq.anon_consumer.class' => '',
            'rabbitmq.rpc_client.class' => '',
            'rabbitmq.rpc_server.class' => '',
            'rabbitmq.logged.channel.class' => '',
            'rabbitmq.parts_holder.class' => 'Proklung\RabbitMq\RabbitMq\AmqpPartsHolder',
            'rabbitmq.fallback.class' => 'Proklung\RabbitMq\RabbitMq\Fallback',
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

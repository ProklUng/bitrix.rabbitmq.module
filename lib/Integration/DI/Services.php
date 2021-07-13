<?php

namespace Proklung\RabbitMq\Integration\DI;

use Bitrix\Main\Config\Configuration;
use Exception;
use Proklung\RabbitMQ\RabbitMq\Consumer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Proklung\RabbitMQ\Provider\ConnectionParametersProviderInterface;
use Proklung\RabbitMq\RabbitMq\AMQPConnectionFactory;
use Proklung\RabbitMq\RabbitMq\AmqpPartsHolder;
use Proklung\RabbitMq\RabbitMq\Binding;
use Proklung\RabbitMQ\RabbitMq\DequeuerAwareInterface;
use Proklung\RabbitMQ\RabbitMq\Producer;
use Proklung\RabbitMq\Utils\BitrixSettingsDiAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class Services
 * @package Proklung\RabbitMq\Integration\DI
 */
class Services
{
    /**
     * @var array $config
     */
    private $config;

    /**
     * @var array $parameters
     */
    private $parameters;

    /**
     * @var array $services
     */
    private $services;

    /**
     * @var ContainerBuilder $container Контейнер.
     */
    private $container;

    /**
     * @var boolean $booted Загружена ли уже конструкция.
     */
    private static $booted = false;

    /**
     * Services constructor.
     */
    public function __construct()
    {
        $this->config = Configuration::getInstance()->get('rabbitmq') ?? [];
        $this->parameters = Configuration::getInstance('proklung.rabbitmq')->get('parameters') ?? [];
        $this->services = Configuration::getInstance('proklung.rabbitmq')->get('services') ?? [];

        $this->container = new ContainerBuilder();
        $adapter = new BitrixSettingsDiAdapter();

        $adapter->importParameters($this->container, $this->config);
        $adapter->importParameters($this->container, $this->parameters);
        $adapter->importServices($this->container, $this->services);
    }

    /**
     * Загрузка и инициализация контейнера.
     *
     * @return ContainerBuilder
     * @throws Exception
     */
    public static function boot() : ContainerBuilder
    {
        $self = new static();

        if (!static::$booted) {
            $self->load();
            static::setBoot(true);
        }

        return $self->getContainer();
    }

    /**
     * Alias boot для читаемости.
     *
     * @return ContainerBuilder
     * @throws Exception
     */
    public static function getInstance() : ContainerBuilder
    {
        return static::boot();
    }

    /**
     * @param boolean $booted
     *
     * @return void
     */
    public static function setBoot(bool $booted) : void
    {
        static::$booted = $booted;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function load() : void
    {
        $this->loadConnections();
        $this->loadBindings();
        $this->loadProducers();
        $this->loadConsumers();
        $this->loadAnonConsumers();
        $this->loadBatchConsumers();
        $this->loadRpcClients();
        $this->loadRpcServers();

        $this->loadPartsHolder();

        $this->container->compile(false);
    }

    /**
     * Экземпляр контейнера.
     *
     * @return ContainerBuilder
     */
    public function getContainer(): ContainerBuilder
    {
        return $this->container;
    }

    /**
     * @return void
     * @throws Exception
     */
    private function loadPartsHolder() : void
    {
        $holder = function () {
            $className = $this->parameters['rabbitmq.parts_holder.class'];

            /** @var AmqpPartsHolder $instance */
            $instance = new $className();

            foreach ($this->config['bindings'] as $binding) {
                ksort($binding);
                $key = md5(json_encode($binding));

                $part = $this->container->get("rabbitmq.binding.{$key}");
                $instance->addPart('rabbitmq.binding', $part);
            }

            foreach ($this->config['producers'] as $key => $producer) {
                $part = $this->container->get("rabbitmq.{$key}_producer");
                $instance->addPart('rabbitmq.base_amqp', $part);
                $instance->addPart('rabbitmq.producer', $part);
            }

            foreach ($this->config['consumers'] as $key => $consumer) {
                $part = $this->container->get("rabbitmq.{$key}_consumer");
                $instance->addPart('rabbitmq.base_amqp', $part);
                $instance->addPart('rabbitmq.consumer', $part);
            }

            return $instance;
        };

        $this->container->set('rabbitmq.parts_holder', $holder());
    }

    /**
     * @return void
     * @throws Exception
     */
    private function loadConnections() : void
    {
        foreach ($this->config['connections'] as $key => $connection) {
            $connectionSuffix = $connection['use_socket'] ? 'socket_connection.class' : 'connection.class';
            $classParam = $connection['lazy']
                ? 'rabbitmq.lazy.' . $connectionSuffix
                : 'rabbitmq.' . $connectionSuffix;

            $factoryName = "rabbitmq.connection_factory.{$key}";
            $connectionName = "rabbitmq.connection.{$key}";

            $constructor = function () use ($classParam, $connection) {
                $className = $this->parameters['rabbitmq.connection_factory.class'];

                $parametersProvider = null;

                if (isset($connection['connection_parameters_provider'])) {
                    /** @var ConnectionParametersProviderInterface $parametersProvider */
                    $parametersProvider = $this->container->get($connection['connection_parameters_provider']);
                }

                /** @var AMQPConnectionFactory $instance */
                $instance = new $className(
                    $this->parameters[$classParam],
                    $connection,
                    $parametersProvider
                );

                return $instance;
            };

            $createConnector = function () use ($factoryName) {
                return $this->container->get($factoryName)->createConnection();
            };

            $this->container->set(
                $factoryName,
                $constructor()
            );

            $this->container->set(
                $connectionName,
                $createConnector()
            );
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    private function loadBindings() : void
    {
        if ($this->config['sandbox']) {
            return;
        }

        foreach ($this->config['bindings'] as $binding) {
            ksort($binding);
            $key = md5(json_encode($binding));

            if (!isset($binding['class'])) {
                $binding['class'] = $this->parameters['rabbitmq.binding.class'];
            }

            $binding = function () use ($binding) {
                $className = $binding['class'];
                $connectionName = "rabbitmq.connection.{$binding['connection']}";

                /** @var Binding $instance */
                $instance = new $className($this->container->get($connectionName));

                $instance->setArguments($binding['arguments']);
                $instance->setDestination($binding['destination']);
                $instance->setDestinationIsExchange($binding['destination_is_exchange']);
                $instance->setExchange($binding['exchange']);
                $instance->setNowait($binding['nowait']);
                $instance->setRoutingKey($binding['routing_key']);

                return $instance;
            };

            $this->container->set(
                "rabbitmq.binding.{$key}",
                $binding()
            );
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    private function loadProducers() : void
    {
        if (!isset($this->config['sandbox']) || $this->config['sandbox'] === false) {
            foreach ($this->config['producers'] as $key => $producer) {
                $producerServiceName = "rabbitmq.{$key}_producer";

                if (!isset($producer['class'])) {
                    $producer['class'] = $this->parameters['rabbitmq.producer.class'];
                }

                // this producer doesn't define an exchange -> using AMQP Default
                if (!isset($producer['exchange_options'])) {
                    $producer['exchange_options'] = $this->getDefaultExchangeOptions();
                }

                // this producer doesn't define a queue -> using AMQP Default
                if (!isset($producer['queue_options'])) {
                    $producer['queue_options'] = $this->getDefaultQueueOptions();
                }

                $producers = function () use ($producer) {
                    $className = $producer['class'];
                    $connectionName = "rabbitmq.connection.{$producer['connection']}";

                    /** @var Producer $instance */
                    $instance = new $className($this->container->get($connectionName));

                    $instance->setExchangeOptions($producer['exchange_options']);
                    $instance->setQueueOptions($producer['queue_options']);

                    if (isset($producer['auto_setup_fabric']) && !$producer['auto_setup_fabric']) {
                        $instance->disableAutoSetupFabric();
                    }

                    if (isset($producer['enable_logger']) && $producer['enable_logger']) {
                        $instance->setLogger($this->container->get($producer['logger']));
                    }

                    return $instance;
                };

                $this->container->set(
                    $producerServiceName,
                    $producers()
                );
            }
        } else {
            foreach ($this->config['producers'] as $key => $producer) {
                $this->container->register(
                    "rabbitmq.{$key}_producer",
                    $this->parameters['rabbitmq.fallback.class']
                )->setPublic(true);
            }
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    private function loadConsumers() : void
    {
        foreach ($this->config['consumers'] as $key => $consumer) {
            // this consumer doesn't define an exchange -> using AMQP Default
            if (!isset($consumer['exchange_options'])) {
                $consumer['exchange_options'] = $this->getDefaultExchangeOptions();
            }

            // this consumer doesn't define a queue -> using AMQP Default
            if (!isset($consumer['queue_options'])) {
                $consumer['queue_options'] = $this->getDefaultQueueOptions();
            }

            $consumers = function () use ($consumer) {
                $className = $this->parameters['rabbitmq.consumer.class'];
                $connectionName = "rabbitmq.connection.{$consumer['connection']}";

                /** @var Consumer $instance */
                $instance = new $className($this->container->get($connectionName));

                $instance->setExchangeOptions($consumer['exchange_options']);
                $instance->setQueueOptions($consumer['queue_options']);

                /** @var object $callback */
                // $callback = $this->container->get($consumer['callback']);
                $callback = new $consumer['callback'];

                $instance->setCallback([$callback, 'execute']);

                if (array_key_exists('qos_options', $consumer)) {
                    $instance->setQosOptions(
                        $consumer['qos_options']['prefetch_size'],
                        $consumer['qos_options']['prefetch_count'],
                        $consumer['qos_options']['global']
                    );
                }

                if (isset($consumer['idle_timeout'])) {
                    $instance->setIdleTimeout($consumer['idle_timeout']);
                }

                if (isset($consumer['idle_timeout_exit_code'])) {
                    $instance->setIdleTimeoutExitCode($consumer['idle_timeout_exit_code']);
                }

                if (isset($consumer['graceful_max_execution'])) {
                    $instance->setGracefulMaxExecutionDateTimeFromSecondsInTheFuture(
                        $consumer['graceful_max_execution']['timeout']
                    );
                    $instance->setGracefulMaxExecutionTimeoutExitCode(
                        $consumer['graceful_max_execution']['exit_code']
                    );
                }

                if (isset($consumer['auto_setup_fabric']) && !$consumer['auto_setup_fabric']) {
                    $instance->disableAutoSetupFabric();
                }

                if (isset($consumer['enable_logger']) && $consumer['enable_logger']) {
                    $instance->setLogger($this->container->get($consumer['logger']));
                }

                if ($this->isDequeverAwareInterface(get_class($callback))) {
                    /** @var DequeuerAwareInterface $callback */
                    $callback->setDequeuer($instance);
                }

                return $instance;
            };

            $this->container->set(
                "rabbitmq.{$key}_consumer",
                $consumers()
            );
        }
    }

    /**
     * @return void
     */
    private function loadRpcClients() : void
    {
        foreach ($this->config['rpc_clients'] as $key => $client) {
            $definition = new Definition('%rabbitmq.rpc_client.class%');
            $definition->setLazy($client['lazy']);
            $definition
                ->addTag('rabbitmq.rpc_client')
                ->addMethodCall('initClient', array($client['expect_serialized_response']));
            $this->injectConnection($definition, $client['connection']);

            if (array_key_exists('unserializer', $client)) {
                $definition->addMethodCall('setUnserializer', array($client['unserializer']));
            }

            if (array_key_exists('direct_reply_to', $client)) {
                $definition->addMethodCall('setDirectReplyTo', array($client['direct_reply_to']));
            }
            $definition->setPublic(true);

            $this->container->setDefinition(sprintf('rabbitmq.%s_rpc', $key), $definition);
        }
    }

    /**
     * @return void
     */
    private function loadRpcServers() : void
    {
        foreach ($this->config['rpc_servers'] as $key => $server) {
            $this->registerCallbackAsService($server['callback']);

            $definition = new Definition('%rabbitmq.rpc_server.class%');
            $definition
                ->setPublic(true)
                ->addTag('rabbitmq.base_amqp')
                ->addTag('rabbitmq.rpc_server')
                ->addMethodCall('initServer', array($key))
                ->addMethodCall('setCallback', array(
                        array(new Reference($server['callback']), 'execute'))
                );
            $this->injectConnection($definition, $server['connection']);

            if (array_key_exists('qos_options', $server)) {
                $definition->addMethodCall('setQosOptions', array(
                    $server['qos_options']['prefetch_size'],
                    $server['qos_options']['prefetch_count'],
                    $server['qos_options']['global']
                ));
            }

            if (array_key_exists('exchange_options', $server)) {
                $definition->addMethodCall('setExchangeOptions', array($server['exchange_options']));
            }

            if (array_key_exists('queue_options', $server)) {
                $definition->addMethodCall('setQueueOptions', array($server['queue_options']));
            }

            if (array_key_exists('serializer', $server)) {
                $definition->addMethodCall('setSerializer', array($server['serializer']));
            }

            $this->container->setDefinition(sprintf('rabbitmq.%s_server', $key), $definition);
        }
    }

    /**
     * @return void
     */
    private function loadAnonConsumers() : void
    {
        foreach ($this->config['anon_consumers'] as $key => $anon) {
            $this->registerCallbackAsService($anon['callback']);

            $definition = new Definition('%rabbitmq.anon_consumer.class%');
            $definition
                ->setPublic(true)
                ->addTag('rabbitmq.base_amqp')
                ->addTag('rabbitmq.anon_consumer')
                ->addMethodCall('setExchangeOptions', array($this->normalizeArgumentKeys($anon['exchange_options'])))
                ->addMethodCall('setCallback', array(array(new Reference($anon['callback']), 'execute')));
            $this->injectConnection($definition, $anon['connection']);

            $name = sprintf('rabbitmq.%s_anon', $key);
            $this->container->setDefinition($name, $definition);
            $this->addDequeuerAwareCall($anon['callback'], $name);
        }
    }

    /**
     * @return void
     */
    private function loadBatchConsumers() : void
    {
        foreach ($this->config['batch_consumers'] as $key => $consumer) {
            $this->registerCallbackAsService($consumer['callback']);

            $definition = new Definition('%rabbitmq.batch_consumer.class%');

            if (!isset($consumer['exchange_options'])) {
                $consumer['exchange_options'] = $this->getDefaultExchangeOptions();
            }

            $definition
                ->setPublic(true)
                ->addTag('rabbitmq.base_amqp')
                ->addTag('rabbitmq.batch_consumer')
                ->addMethodCall('setTimeoutWait', array($consumer['timeout_wait']))
                ->addMethodCall('setPrefetchCount', array($consumer['qos_options']['prefetch_count']))
                ->addMethodCall('setCallback', array(array(new Reference($consumer['callback']), 'batchExecute')))
                ->addMethodCall('setExchangeOptions', array($this->normalizeArgumentKeys($consumer['exchange_options'])))
                ->addMethodCall('setQueueOptions', array($this->normalizeArgumentKeys($consumer['queue_options'])))
                ->addMethodCall('setQosOptions', array(
                    $consumer['qos_options']['prefetch_size'],
                    $consumer['qos_options']['prefetch_count'],
                    $consumer['qos_options']['global']
                ))
            ;

            if (isset($consumer['idle_timeout_exit_code'])) {
                $definition->addMethodCall('setIdleTimeoutExitCode', array($consumer['idle_timeout_exit_code']));
            }

            if (isset($consumer['idle_timeout'])) {
                $definition->addMethodCall('setIdleTimeout', array($consumer['idle_timeout']));
            }

            if (isset($consumer['graceful_max_execution'])) {
                $definition->addMethodCall(
                    'setGracefulMaxExecutionDateTimeFromSecondsInTheFuture',
                    array($consumer['graceful_max_execution']['timeout'])
                );
            }

            if (!$consumer['auto_setup_fabric']) {
                $definition->addMethodCall('disableAutoSetupFabric');
            }

            if ($consumer['keep_alive']) {
                $definition->addMethodCall('keepAlive');
            }

            $this->injectConnection($definition, $consumer['connection']);

            if ($consumer['enable_logger']) {
                $this->injectLogger($definition);
            }

            $this->container->setDefinition(sprintf('rabbitmq.%s_batch', $key), $definition);
        }
    }

    /**
     * @param Definition $definition
     *
     * @return void
     */
    private function injectLogger(Definition $definition)
    {
        $definition->addTag('monolog.logger', array(
            'channel' => 'phpamqplib'
        ));
        $definition->addMethodCall('setLogger', array(new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)));
    }

    /**
     * Регистрация класса сервисом.
     *
     * @param string $class Класс.
     *
     * @return void
     */
    private function registerCallbackAsService(string $class)
    {
        // Регистрация class как сервиса.
        $defCallBack = new Definition($class);
        $defCallBack->setPublic(true);
        $this->container->setDefinition($class, $defCallBack);
    }

    /**
     * Symfony 2 converts '-' to '_' when defined in the configuration. This leads to problems when using x-ha-policy
     * parameter. So we revert the change for right configurations.
     *
     * @param array $config
     *
     * @return array
     */
    private function normalizeArgumentKeys(array $config)
    {
        if (isset($config['arguments'])) {
            $arguments = $config['arguments'];
            // support for old configuration
            if (is_string($arguments)) {
                $arguments = $this->argumentsStringAsArray($arguments);
            }

            $newArguments = array();
            foreach ($arguments as $key => $value) {
                if (strstr($key, '_')) {
                    $key = str_replace('_', '-', $key);
                }
                $newArguments[$key] = $value;
            }
            $config['arguments'] = $newArguments;
        }
        return $config;
    }

    /**
     * Support for arguments provided as string. Support for old configuration files.
     *
     * @deprecated
     * @param string $arguments
     * @return array
     */
    private function argumentsStringAsArray($arguments)
    {
        $argumentsArray = array();

        $argumentPairs = explode(',', $arguments);
        foreach ($argumentPairs as $argument) {
            $argumentPair = explode(':', $argument);
            $type = 'S';
            if (isset($argumentPair[2])) {
                $type = $argumentPair[2];
            }
            $argumentsArray[$argumentPair[0]] = array($type, $argumentPair[1]);
        }

        return $argumentsArray;
    }

    /**
     * Add proper dequeuer aware call.
     *
     * @param string $callback
     * @param string $name
     *
     * @return void
     */
    private function addDequeuerAwareCall($callback, $name) : void
    {
        if (!$this->container->has($callback)) {
            return;
        }

        $callbackDefinition = $this->container->findDefinition($callback);
        if ($this->isDequeverAwareInterface($callbackDefinition->getClass())) {
            $callbackDefinition->addMethodCall('setDequeuer', array(new Reference($name)));
        }
    }

    private function injectConnection(Definition $definition, $connectionName)
    {
        $definition->addArgument(new Reference(sprintf('rabbitmq.connection.%s', $connectionName)));
    }

    private function isDequeverAwareInterface(string $class): bool
    {
        $refClass = new \ReflectionClass($class);

        return $refClass->implementsInterface('Proklung\RabbitMq\RabbitMq\DequeuerAwareInterface');
    }

    private function getDefaultExchangeOptions(): array
    {
        return [
            'name' => '',
            'type' => 'direct',
            'passive' => true,
            'declare' => false,
        ];
    }

    private function getDefaultQueueOptions(): array
    {
        return [
            'name' => '',
            'declare' => false,
        ];
    }
}

<?php

namespace Proklung\RabbitMq\Integration\DI;

use Bitrix\Main\Config\Configuration;
use Exception;
use ProklUng\ContainerBoilerplate\DI\AbstractServiceContainer;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Proklung\RabbitMq\RabbitMq\Binding;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class Services
 * @package Proklung\RabbitMq\Integration\DI
 */
class Services extends AbstractServiceContainer
{
    /**
     * @var ContainerBuilder|null $container Контейнер.
     */
    protected static $container;

    /**
     * @var array $config Битриксовая конфигурация.
     */
    protected $config = [];

    /**
     * @var array $parameters Параметры битриксового сервис-локатора.
     */
    protected $parameters = [];

    /**
     * @var array $services Сервисы битриксового сервис-локатора.
     */
    protected $services = [];

    /**
     * @var string $moduleId ID модуля (переопределяется наследником).
     */
    protected $moduleId = 'proklung.rabbitmq';

    /**
     * Services constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->config = Configuration::getInstance()->get('rabbitmq') ?? [];
        $this->parameters = Configuration::getInstance($this->moduleId)->get('parameters') ?? [];
        $this->services = Configuration::getInstance($this->moduleId)->get('services') ?? [];

        // Инициализация параметров контейнера.
        $this->parameters['cache_path'] = $this->parameters['cache_path'] ?? '/bitrix/cache/proklung.rabbitmq';
        $this->parameters['container.dumper.inline_factories'] = $this->parameters['container.dumper.inline_factories'] ?? false;
        $this->parameters['compile_container_envs'] = (array)$this->parameters['compile_container_envs'];
    }

    /**
     * @return void
     * @throws Exception
     */
    public function initContainer() : void
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

        static::$container->compile(false);
    }

    /**
     * @return void
     * @throws Exception
     */
    private function loadPartsHolder() : void
    {
        if ($this->config['sandbox']) {
            return;
        }
        foreach ($this->config['bindings'] as $binding) {
            ksort($binding);
            $definition = new Definition($binding['class']);
            $definition->addTag('rabbitmq.binding');
            $definition->addMethodCall('setArguments', array($binding['arguments']));
            $definition->addMethodCall('setDestination', array($binding['destination']));
            $definition->addMethodCall('setDestinationIsExchange', array($binding['destination_is_exchange']));
            $definition->addMethodCall('setExchange', array($binding['exchange']));
            $definition->addMethodCall('isNowait', array($binding['nowait']));
            $definition->addMethodCall('setRoutingKey', array($binding['routing_key']));
            $this->injectConnection($definition, $binding['connection']);
            $key = md5(json_encode($binding));

            static::$container->setDefinition(sprintf('rabbitmq.binding.%s', $key), $definition);
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    private function loadConnections() : void
    {
        foreach ($this->config['connections'] as $key => $connection) {
            $connectionSuffix = $connection['use_socket'] ? 'socket_connection.class' : 'connection.class';
            $classParam =
                $connection['lazy']
                    ? '%rabbitmq.lazy.'.$connectionSuffix.'%'
                    : '%rabbitmq.'.$connectionSuffix.'%';

            $definition = new Definition('%rabbitmq.connection_factory.class%', array(
                $classParam, $connection,
            ));
            if (isset($connection['connection_parameters_provider'])) {
                $definition->addArgument(new Reference($connection['connection_parameters_provider']));
                unset($connection['connection_parameters_provider']);
            }
            $definition->setPublic(true);
            $factoryName = sprintf('rabbitmq.connection_factory.%s', $key);
            static::$container->setDefinition($factoryName, $definition);

            $definition = new Definition($classParam);
            if (method_exists($definition, 'setFactory')) {
                // to be inlined in services.xml when dependency on Symfony DependencyInjection is bumped to 2.6
                $definition->setFactory(array(new Reference($factoryName), 'createConnection'));
            } else {
                // to be removed when dependency on Symfony DependencyInjection is bumped to 2.6
                $definition->setFactoryService($factoryName);
                $definition->setFactoryMethod('createConnection');
            }
            $definition->addTag('rabbitmq.connection');
            $definition->setPublic(true);

            static::$container->setDefinition(sprintf('rabbitmq.connection.%s', $key), $definition);
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
                $instance = new $className(static::$container->get($connectionName));

                $instance->setArguments($binding['arguments']);
                $instance->setDestination($binding['destination']);
                $instance->setDestinationIsExchange($binding['destination_is_exchange']);
                $instance->setExchange($binding['exchange']);
                $instance->setNowait($binding['nowait']);
                $instance->setRoutingKey($binding['routing_key']);

                return $instance;
            };

            static::$container->set(
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
        if ($this->config['sandbox'] == false) {
            foreach ($this->config['producers'] as $key => $producer) {
                $definition = new Definition($producer['class'] ?? static::$container->getParameter('rabbitmq.producer.class'));
                $definition->setPublic(true);
                $definition->addTag('rabbitmq.base_amqp');
                $definition->addTag('rabbitmq.producer');
                //this producer doesn't define an exchange -> using AMQP Default
                if (!isset($producer['exchange_options'])) {
                    $producer['exchange_options'] = $this->getDefaultExchangeOptions();
                }
                $definition->addMethodCall('setExchangeOptions', array($this->normalizeArgumentKeys($producer['exchange_options'])));
                //this producer doesn't define a queue -> using AMQP Default
                if (!isset($producer['queue_options'])) {
                    $producer['queue_options'] = $this->getDefaultQueueOptions();
                }
                $definition->addMethodCall('setQueueOptions', array($producer['queue_options']));
                $this->injectConnection($definition, $producer['connection']);

                if (!$producer['auto_setup_fabric']) {
                    $definition->addMethodCall('disableAutoSetupFabric');
                }

                if ($producer['enable_logger']) {
                    $this->injectLogger($definition);
                }

                $producerServiceName = sprintf('rabbitmq.%s_producer', $key);

                static::$container->setDefinition($producerServiceName, $definition);
                if (null !== $producer['service_alias']) {
                    static::$container->setAlias($producer['service_alias'], $producerServiceName);
                }
            }
        } else {
            foreach ($this->config['producers'] as $key => $producer) {
                $definition = new Definition('%rabbitmq.fallback.class%');
                static::$container->setDefinition(sprintf('rabbitmq.%s_producer', $key), $definition);
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
            $this->registerCallbackAsService($consumer['callback']);

            $definition = new Definition('%rabbitmq.consumer.class%');
            $definition->setPublic(true);
            $definition->addTag('rabbitmq.base_amqp');
            $definition->addTag('rabbitmq.consumer');
            //this consumer doesn't define an exchange -> using AMQP Default
            if (!isset($consumer['exchange_options'])) {
                $consumer['exchange_options'] = $this->getDefaultExchangeOptions();
            }
            $definition->addMethodCall('setExchangeOptions', array($this->normalizeArgumentKeys($consumer['exchange_options'])));
            //this consumer doesn't define a queue -> using AMQP Default
            if (!isset($consumer['queue_options'])) {
                $consumer['queue_options'] = $this->getDefaultQueueOptions();
            }
            $definition->addMethodCall('setQueueOptions', array($this->normalizeArgumentKeys($consumer['queue_options'])));
            $definition->addMethodCall('setCallback', array(array(new Reference($consumer['callback']), 'execute')));

            if (array_key_exists('qos_options', $consumer)) {
                $definition->addMethodCall('setQosOptions', array(
                    $consumer['qos_options']['prefetch_size'],
                    $consumer['qos_options']['prefetch_count'],
                    $consumer['qos_options']['global']
                ));
            }

            if (isset($consumer['idle_timeout'])) {
                $definition->addMethodCall('setIdleTimeout', array($consumer['idle_timeout']));
            }
            if (isset($consumer['idle_timeout_exit_code'])) {
                $definition->addMethodCall('setIdleTimeoutExitCode', array($consumer['idle_timeout_exit_code']));
            }
            if (isset($consumer['timeout_wait'])) {
                $definition->addMethodCall('setTimeoutWait', array($consumer['timeout_wait']));
            }
            if (isset($consumer['graceful_max_execution'])) {
                $definition->addMethodCall(
                    'setGracefulMaxExecutionDateTimeFromSecondsInTheFuture',
                    array($consumer['graceful_max_execution']['timeout'])
                );
                $definition->addMethodCall(
                    'setGracefulMaxExecutionTimeoutExitCode',
                    array($consumer['graceful_max_execution']['exit_code'])
                );
            }
            if (!$consumer['auto_setup_fabric']) {
                $definition->addMethodCall('disableAutoSetupFabric');
            }

            $this->injectConnection($definition, $consumer['connection']);

            if ($consumer['enable_logger']) {
                $this->injectLogger($definition);
            }

            $name = sprintf('rabbitmq.%s_consumer', $key);
            static::$container->setDefinition($name, $definition);
            $this->addDequeuerAwareCall($consumer['callback'], $name);
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

            static::$container->setDefinition(sprintf('rabbitmq.%s_rpc', $key), $definition);
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

            static::$container->setDefinition(sprintf('rabbitmq.%s_server', $key), $definition);
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
            static::$container->setDefinition($name, $definition);
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

            static::$container->setDefinition(sprintf('rabbitmq.%s_batch', $key), $definition);
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
        static::$container->setDefinition($class, $defCallBack);
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
     * @throws ReflectionException
     */
    private function addDequeuerAwareCall($callback, $name) : void
    {
        if (!static::$container->has($callback)) {
            return;
        }

        $callbackDefinition = static::$container->findDefinition($callback);
        if ($this->isDequeverAwareInterface($callbackDefinition->getClass())) {
            $callbackDefinition->addMethodCall('setDequeuer', array(new Reference($name)));
        }
    }

    /**
     * @param Definition $definition
     * @param mixed      $connectionName
     *
     * @return void
     */
    private function injectConnection(Definition $definition, $connectionName)
    {
        $definition->addArgument(new Reference(sprintf('rabbitmq.connection.%s', $connectionName)));
    }

    /**
     * @param string $class
     *
     * @return boolean
     *
     * @throws ReflectionException
     */
    private function isDequeverAwareInterface(string $class): bool
    {
        $refClass = new ReflectionClass($class);

        return $refClass->implementsInterface('Proklung\RabbitMq\RabbitMq\DequeuerAwareInterface');
    }

    /**
     * @return array
     */
    private function getDefaultExchangeOptions(): array
    {
        return [
            'name' => '',
            'type' => 'direct',
            'passive' => true,
            'declare' => false,
        ];
    }

    /**
     * @return array
     */
    private function getDefaultQueueOptions(): array
    {
        return [
            'name' => '',
            'declare' => false,
        ];
    }
}
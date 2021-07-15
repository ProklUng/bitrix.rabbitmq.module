# Форк [пакета](https://github.com/yngc0der/bitrix-rabbitmq)

## Зачем?

Оригинальный модуль заточен под битриксовый сервис-локатор, а он встречается только на версиях главного модуля больше,
чем `20.5.400`. На моих проектах такой свежести нет, а функционал интересный и полезный. 

## Отличия

- Выпилил битриксовый сервис-локатор в пользу отдельного симфонического контейнера
- Исправил некоторое количество ошибок
- Добавлен отдельный раннер консольных команд
- Адаптирована работа с `RPC_Server` и `RPC_Clients` 
- Адаптирована работа с `Anon consumer`
- Адаптирована работа с `Batch consumer`
- Внутренний контейнер кэшируется 

`.settings.php` модуля:

```php
return [
    'parameters' => [
        'value' => [
            
            'cache_path' => '/bitrix/cache/s1/proklung.rabbitmq', // Путь к закешированному контейнеру
            'compile_container_envs' => ['dev', 'prod'], // Окружения при которых компилировать контейнер
            'container.dumper.inline_factories' => false, // Дампить контейнер как одиночные файлы
        ],
        'readonly' => false,
    ]
];
```

Параметр `cache_path` - путь, куда ляжет скомпилированный контейнер. Если не задано, то по умолчанию `/bitrix/cache/s1/proklung.rabbitmq`.

Предполагается, что в системе так или иначе установлена переменная среды `DEBUG` в массиве `$_ENV`. Если нет, то по умолчанию
 полагается, что среда "отладочная".
 
Параметр (массив) `compile_container_envs` указывает окружения, при которых необходимо кэшировать контейнер.

Пока простая логика: `$_ENV["DEBUG"] === true` => окружение `dev`, иначе `prod`. 

# Оригинальное readme.MD с некоторыми корректировками

## О проекте

Модуль включает в себя обмен сообщениями в вашем php-приложении через [RabbitMQ](http://www.rabbitmq.com/) 
с использованием библиотеки [php-amqplib](http://github.com/php-amqplib/php-amqplib).

Пакет реализует шаблоны обмена сообщениями, приведенными в библиотеке [Thumper](https://github.com/php-amqplib/Thumper),
что позволяет сделать публикацию сообщений в RabbitMQ из вашего контроллера очень простой:

```php
use Proklung\RabbitMq\Integration\DI\Services;

$msg = ['user_id' => 1235, 'image_path' => '/path/to/new/pic.png'];
Services::getInstance()->get('rabbitmq.upload_picture_producer')->publish(serialize($msg));
```

Для получения 50-ти сообщений из очереди `upload_pictures`, вы просто запускаете слушатель:

```php
use Proklung\RabbitMq\Integration\DI\Services;

$consumer = Services::getInstance()->get('rabbitmq.upload_picture_consumer');
$consumer->consume(50);
```

Данные примеры требуют запущенного сервера RabbitMQ.

## Минимальные требования
* `php-7.1.3` или выше

## Установка

Загрузите пакет, используя пакетный менеджер composer:

composer.json основного проекта:

```json
  "extra": {
    "installer-paths": {
      "./bitrix/modules/{$name}/": ["type:bitrix-d7-module", "type:bitrix-module"],
      "./bitrix/components/{$name}/": ["type:bitrix-d7-component", "type:bitrix-component"],
      "./bitrix/templates/{$name}/": ["type:bitrix-d7-template", "type:bitrix-theme"]
    },
    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/proklung/bitrix.rabbitmq.module"
        },
    {
      "type": "git",
      "url": "https://github.com/proklung/bitrix.containerable.boilerplate"
    }
    ]
  }
```

```bash
$ composer require proklung/bitrix.rabbitmq.module
```

Установите модуль `proklung.rabbitmq` в административном интерфейсе сайта `bitrix/admin/partner_modules.php`

Добавьте следующий код в ваш `init.php`:

```php
use Bitrix\Main\Loader;
use Proklung\RabbitMq\Integration\DI\Services;

if (Loader::includeModule('proklung.rabbitmq')) {
    Services::boot();
}
```

## Использование
Конфигурация идентична родительскому пакету. Настройка производится посредством правки файлов `bitrix/.settings.php`
и `bitrix/.settings_extra.php`:

```php
return [
    'rabbitmq' => [
        'value' => [
            'connections' => [
                'default' => [
                    'host' => '172.17.0.2',
                    'port' => 5672,
                    'user' => 'guest',
                    'password' => 'guest',
                    'vhost' => '/',
                    'lazy' => false,
                    'connection_timeout' => 3.0,
                    'read_write_timeout' => 3.0,
                    'keepalive' => false,
                    'heartbeat' => 0,
                    'use_socket' => true,
                ],
            ],
            'producers' => [
                'upload_picture' => [
                    'connection' => 'default',
                    'exchange_options' => [
                        'name' => 'upload_picture',
                        'type' => 'direct',
                    ],
                ],
            ],
            'consumers' => [
                'upload_picture' => [
                    'connection' => 'default',
                    'exchange_options' => [
                        'name' => 'upload_picture',
                        'type' => 'direct',
                    ],
                    'queue_options' => [
                        'name' => 'upload_picture',
                    ],
                    // Автоматом регистрируется сервисом. Без обработки зависимостей.
                    'callback' => 'Proklung\RabbitMq\Consumers\UploadPictureConsumer',
                ],
            ],
            'rpc_clients' => [
                'integer_store' => [
                    'connection' => 'default',
                    'unserializer' => 'json_decode',
                    'lazy' => true,
                    'direct_reply_to' => false,
                    'expect_serialized_response' => false
                ],
            ],
            'rpc_servers' => [
                'random_int' => [
                    'connection' => 'default',
                    // Автоматом регистрируется сервисом. Без обработки зависимостей.
                    'callback' => 'Proklung\RabbitMq\Examples\RandomIntServer',
                    'qos_options' => [
                        'prefetch_size' => 0,
                        'prefetch_count' => 1,
                        'global' => false
                    ],
                    'exchange_options' => [
                        'name' => 'random_int',
                        'type' => 'topic',
                    ],
                    'queue_options' => [
                        'name' => 'random_int_queue',
                        'durable' => false,
                        'auto_delete' => true,
                    ],
                    'serializer' => 'json_encode',
                ],
            ],
        ],
        'readonly' => false,
    ],
];
```

Пример обработчика сообщений:

```php
// UploadPictureConsumer.php

use Proklung\RabbitMq\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

class UploadPictureConsumer implements ConsumerInterface
{
    public function execute(AMQPMessage $msg)
    {
        echo ' [x] Received ', $msg->body, "\n";
    }
}
```

Пример серверной части RPC сообщений (при опции клиента `expect_serialized_response` равной `false`):

```php

use PhpAmqpLib\Message\AMQPMessage;

class RandomIntServer
{
    public function execute(AMQPMessage $request)
    {
        $params = json_decode($request->getBody(), true);
        
        return ['request_id' => mt_rand(1, 123)];
    }
}
```

Отправка запроса и получение ответа от RPC:

1) Запустить сервер командой `php bin/rabbitmq bitrix-rabbitmq:rpc-server random_int`
2) Код:

```php
    use Proklung\RabbitMq\Integration\DI\Services;
    use Proklung\RabbitMq\RabbitMq\RpcClient;

    /** @var RpcClient $client */
    $client = Services::boot()->get('rabbitmq.integer_store_rpc');

    $client->addRequest(serialize(array('min' => 0, 'max' => 10)), 'random_int', 'request_id');
    $replies = $client->getReplies();
    // Обработать $replies
```

## Интеграция с CLI

Доступны некоторые команды, которые упрощают работу:

* `rabbitmq:consumer`        Executes a consumer
* `rabbitmq:delete`          Delete a consumer's queue
* `rabbitmq:purge`           Purge a consumer's queue
* `rabbitmq:setup-fabric`     Sets up the Rabbit MQ fabric
* `rabbitmq:stdin-producer`  Executes a producer that reads data from STDIN
* `rabbitmq:rpc-server`      Start RPC server

В папке `/install/bin` модуля лежит файл `rabbitmq`. При установке модуля система попробует скопировать его в директорию,
`bin`, лежащую двумя уровнями выше `DOCUMENT_ROOT`. Если такой директории не существует, то сделано ничего не будет. Придется
создать папку руками и скопировать туда файл вручную. 

Запуск:

```bash
   php bin/rabbitmq bitrix-abbitmq:setup-fabric
```

Все доступные команды:

```bash
   php bin/rabbitmq
```

## Адаптировано к Bitrix
- [x] Connection (Stream, Socket, Lazy, LazySocket)
- [x] Connection factory
- [x] Binding
- [x] Producer
- [x] Consumer
- [x] Parts holder
- [x] Fallback producer
- [ ] Multi-consumer
- [ ] Dynamic consumer
- [x] Batch consumer
- [x] Anon consumer
- [x] Rpc client
- [x] Rpc server
- [ ] Logged channel

## Credits
Модуль и документация базируется на [RabbitMqBundle](https://github.com/php-amqplib/RabbitMqBundle).
Там же вы можете найти подробную информацию о его использовании.

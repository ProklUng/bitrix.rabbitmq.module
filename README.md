# Форк [пакета](https://github.com/yngc0der/bitrix-rabbitmq)

## Зачем?

Оригинальный модуль заточен под битриксовый сервис-локатор, а он встречается только на версиях главного модуля больше,
чем `20.5.400`. На моих проектах такой свежести нет, а функционал интересный и полезный. 

## Отличия

Выпилил битриксовый сервис-локатор в пользу отдельного симфонического контейнера.

Исправил некоторое количество ошибок.

Добавил к командам префикс `bitrix-`, чтобы избежать конфликта с командами оригинального бандла `RabbitMqBundle`. 

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

composer.json основного проектаЖ

```json
  "extra": {
    "installer-paths": {
      "./bitrix/modules/{$name}/": ["type:bitrix-d7-module", "type:bitrix-module"],
      "./bitrix/components/{$name}/": ["type:bitrix-d7-component", "type:bitrix-component"],
      "./bitrix/templates/{$name}/": ["type:bitrix-d7-template", "type:bitrix-theme"]
    }
  }
```

composer.json:

```json
    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/proklung/bitrix.rabbitmq.module"
        }
    ]
```

```bash
$ composer require proklung/bitrix-rabbitmq-module
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
                    // В оригинале тянулся сервис из битриксового сервис-локатора
                    // Пока упростил - класс инстанцируется через new.
                    'callback' => 'Proklung\RabbitMq\Consumers\UploadPictureConsumer',
                ],
            ],
            'rpc_clients' => [
                'integer_store' => [
                    'connection' => 'default',
                    'unserializer' => 'json_decode',
                    'lazy' => true,
                    'direct_reply_to' => false
                ],
            ],
            'rpc_servers' => [
                'random_int' => [
                    'connection' => 'default',
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

## Интеграция с CLI

Доступны некоторые команды, которые упрощают работу:

* `bitrix-rabbitmq:consumer`        Executes a consumer
* `bitrix-rabbitmq:delete`          Delete a consumer's queue
* `bitrix-rabbitmq:purge`           Purge a consumer's queue
* `bitrix-abbitmq:setup-fabric`     Sets up the Rabbit MQ fabric
* `bitrix-rabbitmq:stdin-producer`  Executes a producer that reads data from STDIN
* `bitrix-rabbitmq:rpc-server`      Start RPC server

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
- [ ] Batch consumer
- [ ] Anon consumer
- [x] Rpc client (в разработке)
- [x] Rpc server (в разработке)
- [ ] Logged channel

## Credits
Модуль и документация базируется на [RabbitMqBundle](https://github.com/php-amqplib/RabbitMqBundle).
Там же вы можете найти подробную информацию о его использовании.

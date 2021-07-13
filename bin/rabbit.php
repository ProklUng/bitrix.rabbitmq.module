<?php

use Bitrix\Main\Loader;
use Proklung\RabbitMq\Integration\CLI\CommandsSetup;
use Proklung\RabbitMq\Integration\CLI\ConsoleCommandConfigurator;
use Proklung\RabbitMq\Integration\CLI\LoaderBitrix;
use Proklung\RabbitMq\Integration\DI\Services;
use Symfony\Component\Console\Application;

@set_time_limit(0);

$_SERVER['DOCUMENT_ROOT'] = __DIR__. DIRECTORY_SEPARATOR . '..';
$GLOBALS['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'];

$autoloadPath = $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

/** @noinspection PhpIncludeInspection */
require_once $autoloadPath;

/**
 * Загрузить Битрикс.
 */
$loaderBitrix = new LoaderBitrix();
$loaderBitrix->setDocumentRoot($_SERVER['DOCUMENT_ROOT']);
$loaderBitrix->initializeBitrix();

if (!$loaderBitrix->isBitrixLoaded()) {
    exit('Bitrix not initialized.');
}

Loader::includeModule('proklung.rabbitmq');

$application = new ConsoleCommandConfigurator(
    new Application(),
    CommandsSetup::load(Services::getInstance())
);

$application->init();
$application->run();
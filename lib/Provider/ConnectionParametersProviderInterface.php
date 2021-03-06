<?php

namespace Proklung\RabbitMq\Provider;

/**
 * Interface to provide and/or override connection parameters.
 *
 * @author David Cochrum <davidcochrum@gmail.com>
 */
interface ConnectionParametersProviderInterface
{
    /**
     * Return connection parameters.
     *
     * Example:
     * array(
     *   'host' => 'localhost',
     *   'port' => 5672,
     *   'user' => 'guest',
     *   'password' => 'guest',
     *   'vhost' => '/',
     *   'lazy' => false,
     *   'connection_timeout' => 3,
     *   'read_write_timeout' => 3,
     *   'keepalive' => false,
     *   'heartbeat' => 0,
     *   'use_socket' => true,
     *   'constructor_args' => array(...)
     * )
     *
     * If constructor_args is present, all the other parameters are ignored; constructor_args are passes as constructor
     * arguments.
     *
     * @return array
     */
    public function getConnectionParameters();
}

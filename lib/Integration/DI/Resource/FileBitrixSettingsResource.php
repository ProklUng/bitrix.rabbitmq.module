<?php

namespace Proklung\RabbitMq\Integration\DI\Resource;

use Symfony\Component\Config\Resource\SelfCheckingResourceInterface;

/**
 * @final
 */
final class FileBitrixSettingsResource implements SelfCheckingResourceInterface
{
    /**
     * @var string|false
     */
    private $resource;

    /**
     * @var integer $timestamp
     */
    private $timestamp;

    /**
     * @param string $resource The file path to the resource.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $resource)
    {
        $this->resource = realpath($resource) ?: (file_exists($resource) ? $resource : false);
        
        if (false === $this->resource) {
            throw new \InvalidArgumentException(sprintf('The file "%s" does not exist.', $resource));
        }
        
        $this->timestamp = (int)filemtime($this->resource);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return $this->resource;
    }

    /**
     * @return string The canonicalized, absolute path to the resource.
     */
    public function getResource(): string
    {
        return $this->resource;
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh(int $timestamp): bool
    {
        return false !== @filemtime($this->resource) && $this->timestamp >= $timestamp;
    }
}

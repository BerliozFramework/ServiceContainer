<?php

declare(strict_types=1);

namespace Berlioz\ServiceContainer\Inflector;

/**
 * Class Inflector.
 */
class Inflector
{
    public function __construct(
        protected string $interface,
        protected string $method,
        protected array $arguments = [],
    ) {
    }

    /**
     * Get interface.
     *
     * @return string
     */
    public function getInterface(): string
    {
        return $this->interface;
    }

    /**
     * Get method.
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get arguments.
     *
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
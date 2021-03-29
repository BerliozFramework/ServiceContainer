<?php

declare(strict_types=1);

namespace Berlioz\ServiceContainer\Exception;

/**
 * Class ArgumentException.
 */
class ArgumentException extends InstantiatorException
{
    /**
     * Missing argument.
     *
     * @param string $name
     *
     * @return static
     */
    public static function missingArgument(string $name): static
    {
        return new static(sprintf('Missing argument "%s"', $name));
    }
}
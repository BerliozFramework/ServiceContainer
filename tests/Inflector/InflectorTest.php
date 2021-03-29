<?php

namespace Berlioz\ServiceContainer\Tests\Inflector;

use Berlioz\ServiceContainer\Inflector\Inflector;
use PHPUnit\Framework\TestCase;

class InflectorTest extends TestCase
{
    public function test()
    {
        $inflector = new Inflector(
            $interface = 'INTERFACE',
            $method = 'METHOD',
            $arguments = ['foo' => 'bar', 'baz' => 'qux']
        );

        $this->assertEquals($interface, $inflector->getInterface());
        $this->assertEquals($method, $inflector->getMethod());
        $this->assertEquals($arguments, $inflector->getArguments());
    }
}

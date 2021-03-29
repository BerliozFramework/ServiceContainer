<?php

namespace Berlioz\ServiceContainer\Tests\Container;

use Berlioz\ServiceContainer\Container\AutoWiringContainer;
use Berlioz\ServiceContainer\Exception\NotFoundException;
use Berlioz\ServiceContainer\Tests\Asset\WithDependency;
use Berlioz\ServiceContainer\Tests\Asset\WithoutConstructor;
use PHPUnit\Framework\TestCase;

class AutoWiringContainerTest extends TestCase
{
    public function testGet()
    {
        $container = new AutoWiringContainer();

        $this->assertInstanceOf(WithDependency::class, $service = $container->get(WithDependency::class));
        $this->assertSame($service, $container->get(WithDependency::class));

        $this->assertInstanceOf(WithoutConstructor::class, $service2 = $container->get(WithoutConstructor::class));
        $this->assertSame($service2, $container->get(WithoutConstructor::class));
        $this->assertSame($service2, $service->param);
    }

    public function testGetUnknown()
    {
        $this->expectException(NotFoundException::class);

        $container = new AutoWiringContainer();
        $container->get('UnknownClass');
    }

    public function testHas()
    {
        $container = new AutoWiringContainer();
        $container->get(WithoutConstructor::class);

        $this->assertTrue($container->has(WithoutConstructor::class));
        $this->assertTrue($container->has(WithDependency::class));
        $this->assertFalse($container->has('UnknownClass'));
    }
}

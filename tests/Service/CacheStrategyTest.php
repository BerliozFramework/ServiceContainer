<?php

namespace Berlioz\ServiceContainer\Tests\Service;

use Berlioz\ServiceContainer\Exception\ContainerException;
use Berlioz\ServiceContainer\Service\CacheStrategy;
use Berlioz\ServiceContainer\Service\Service;
use Berlioz\ServiceContainer\Tests\Asset\Service4;
use PHPUnit\Framework\TestCase;
use stdClass;

class CacheStrategyTest extends TestCase
{
    public function test()
    {
        $service = new Service(Service4::class);
        $cacheStrategy = new CacheStrategy(new MemoryCacheDriver());

        $this->assertNull($cacheStrategy->get($service));

        $cacheStrategy->set($service, $obj = new Service4());

        $this->assertSame($obj, $cacheStrategy->get($service));
    }

    public function testCacheIntegrity()
    {
        $this->expectException(ContainerException::class);

        $service = new Service(Service4::class);
        $cacheStrategy = new CacheStrategy(new MemoryCacheDriver());

        $cacheStrategy->set($service, new stdClass());
        $cacheStrategy->get($service);
    }
}

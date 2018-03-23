<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\ServiceContainer\Tests;

use Berlioz\ServiceContainer\Exception\ContainerException;
use Berlioz\ServiceContainer\Exception\NotFoundException;
use Berlioz\ServiceContainer\ServiceContainer;
use Berlioz\ServiceContainer\Tests\files\Service1;
use Berlioz\ServiceContainer\Tests\files\Service2;
use Berlioz\ServiceContainer\Tests\files\Service3;
use Berlioz\ServiceContainer\Tests\files\ServiceInterface;
use PHPUnit\Framework\TestCase;

class ServiceContainerTest extends TestCase
{
    private function getConfig()
    {
        $json = <<<'EOD'
{
  "aliasService1": {
    "class": "\\Berlioz\\ServiceContainer\\Tests\\files\\Service1",
    "arguments": {
      "param1": "test",
      "param2": "test",
      "param3": 1 
    }
  },
  "aliasService2": {
    "class": "\\Berlioz\\ServiceContainer\\Tests\\files\\Service2",
    "arguments": {
      "param3": false,
      "param1": "test"
    }
  }
}
EOD;

        return json_decode($json, true);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function testRegisterObjectAsService()
    {
        $serviceContainer = new ServiceContainer;
        $serviceContainer->registerObjectAsService($service = new Service1('test', 'test2', 1), 'alias1');
        $this->assertTrue($serviceContainer->has('alias1'));
        $this->assertEquals($service, $serviceContainer->get('alias1'));
        $this->assertTrue($serviceContainer->has('alias1'));
        $this->assertFalse($serviceContainer->has('alias3'));
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function testRegisterServices()
    {
        $serviceContainer = new ServiceContainer;
        $serviceContainer->registerServices($this->getConfig());
        $this->assertInstanceOf(Service1::class, $service1 = $serviceContainer->get('aliasService1'));
        $this->assertInstanceOf(Service2::class, $service2 = $serviceContainer->get('aliasService2'));
        $this->assertEquals($service1, $service2->param2);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function testRegisterService()
    {
        $config = $this->getConfig();

        $serviceContainer = new ServiceContainer;
        $serviceContainer->registerService($config['aliasService1'], 'service');
        $this->assertInstanceOf('\Berlioz\ServiceContainer\Tests\files\Service1', $service = $serviceContainer->get('service'));
        $this->assertEquals($service, $serviceContainer->get('service'));
        $this->assertTrue($serviceContainer->has('service'));
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    public function testMakeConfiguration()
    {
        $serviceContainer = new ServiceContainer;
        $reflection = new \ReflectionClass($serviceContainer);
        $method = $reflection->getMethod('makeConfiguration');
        $method->setAccessible(true);

        $result = $method->invokeArgs($serviceContainer, [json_decode(<<<'EOD'
{
  "aliasService1": {
    "arguments": {
      "param1": "test",
      "param2": "test",
      "param3": 1 
    }
  }
}
EOD
            , true)]);
        $this->assertFalse($result);

        $result = $method->invokeArgs($serviceContainer, $this->getConfig());
        $this->assertNotFalse($result);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    public function testCreateService()
    {
        $json = <<<'EOD'
{
  "class": "\\Berlioz\\ServiceContainer\\Tests\\files\\Service1",
  "arguments": {
    "param1": "test",
    "param2": "test",
    "param3": 1 
  }
}
EOD;
        $serviceContainer = new ServiceContainer;
        $reflection = new \ReflectionClass($serviceContainer);
        $method = $reflection->getMethod('createService');
        $method->setAccessible(true);

        $result = $method->invokeArgs($serviceContainer, [json_decode($json, true)]);
        $this->assertInstanceOf('\Berlioz\ServiceContainer\Tests\files\Service1', $result);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    public function testCreateServiceException()
    {
        $this->expectException(NotFoundException::class);
        $json = <<<'EOD'
{
  "class": "\\Berlioz\\ServiceContainer\\Tests\\files\\Service666",
  "arguments": {
    "param1": "test",
    "param2": "test",
    "param3": 1 
  }
}
EOD;
        $serviceContainer = new ServiceContainer;
        $reflection = new \ReflectionClass($serviceContainer);
        $method = $reflection->getMethod('createService');
        $method->setAccessible(true);
        $method->invokeArgs($serviceContainer, [json_decode($json, true)]);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    public function testCheckConstraints()
    {
        $serviceContainer = new ServiceContainer($this->getConfig());
        $serviceContainer->addConstraint('service', ServiceInterface::class);
        $reflection = new \ReflectionClass($serviceContainer);
        $method = $reflection->getMethod('checkConstraints');
        $method->setAccessible(true);

        $method->invokeArgs($serviceContainer, ['service', Service2::class]);

        $this->expectException(ContainerException::class);

        $method->invokeArgs($serviceContainer, ['service', Service1::class]);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function testDependencyInjection()
    {
        $serviceContainer = new ServiceContainer;
        $service = $serviceContainer->dependencyInjection(Service3::class,
                                                          ['param1' => 'test',
                                                           'param2' => 'test',
                                                           'param3' => 3,
                                                           'param4' => 'test']);
        $this->assertInstanceOf(Service3::class, $service);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function testDependencyInjection_optionalParameters()
    {
        $serviceContainer = new ServiceContainer;
        $service = $serviceContainer->dependencyInjection(Service3::class,
                                                          ['param1' => 'test',
                                                           'param2' => 'test']);
        $this->assertInstanceOf(Service3::class, $service);
        $this->assertNull($service->param3);
        $this->assertEquals('test', $service->param4);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function testDependencyInjection_missingParameter()
    {
        $this->expectException(ContainerException::class);
        $serviceContainer = new ServiceContainer;
        $serviceContainer->dependencyInjection(Service3::class,
                                               ['param1' => 'test',
                                                'param4' => 'test2']);
    }
}

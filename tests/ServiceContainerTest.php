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
    },
    "calls": [
      {
        "method": "increaseParam3",
        "arguments": {
          "nb": 5
        }
      }
    ]
  },
  "aliasService1X": {
    "class": "\\Berlioz\\ServiceContainer\\Tests\\files\\Service1",
    "arguments": {
      "param1": "another",
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
  },
  "aliasServiceX": {
    "class": "\\Berlioz\\ServiceContainer\\Tests\\files\\Service2",
    "arguments": {
      "param3": false,
      "param1": "test",
      "param2": "@aliasService1X"
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
        $serviceContainer->register('alias1', $service = new Service1('test', 'test2', 1));
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
        $serviceContainer->register('service', $config['aliasService1']['class'], $config['aliasService1']['arguments']);
        $this->assertInstanceOf('\Berlioz\ServiceContainer\Tests\files\Service1', $service = $serviceContainer->get('service'));
        $this->assertEquals($service, $serviceContainer->get('service'));
        $this->assertTrue($serviceContainer->has('service'));
    }

    public function testCallsService()
    {
        $serviceContainer = new ServiceContainer($this->getConfig());
        $service1 = $serviceContainer->get('aliasService1');
        $this->assertEquals(6, $service1->getParam3());
    }

    public function testRecursivelyServices()
    {
        $serviceContainer = new ServiceContainer($this->getConfig());
        $service1 = $serviceContainer->get(Service1::class);
        $service1X = $serviceContainer->get('aliasService1X');
        $serviceX = $serviceContainer->get('aliasServiceX');

        $this->assertNotEquals($service1, $serviceX->getParam2());
        $this->assertEquals($service1X, $serviceX->getParam2());
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
    public function testNewInstanceOf()
    {
        $serviceContainer = new ServiceContainer;
        $service = $serviceContainer->newInstanceOf(Service3::class,
                                                    ['param1' => 'test',
                                                     'param2' => 'test',
                                                     'param3' => 3,
                                                     'param4' => 'test']);
        $this->assertInstanceOf(Service3::class, $service);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function testNewInstanceOfWithNotNamedParameters()
    {
        $service1 = new Service1('param1', 'param2', 1);
        $serviceContainer = new ServiceContainer;
        $service = $serviceContainer->newInstanceOf(Service2::class,
                                                    ['param1'   => 'test',
                                                     'aService' => $service1]);
        $this->assertInstanceOf(Service2::class, $service);
        $this->assertEquals($service1, $service->getParam2());
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function testNewInstanceOf_optionalParameters()
    {
        $serviceContainer = new ServiceContainer;
        $service = $serviceContainer->newInstanceOf(Service3::class,
                                                    ['param1' => 'test',
                                                     'param2' => 'test']);
        $this->assertInstanceOf(Service3::class, $service);
        $this->assertNull($service->param3);
        $this->assertEquals('test', $service->param4);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function testNewInstanceOf_missingParameter()
    {
        $this->expectException(ContainerException::class);
        $serviceContainer = new ServiceContainer;
        $serviceContainer->newInstanceOf(Service3::class,
                                         ['param1' => 'test',
                                          'param4' => 'test2']);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function testInvokeMethod()
    {
        $serviceContainer = new ServiceContainer($this->getConfig());
        $service = $serviceContainer->get(Service2::class);
        $result = $serviceContainer->invokeMethod($service,
                                                  'test',
                                                  ['param' => ($str = 'toto')]);
        $this->assertEquals(sprintf('It\'s a test "%s"', $str), $result);
    }
}

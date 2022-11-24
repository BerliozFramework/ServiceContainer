# Berlioz Service Container

[![Latest Version](https://img.shields.io/packagist/v/berlioz/service-container.svg?style=flat-square)](https://github.com/BerliozFramework/ServiceContainer/releases)
[![Software license](https://img.shields.io/github/license/BerliozFramework/ServiceContainer.svg?style=flat-square)](https://github.com/BerliozFramework/ServiceContainer/blob/2.x/LICENSE)
[![Build Status](https://img.shields.io/github/workflow/status/BerliozFramework/ServiceContainer/Tests/2.x.svg?style=flat-square)](https://github.com/BerliozFramework/ServiceContainer/actions/workflows/tests.yml?query=branch%3A2.x)
[![Quality Grade](https://img.shields.io/codacy/grade/cb21d20358cc4ba2be5ab42bf0ddb8b2/2.x.svg?style=flat-square)](https://www.codacy.com/manual/BerliozFramework/ServiceContainer)
[![Total Downloads](https://img.shields.io/packagist/dt/berlioz/service-container.svg?style=flat-square)](https://packagist.org/packages/berlioz/service-container)

**Berlioz Service Container** is a PHP library to manage your services with dependencies injection, respecting PSR-11 (
Container interface) standard.

For more information, and use of Berlioz Framework, go to website and online documentation :
https://getberlioz.com

## Installation

### Composer

You can install **Berlioz Service Container** with [Composer](https://getcomposer.org/), it's the recommended
installation.

```bash
$ composer require berlioz/service-container
```

### Dependencies

* **PHP** ^8.0
* Packages:
    * **psr/container**
    * **psr/simple-cache**

## Usage

### Container

Methods available from PSR-11:

- `get($id)`

  > PSR-11: Finds an entry of the container by its identifier and returns it.
  >
  > **Accept a class name.**

- `has($id)`

  > PSR-11: Returns true if the container can return an entry for the given identifier.
  > Returns false otherwise.

### Add a service

You can add a service with `Container::add()` method.

```php
use Berlioz\ServiceContainer\Container;

$container = new Container();
$service = $container->add(MyService::class, 'alias'); // Returns a Berlioz\ServiceContainer\Service\Service object
```

Or with method `Container::addService()`, who accept a `Service` object.

```php
use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Service\Service;

$container = new Container();
$service = new Service(MyService::class, 'alias');
$container->addService($service);
```

### Service object

- ```
  Service::public function __construct(
      string|object $class,
      ?string $alias = null,
      callable|string|null $factory = null,
      ?CacheStrategy $cacheStrategy = null,
  )
  ```

  > Constructor of Service object.

- `Service::setNullable(bool $nullable = true): Service`

  > Service can be null after factory execution (false by default).

- `Service::setShared(bool $shared = true): Service`

  > Share a service, always same instance returned for a shared service.

- `Service::addProvide(string ...$provide): Service`

  > Add provided class/interfaces/alias by service.

- `Service::addArgument(string $name, mixed $value): Service`

  > Add argument to make an instance of service class.

- `Service::addArguments(array $arguments): Service`

  > It's an array of arguments, the key must be the name of argument, and the value of key, must be the argument value.

- `Service::addCall(string $method, array $arguments = []): Service`

  > Method name (and arguments) called just after the construction of object class.

- `Service::addCalls(array $calls = []): void`

  > It's an array of calls, the key must be the name of called method and value an array of arguments.

- `Service::setFactory(string $factory): Service`

  > It's the factory static method used to make object.
  >
  > Example: `MyProject\Name\Space\MyFactory::service`

### Instantiator

New instance of a class or object:

```php
use Berlioz\ServiceContainer\Instantiator;

$instantiator = new Instantiator();
$object = $instantiator->newInstanceOf(
    MyClass::class,
    [
        'argument1' => 'Value',
        'argument3' => 'Value',
        'argument2' => 'Value'
    ]
);
```

Invoke a method:

```php
use Berlioz\ServiceContainer\Instantiator;

$instantiator = new Instantiator();
$instantiator->invokeMethod(
    $myObject,
    'myMethodName',
    [
        'argument1' => 'Value',
        'argument3' => 'Value',
        'argument2' => 'Value'
    ]
);
```

Invoke a function:

```php
use Berlioz\ServiceContainer\Instantiator;

$instantiator = new Instantiator();
$instantiator->invokeFunction(
    'myFunctionName',
    [
        'argument1' => 'Value',
        'argument3' => 'Value',
        'argument2' => 'Value'
    ]
);
```

In all examples cases, the last argument is an array of parameters to give to the constructor, method or function. The
order of arguments is not important.

If parameter is an object, the system get this into the container or try to instantiate the class.

The method `Container::call()` call the good method of the instantiator according value:

```php
use Berlioz\ServiceContainer\Container;

$container = new Container();
$container->call(fn() => 'test'); // Call closure
$container->call(MyClass::class); // Instantiate the class
$container->call(MyClass::method); // Call static method or instantiate the class and call method 
```

### Inflector

The inflector is util if you want to inject some dependencies by methods implemented by an interface.

```php
use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Inflector\Inflector;

$inflector = new Inflector(
    MyInterface::class, // Interface implemented by object
    'setFoo', // Method to call
    [/*...*/] // Arguments
);
$container = new Container();
$container->addInflector($inflector);
```

### Service provider

In some case, like performances constraints, you need to add a service provider.

A service provider need to implement `\Berlioz\ServiceContainer\Provider\ServiceProviderInterface` interface. An
abstract class `\Berlioz\ServiceContainer\Provider\AbstractServiceProvider` can help you.

Example of a service provider:

```php
use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Provider\AbstractServiceProvider;

class MyServiceProvider extends AbstractServiceProvider
{
    // Declare services class and alias
    protected array $provides = [stdClass::class, 'service'];
    
    public function boot(Container $container) : void
    {
        // This method is called when provider is added to container.
        // Add inflectors here.
    }
    
    public function register(Container $container) : void
    {
         // Add services here
         $container->add(stdClass::class, 'service');
    }
}
```

Add your service provider:

```php
use Berlioz\ServiceContainer\Container;

$container = new Container();
$container->addProvider(new MyServiceProvider());

$container->has('service'); // Returns TRUE
$container->get('service'); // Returns an `stdClass` instance
```
# Berlioz Service Container

**Berlioz Service Container** is a PHP library to manage your services with dependencies injection, respecting PSR-11 (Container interface) standard.


## Installation

### Composer

You can install **Berlioz Service Container** with [Composer](https://getcomposer.org/), it's the recommended installation.

```bash
$ composer require berlioz/service-container
```

### Dependencies

* **PHP** >= 7.1
* Packages:
  * **psr/container**


## Usage

### Service container

Methods available more than :

- `get($id)`

  > PSR-11: Finds an entry of the container by its identifier and returns it.
  > 
  > **Accept a class name directly.**

- `has($id)`

  > PSR-11: Returns true if the container can return an entry for the given identifier.
  > Returns false otherwise.

- `register(string $alias, $class, array $arguments = [], array $calls = [])`

  > Register a service.
  >
  > Format for $calls argument:
  > ```php
  > [ [ 'method' => 'myMethod',
  >     'arguments' => [ 'argument1' => true ] ] ]
  > ```

- `registerServices(array $services)`

  > Register multiple services.
  >
  > Format:
  > ```php
  > [ 'alias' => [ 'class' => '\MyClass\Of\Service',
  >                'arguments' => [ 'argument1' => true ],
  >                'calls' => [ [ 'method' => 'myMethod',
  >                               'arguments' => [ 'argument1' => true ] ] ] ] ]
  > ```

- `getConstraints()`

  > Get constraints.

- `setConstraints(array $constraints)`

  > Set constraints.
  >
  > Format:
  > ```php
  > [ 'serviceName' => '\MyClass\of\Service',
  >   ... ]
  > ```

- `addConstraint(string $alias, string $class)`

  > Set constraint for service.

### Instantiator

New instance of a class or object:
```php
$instantiator = new Instantiator();
$object = $instantiator->newInstanceOf(MyClass::class,
                                       ['argument1' => 'Value',
                                        'argument3' => 'Value',
                                        'argument2' => 'Value']);
```

Invoke a method:
```php
$instantiator = new Instantiator();
$instantiator->invokeMethod($myObject,
                            'myMethodName',
                            ['argument1' => 'Value',
                             'argument3' => 'Value',
                             'argument2' => 'Value']);
```

Invoke a function:
```php
$instantiator = new Instantiator();
$instantiator->invokeFunction('myFunctionName',
                              ['argument1' => 'Value',
                               'argument3' => 'Value',
                               'argument2' => 'Value']);
```

In all examples cases, the last argument is an array of parameters to give to the constructor, method or function.
The order of arguments is not important.

In case of a parameter is an object, the system get the classes implemented to inject in good parameter.
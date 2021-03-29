<?php

declare(strict_types=1);

namespace Berlioz\ServiceContainer;

use Berlioz\ServiceContainer\Container\AutoWiringContainer;
use Berlioz\ServiceContainer\Container\DefaultContainer;
use Berlioz\ServiceContainer\Exception\NotFoundException;
use Berlioz\ServiceContainer\Inflector\Inflector;
use Berlioz\ServiceContainer\Service\Service;
use Closure;
use Generator;
use Psr\Container\ContainerInterface;

/**
 * Class Container.
 */
class Container implements ContainerInterface
{
    protected Instantiator $instantiator;
    protected ContainerInterface $container;
    /** @var ContainerInterface[] */
    protected array $containers = [];
    /** @var Inflector[] */
    protected array $inflectors = [];

    /**
     * Container constructor.
     *
     * @param ContainerInterface[] $containers
     * @param Inflector[] $inflectors
     */
    public function __construct(array $containers = [], array $inflectors = [])
    {
        $this->instantiator = new Instantiator($this);
        $this->container = new DefaultContainer($this->instantiator);

        $this->addContainer(...$containers);
        $this->addInflector(
            new Inflector(
                ContainerAwareInterface::class,
                'setContainer',
                ['container' => $this]
            ),
            new Inflector(
                InstantiatorAwareInterface::class,
                'setInstantiator',
                ['instantiator' => $this->instantiator]
            ),
            ...$inflectors
        );

        $this->add($this);
    }

    /**
     * Auto wiring.
     *
     * @param bool $active
     */
    public function autoWiring(bool $active = true): void
    {
        if (true === $active) {
            $this->addContainer(new AutoWiringContainer($this->instantiator));
            return;
        }

        // Remove auto wiring container
        $this->containers = array_filter(
            $this->containers,
            fn($container) => !($container instanceof AutoWiringContainer)
        );
    }

    /**
     * Add service.
     *
     * @param Service ...$service
     */
    public function addService(Service ...$service): void
    {
        $this->container->addService(...$service);
    }

    /**
     * New service.
     *
     * @param string|object $class
     * @param string|null $alias
     *
     * @return Service
     */
    public function add(string|object $class, ?string $alias = null): Service
    {
        if ($class instanceof Service) {
            $this->addService($class);

            return $class;
        }

        $this->addService($service = new Service($class, $alias));

        return $service;
    }

    /**
     * @inheritDoc
     */
    public function get($id): object
    {
        foreach ($this->getContainers() as $container) {
            if (true === $container->has($id)) {
                return $this->inflects($container->get($id));
            }
        }

        throw NotFoundException::notFound($id);
    }

    /**
     * @inheritDoc
     */
    public function has($id): bool
    {
        foreach ($this->getContainers() as $container) {
            if (true === $container->has($id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Call.
     *
     * @param string|Closure $subject
     * @param array $arguments
     * @param bool $autoWiring
     *
     * @return mixed
     * @throws Exception\ContainerException
     */
    public function call(string|Closure $subject, array $arguments = [], bool $autoWiring = true): mixed
    {
        $result = $this->instantiator->call($subject, $arguments, $autoWiring);

        if (!is_object($result)) {
            return $result;
        }

        return $this->inflects($result);
    }

    /**
     * Get containers.
     *
     * @return Generator<ContainerInterface>
     */
    protected function getContainers(): Generator
    {
        yield $this->container;
        yield from $this->containers;
    }

    /**
     * Add container.
     *
     * @param ContainerInterface ...$container
     */
    public function addContainer(ContainerInterface ...$container): void
    {
        array_push($this->containers, ...$container);
    }

    /**
     * Add inflector.
     *
     * @param Inflector ...$inflector
     */
    public function addInflector(Inflector ...$inflector): void
    {
        array_push($this->inflectors, ...$inflector);
    }

    /**
     * Inflects.
     *
     * @param object $obj
     *
     * @return object
     * @throws Exception\ContainerException
     */
    protected function inflects(object $obj): object
    {
        foreach ($this->inflectors as $inflector) {
            if (!is_a($obj, $inflector->getInterface())) {
                continue;
            }

            $this->instantiator->invokeMethod($obj, $inflector->getMethod(), $inflector->getArguments());
        }

        return $obj;
    }
}
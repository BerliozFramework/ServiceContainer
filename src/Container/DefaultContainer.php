<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\ServiceContainer\Container;

use Berlioz\ServiceContainer\Exception\NotFoundException;
use Berlioz\ServiceContainer\Instantiator;
use Berlioz\ServiceContainer\Service\Service;
use Psr\Container\ContainerInterface;

/**
 * Class DefaultContainer.
 */
class DefaultContainer implements ContainerInterface
{
    protected Instantiator $instantiator;
    /** @var Service[] */
    private array $services = [];

    public function __construct(?Instantiator $instantiator = null)
    {
        $this->instantiator = $instantiator ?? new Instantiator($this);
    }

    /**
     * Add service.
     *
     * @param Service ...$service
     */
    public function addService(Service ...$service): void
    {
        array_push($this->services, ...$service);
    }

    /**
     * @inheritDoc
     */
    public function get($id): object
    {
        return $this->getService($id)?->get($this->instantiator) ?? throw NotFoundException::notFound($id);
    }

    /**
     * @inheritDoc
     */
    public function has($id): bool
    {
        return null !== $this->getService($id);
    }

    /**
     * Get service by id.
     *
     * @param string $id
     *
     * @return Service|null
     */
    protected function getService(string $id): ?Service
    {
        foreach ($this->services as $service) {
            if ($id === $service->getAlias()) {
                return $service;
            }

            if ($id === $service->getClass()) {
                return $service;
            }
        }

        return null;
    }
}
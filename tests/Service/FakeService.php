<?php

declare(strict_types=1);

namespace Berlioz\ServiceContainer\Tests\Service;

use Berlioz\ServiceContainer\Service\Service;

class FakeService extends Service
{
    /**
     * @return array
     */
    public function getCalls(): array
    {
        return $this->calls;
    }
}
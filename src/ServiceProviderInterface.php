<?php

declare(strict_types=1);

namespace Switch\Container;

interface ServiceProviderInterface
{
    /**
     * Register services into the container.
     */
    public function register(Container $container): void;
}

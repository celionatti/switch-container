<?php

declare(strict_types=1);

namespace Switch\Container;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Switch\Container\Exception\ContainerException;
use Switch\Container\Exception\NotFoundException;

class Container implements ContainerInterface
{
    /**
     * @var array<string, array{concrete: mixed, shared: bool}>
     */
    private array $bindings = [];

    /**
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * @var array<string, string>
     */
    private array $aliases = [];

    public function bind(string $id, mixed $concrete = null, bool $shared = false): void
    {
        $concrete ??= $id;
        $this->bindings[$id] = [
            'concrete' => $concrete,
            'shared' => $shared,
        ];
    }

    public function singleton(string $id, mixed $concrete = null): void
    {
        $this->bind($id, $concrete, true);
    }

    public function instance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function alias(string $alias, string $target): void
    {
        $this->aliases[$alias] = $target;
    }

    public function register(ServiceProviderInterface $provider): void
    {
        $provider->register($this);
    }

    public function has(string $id): bool
    {
        $resolvedId = $this->resolveAlias($id);

        if (isset($this->instances[$resolvedId]) || isset($this->bindings[$resolvedId])) {
            return true;
        }

        if (class_exists($resolvedId)) {
            return true;
        }

        return false;
    }

    public function get(string $id): mixed
    {
        $resolvedId = $this->resolveAlias($id);

        if (isset($this->instances[$resolvedId])) {
            return $this->instances[$resolvedId];
        }

        if (isset($this->bindings[$resolvedId])) {
            $binding = $this->bindings[$resolvedId];
            $concrete = $binding['concrete'];

            if ($concrete instanceof Closure) {
                $object = $concrete($this);
            } elseif (is_string($concrete) && $concrete !== $resolvedId) {
                $object = $this->get($concrete);
            } else {
                $object = $this->build($resolvedId);
            }

            if ($binding['shared']) {
                $this->instances[$resolvedId] = $object;
            }

            return $object;
        }

        if (class_exists($resolvedId)) {
            return $this->build($resolvedId);
        }

        throw new NotFoundException("Service or class '{$id}' not found in container.");
    }

    public function build(string $className): object
    {
        if (!class_exists($className)) {
            throw new NotFoundException("Class '{$className}' does not exist.");
        }

        $reflector = new ReflectionClass($className);

        if (!$reflector->isInstantiable()) {
            throw new ContainerException("Class '{$className}' is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $className();
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveDependencies($parameters, $className);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * @param array<ReflectionParameter> $parameters
     * @return array<int, mixed>
     */
    private function resolveDependencies(array $parameters, string $className): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();
                if ($this->has($typeName)) {
                    $dependencies[] = $this->get($typeName);
                    continue;
                }
            }

            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull()) {
                $dependencies[] = null;
            } else {
                throw new ContainerException(
                    "Cannot resolve parameter '{$parameter->getName()}' of class '{$className}'."
                );
            }
        }

        return $dependencies;
    }

    private function resolveAlias(string $id): string
    {
        return $this->aliases[$id] ?? $id;
    }
}

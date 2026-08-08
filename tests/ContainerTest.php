<?php

declare(strict_types=1);

namespace Switch\Container\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Container\Container;
use Switch\Container\Exception\ContainerException;
use Switch\Container\Exception\NotFoundException;
use Switch\Container\ServiceProviderInterface;

class DummyDependency
{
    public string $name = 'default';
}

class DummyService
{
    public function __construct(public DummyDependency $dependency)
    {
    }
}

class NonInstantiableClass
{
    private function __construct()
    {
    }
}

class SampleServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->singleton('sample', fn() => new DummyDependency());
    }
}

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testBindAndGetClosure(): void
    {
        $this->container->bind('foo', fn() => 'bar');
        $this->assertTrue($this->container->has('foo'));
        $this->assertEquals('bar', $this->container->get('foo'));
    }

    public function testSingleton(): void
    {
        $this->container->singleton('service', fn() => new DummyDependency());
        $obj1 = $this->container->get('service');
        $obj2 = $this->container->get('service');

        $this->assertSame($obj1, $obj2);
    }

    public function testNonSingletonReturnsNewInstance(): void
    {
        $this->container->bind('service', fn() => new DummyDependency());
        $obj1 = $this->container->get('service');
        $obj2 = $this->container->get('service');

        $this->assertNotSame($obj1, $obj2);
    }

    public function testInstanceRegistration(): void
    {
        $instance = new DummyDependency();
        $this->container->instance('instance_key', $instance);

        $this->assertSame($instance, $this->container->get('instance_key'));
    }

    public function testAliasResolution(): void
    {
        $this->container->bind(DummyDependency::class, fn() => new DummyDependency());
        $this->container->alias('alias_key', DummyDependency::class);

        $this->assertInstanceOf(DummyDependency::class, $this->container->get('alias_key'));
    }

    public function testAutoWiring(): void
    {
        $service = $this->container->get(DummyService::class);
        $this->assertInstanceOf(DummyService::class, $service);
        $this->assertInstanceOf(DummyDependency::class, $service->dependency);
    }

    public function testServiceProviderRegister(): void
    {
        $this->container->register(new SampleServiceProvider());
        $this->assertTrue($this->container->has('sample'));
        $this->assertInstanceOf(DummyDependency::class, $this->container->get('sample'));
    }

    public function testNotFoundException(): void
    {
        $this->expectException(NotFoundException::class);
        $this->container->get('non_existent_service_id');
    }

    public function testNonInstantiableClassThrowsException(): void
    {
        $this->expectException(ContainerException::class);
        $this->container->get(NonInstantiableClass::class);
    }
}

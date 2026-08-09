# Switch Container (`switch/container`)

> Lightweight PSR-11 Dependency Injection Container featuring automatic constructor autowiring, singletons, aliases, and service providers.

---

## 📦 Installation

```bash
composer require switch/container
```

---

## 🚀 Usage

```php
use Switch\Container\Container;

$container = new Container();

// 1. Closures & Singletons
$container->singleton(Database::class, fn() => new Database('sqlite.db'));

// 2. Class Autowiring (Resolves dependencies automatically via Reflection)
$userService = $container->get(UserService::class);

// 3. Service Providers
$container->register(new AppServiceProvider());
```

---

## 📄 License
MIT License.

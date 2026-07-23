# troum/mycro-core

Общая PHP-библиотека для микросервисов: типизированные DTO с гидратацией из массивов, интеграция с RabbitMQ и простой файловый логгер.

**Требования:** PHP ^8.4, [php-amqplib/php-amqplib](https://github.com/php-amqplib/php-amqplib) ^3.5

**Пространство имён:** `Mycro\Core\`

---

## Установка

Добавьте VCS-репозиторий и зависимость в `composer.json` сервиса:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/Troum/mycro-core.git"
    }
  ],
  "require": {
    "troum/mycro-core": "^1.1"
  }
}
```

```bash
composer require troum/mycro-core
```

Версии пакета отмечаются git-тегами (`v1.1.0`, `v1.1.2` и т.д.). Укажите нужный тег в constraint или зафиксируйте версию:

```bash
composer require troum/mycro-core:1.1.3
```

---

## DTO (BaseDto)

Базовый класс для immutable DTO. Свойства объявляются как `public readonly` — значения задаются один раз при создании из массива (например, из HTTP-запроса или сообщения очереди).

### Минимальный пример

```php
<?php

use Mycro\Core\Attributes\DefaultValue;
use Mycro\Core\Contracts\BaseDto;

final class UserRegisterDto extends BaseDto
{
    public readonly string $first_name;
    public readonly string $last_name;
    public readonly string $email;
    public readonly string $password;
    public readonly string $phone;

    #[DefaultValue(null)]
    public readonly ?string $second_name;
}

$dto = new UserRegisterDto($request->validated());
$array = $dto->toArray();
```

### Правила гидратации

1. Обрабатываются только свойства с модификатором `readonly`.
2. Ключи входного массива приводятся к `snake_case` (`firstName`, `first-name`, `first_name` → `first_name`). Вложенные массивы нормализуются рекурсивно.
3. Если значение не найдено и нет атрибута `DefaultValue` / необязательного `MapProperty`, выбрасывается `DtoHydrationException`.
4. Повторная инициализация уже заданного readonly-свойства запрещена (`ReadonlyPropertyUpdateException`).

### Атрибут `DefaultValue`

Задаёт значение, если ключ отсутствует в данных:

```php
#[DefaultValue(null)]
public readonly ?string $second_name;

#[DefaultValue('ru')]
public readonly string $locale;
```

### Атрибут `MapProperty`

Сопоставляет свойство DTO с одним или несколькими ключами во входных данных (после нормализации в `snake_case`):

```php
use Mycro\Core\Attributes\MapProperty;

// API отдаёт user_id, в DTO — id
#[MapProperty(from: 'user_id')]
public readonly int $id;

// Несколько алиасов
#[MapProperty(from: ['phone_number', 'mobile'])]
public readonly string $phone;

// Необязательное поле: при отсутствии ключа будет null
#[MapProperty(from: 'middle_name', required: false)]
public readonly ?string $middle_name;
```

Порядок поиска значения: алиасы из `from` → имя свойства → `DefaultValue` → `null` (если `required: false`).

### Атрибут `Transform`

Преобразует значение после разрешения маппера, до записи в свойство. Трансформер должен реализовать `TransformerContract`:

```php
use Mycro\Core\Attributes\Transform;
use Mycro\Core\Contracts\TransformerContract;

final class TrimTransformer implements TransformerContract
{
    public function transform(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }
}

final class CreateUserDto extends BaseDto
{
    #[Transform(TrimTransformer::class)]
    public readonly string $email;
}
```

### Кастомный PropertyMapper

По умолчанию используется `DefaultPropertyMapper`. Свой маппер подключается глобально для всех DTO:

```php
use Mycro\Core\Contracts\BaseDto;
use Mycro\Core\Contracts\PropertyMapperInterface;

BaseDto::setPropertyMapper(new MyCustomPropertyMapper());
```

Интерфейс `PropertyMapperInterface::resolve()` возвращает кортеж `[bool $hasValue, mixed $value]`.

---

## RabbitMQ

### RabbitMQService

Подключение, публикация и потребление сообщений (JSON в теле, durable exchange/queue).

```php
use Mycro\Core\Logging\FileCoreLogger;
use Mycro\Core\Messaging\RabbitMQ\RabbitMQService;

$logger = new FileCoreLogger('/var/log/my-service/rabbit.log');

$rabbit = new RabbitMQService(
    host: 'rabbitmq',
    port: 5672,
    user: 'guest',
    password: 'guest',
    logger: $logger,
);

// Публикация
$rabbit->publish(
    exchange: 'users',
    routingKey: 'user.registered',
    data: ['user_id' => 42, 'email' => 'a@b.c'],
    queue: 'user_events', // опционально: объявить очередь и привязать к exchange
);

// Потребление (блокирующий цикл)
$rabbit->consume('user_events', function (array $payload): void {
    // обработка $payload
});
```

Поведение consumer:

- `no_ack = false` — ручное подтверждение;
- при успехе — `ack()`;
- при ошибке — лог через `CoreLoggerInterface::error()`, `nack(requeue: true)`.

### RabbitMQConsumer

Тонкая обёртка над `consume()`:

```php
use Mycro\Core\Messaging\RabbitMQ\RabbitMQConsumer;

$consumer = new RabbitMQConsumer($rabbit);
$consumer->listen('user_events', $handler);
```

### RabbitMQPublisherInterface

Интерфейс только для публикации — удобно для DI и моков в тестах:

```php
public function publish(string $exchange, string $routingKey, array $data, ?string $queue = null): void;
```

---

## Логирование

### CoreLoggerInterface

```php
public function error(string $message): void;
public function info(string $message): void;
public function debug(string $message): void;
```

Используется в RabbitMQ consumer. В Laravel-сервисе можно зарегистрировать адаптер к `Log::channel()`.

### FileCoreLogger

Запись в файл с меткой времени. По умолчанию: `{package}/logs/core.log` (каталог создаётся автоматически).

```php
$logger = new FileCoreLogger(storage_path('logs/mycro-core.log'));
```

---

## Исключения

| Класс | Когда |
|--------|--------|
| `DtoHydrationException` | Обязательное свойство не передано и не задано по умолчанию |
| `ReadonlyPropertyUpdateException` | Попытка повторно инициализировать readonly-свойство |

---

## Структура пакета

```
src/
├── Attributes/          # DefaultValue, MapProperty, Transform
├── Contracts/           # BaseDto, PropertyMapperInterface, TransformerContract
├── Exceptions/
├── Logging/
├── Messaging/RabbitMQ/
└── Services/            # DefaultPropertyMapper
```

---

## Миграция с `troum/marketplace-core`

Ранние версии публиковались как `troum/marketplace-core` с пространством имён `Marketplace\Core\`. Текущий пакет — `troum/mycro-core`, namespace `Mycro\Core\`. При обновлении замените `use`-импорты и constraint в `composer.json`.

---

## Лицензия

MIT

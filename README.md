# troum/mycro-core

Общая PHP-библиотека для микросервисов: типизированные DTO с гидратацией из массивов, интеграция с RabbitMQ и простой файловый логгер.

**Требования:** PHP ^8.4, [php-amqplib/php-amqplib](https://github.com/php-amqplib/php-amqplib) ^3.5

**Пространство имён:** `Mycro\Core\`

---

## Установка

Пакет доступен на [Packagist](https://packagist.org/packages/troum/mycro-core):

```bash
composer require troum/mycro-core
```

Рекомендуемый constraint — `^1.1`. Версии отмечаются git-тегами (`v1.1.0`, `v1.1.3` и т.д.):

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
2. Ключи входного массива приводятся к `snake_case` (`firstName`, `first-name`, `first_name` → `first_name`). Нормализуется **только верхний уровень** — вложенные массивы передаются в свойство как есть, вместе с исходными ключами.
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

### Частичное обновление: `wasProvided()` и `providedAttributes()`

`toArray()` возвращает **все** readonly-свойства, включая значения из `DefaultValue` и `null` от `required: false`. Для `PATCH`-запросов этого недостаточно: непонятно, что клиент прислал, а что подставила библиотека.

DTO запоминает, ключи каких свойств действительно присутствовали во входном массиве:

```php
public function wasProvided(string $property): bool;

/** @return array<string, mixed> */
public function providedAttributes(): array;
```

- явный `null` считается переданным значением — поле можно очистить;
- значение из `#[DefaultValue]` или `#[MapProperty(required: false)]` переданным **не** считается — оно не перезапишет данные в БД;
- свойство, найденное по алиасу `#[MapProperty(from: 'other_name')]`, попадает в результат под именем свойства, а не алиаса;
- значения — уже после `#[Transform]`, как и в `toArray()`.

```php
final class UpdateMailingDto extends BaseDto
{
    #[DefaultValue('')]
    public readonly string $subject;

    #[MapProperty(from: 'reply_to', required: false)]
    public readonly ?string $reply_to_email;
}

$dto = new UpdateMailingDto(['reply_to' => null]);

$dto->wasProvided('subject');        // false — ключ не передан
$dto->wasProvided('reply_to_email'); // true  — передан явный null

$dto->toArray();
// ['subject' => '', 'reply_to_email' => null]

$dto->providedAttributes();
// ['reply_to_email' => null]
```

Для частичного обновления используйте `providedAttributes()` вместо `array_filter($dto->toArray(), fn ($v) => $v !== null)`:

```php
$mailing->update($dto->providedAttributes());
```

Для создания записи по-прежнему нужен `toArray()` — там значения по умолчанию как раз необходимы.

### Кастомный PropertyMapper

По умолчанию используется `DefaultPropertyMapper`. Свой маппер подключается глобально для всех DTO:

```php
use Mycro\Core\Contracts\BaseDto;
use Mycro\Core\Contracts\PropertyMapperInterface;

BaseDto::setPropertyMapper(new MyCustomPropertyMapper());
```

Интерфейс `PropertyMapperInterface::resolve()` возвращает кортеж `[bool $hasValue, mixed $value, bool $fromInput]`.

Третий элемент — позиционный и необязательный: маппер, возвращающий два элемента, продолжает работать, но `wasProvided()` и `providedAttributes()` для его DTO всегда будут пустыми. Возвращайте `true` только тогда, когда значение действительно взято из входного массива:

```php
public function resolve(object $dto, ReflectionProperty $property, array $data): array
{
    $name = $property->getName();

    if (array_key_exists($name, $data)) {
        return [true, $data[$name], true];  // из входных данных
    }

    return [true, null, false];             // подставлено маппером
}
```

Проверяйте наличие ключа через `array_key_exists()`, а не `isset()` — иначе явный `null` не будет считаться переданным.

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

tests/
├── Dto/                 # гидратация, частичное обновление, нормализация ключей
└── Fixtures/
```

---

## Тесты

```bash
composer install
composer test
```

---

## Лицензия

MIT

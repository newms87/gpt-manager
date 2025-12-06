# PHP 8.3 Code Style Guide

Modern PHP syntax and project standards for GPT Manager. All Laravel backend agents MUST follow these patterns.

## Null Safety

**`??`** - Default when null: `$name = $data['name'] ?? 'default';`

**`??=`** - Assign if not set: `$config['timeout'] ??= 30;`

**`?->`** - Safe access: `$userName = $user?->profile?->getName() ?? 'Guest';`

## Types

ALWAYS declare types for properties, parameters, returns, and constants.

**Union types:** `public function find(int|string $id): User|null`

**Typed constants (8.3):** `public const string STATUS_ACTIVE = 'active';`

**Nullable:** Prefer `?Type` over `Type|null`

**Mixed:** Use sparingly: `public function set(string $key, mixed $value): void`

## Constructor Promotion & Readonly

**Use for:** DTOs, value objects, events
```php
readonly class TaskInputDTO
{
    public function __construct(
        public readonly string $prompt,
        public readonly array $files,
        public readonly ?string $model = null,
    ) {}
}
```

**NEVER use for:** Eloquent Models or Services (use standard properties + `app()` helper)

**Immutable modifications (8.3):**
```php
public function withTimeout(int $timeout): self
{
    return new self(name: $this->name, timeout: $timeout, options: $this->options);
}
```

## Match Expressions

Use instead of switch for value returns. Strict comparison, throws on unhandled cases.

```php
return match($status) {
    'active', 'running' => 'green',
    'pending', 'queued' => 'yellow',
    default => 'gray',
};

return match($action) {
    'create' => $this->createModel($data),
    'update' => $this->updateModel($model, $data),
    default => parent::applyAction($action, $model, $data)
};
```

**Conditional match:** `$priority = match(true) { $user->isAdmin() => 'high', default => 'low' };`

## Enums

Prefer backed enums (string/int) for database storage and API serialization.

```php
enum TaskStatus: string
{
    case Pending = 'pending';
    case Running = 'running';

    public function color(): string
    {
        return match($this) {
            self::Pending => 'yellow',
            self::Running => 'blue',
        };
    }
}

// Usage: $status = TaskStatus::Running; echo $status->value; // 'running'
```

## Named Arguments

Use for clarity with many parameters, skipping optionals, or boolean flags.

```php
app(TaskProcessExecutorService::class)->execute(
    taskRun: $taskRun,
    options: ['timeout' => 300],
    retryOnFailure: true,
);
```

## Arrow Functions

Auto-captures parent scope. Use for short callbacks.

```php
$activeUsers = array_filter($users, fn($user) => $user->isActive());
$tasks = TaskRun::query()
    ->whereHas('taskDefinition', fn($q) => $q->where('type', 'classifier'))
    ->when($status, fn($q, $s) => $q->where('status', $s))
    ->get();
```

## Attributes

**ALWAYS use `#[Override]` when overriding parent methods:**

```php
class TaskRunRepository extends ActionRepository
{
    #[Override]
    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }
}
```

## String & Array Features

**String functions:** `str_contains($file, '.pdf')`, `str_starts_with($path, '/')`, `str_ends_with($email, '.com')`

**Destructuring:** `[$first, $last] = explode(' ', $name);`

**Spread:** `$all = [...$arrayA, ...$arrayB];`

**Numeric separators:** `public const int MAX_SIZE = 10_000_000;`

## Exception Handling

**Throw as expression:** `$user = $this->find($id) ?? throw new ValidationError('Not found');`

**Non-capturing catch:** `try { $data = json_decode($json); } catch (JsonException) { return null; }`

## First-Class Callables

`$results = array_map($this->transform(...), $items);`

`$lengths = array_map(strlen(...), $strings);`

## Dynamic Class Constants (8.3)

```php
class TaskRun
{
    public const string STATUS_PENDING = 'pending';
}

$value = TaskRun::{'STATUS_PENDING'};  // 'pending'
```

## json_validate() (8.3)

`if (!json_validate($json)) { return null; }`

## Project Standards

### ALWAYS Use `app()` Helper

NEVER use constructor injection:

```php
public function execute(TaskRun $taskRun): void
{
    app(TaskInputService::class)->prepare($taskRun);
}
```

### ALWAYS Use `use` Statements

NEVER use inline backslashes. Import order: Laravel/PHP → Third-party → App (alphabetically)

```php
use App\Services\Task\TaskProcessExecutorService;
use Illuminate\Support\Facades\DB;
```

### Group Related Constants

```php
public const string STATUS_PENDING = 'pending',
                    STATUS_RUNNING = 'running',
                    STATUS_COMPLETED = 'completed';
```

### Size Limits

**Class:** 300 lines max - extract into smaller focused classes

**Method:** 50 lines max - extract helper methods

```php
public function processTask(TaskRun $taskRun): void
{
    $this->validateTask($taskRun);
    $this->executeTask($taskRun);
    $this->storeResult($taskRun);
}
```

### Self-Documenting Code

Extract magic numbers to constants. Use descriptive method names.

```php
private const int HIGH_CONFIDENCE_THRESHOLD = 4;

public function hasHighConfidenceAssignments(array $data): bool
{
    return $this->calculateAverage($data) >= self::HIGH_CONFIDENCE_THRESHOLD;
}
```

## Quick Checklist

- [ ] All properties, constants, parameters, returns have types
- [ ] Used `app()` helper (NO constructor injection)
- [ ] Used `use` statements (NO inline backslashes)
- [ ] Used `??`, `??=`, `?->` operators
- [ ] Used `match` for value returns
- [ ] Used arrow functions for simple callbacks
- [ ] Used `#[Override]` on parent method overrides
- [ ] Used modern string functions
- [ ] Grouped related constants
- [ ] Classes < 300 lines, methods < 50 lines
- [ ] Magic numbers extracted to constants

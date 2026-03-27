# Symfony Messenger Patterns

## When to Apply
Use when implementing message handlers, consumers, or async event processing with RabbitMQ.

## Patterns

### Message Classes
- In `src/Application/Message/`
- Final readonly classes with public constructor properties
- No logic, just data carriers
- One message per file

```php
final readonly class EditorialPublished
{
    public function __construct(
        public string $editorialId,
    ) {}
}
```

### Handlers
- In `src/Application/Handler/`
- Use `#[AsMessageHandler]` attribute
- One handler per message (invokable `__invoke`)
- Inject dependencies via constructor
- Handle idempotency: check if already processed before acting

### Transport Configuration
- AMQP transport for RabbitMQ
- Queue naming: `queue::event::domain::action` or `queue::command::action`
- Separate transports per consumer (one queue per Supervisor process)
- `in-memory://` for test environment

### Delayed Messages
- Use `DelayStamp` for scheduled delivery
- Calculate delay: `max(0, (scheduledAt - now) * 1000)` milliseconds
- Requires RabbitMQ delayed message exchange plugin

### Retry Strategy
- Configure in `messenger.yaml` per transport
- Events (ingesta): retry with backoff (3 retries, 1s delay, 2x multiplier)
- Commands (envio): retry managed by application logic, not Messenger

### Supervisor Config
```ini
[program:service_consumer_name]
command=php /var/www/service/bin/console messenger:consume transport_name --queues=queue::name --time-limit=3600
user=www-data
numprocs=1
autostart=true
autorestart=true
```

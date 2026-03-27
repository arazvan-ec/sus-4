# Symfony Doctrine Patterns

## When to Apply
Use when creating entities, repositories, migrations, or database queries.

## Patterns

### Entities
- In `src/Domain/` (domain layer, no Doctrine annotations)
- Final classes with private properties and explicit getters/setters
- Use PHP enums for status fields (backed enums with string values)
- UUID v7 for identifiers (`Symfony\Component\Uid\Uuid::v7()`)
- Value objects for composite data (readonly classes)

### Doctrine Mapping
- XML mapping in `config/doctrine/` (keeps Domain free of framework)
- One XML file per entity: `EntityName.orm.xml`
- Enum fields: use `enumType` attribute in XML mapping

### Repository Pattern
- Interface in `src/Domain/EntityRepositoryInterface.php`
- Implementation in `src/Infrastructure/Repository/DoctrineEntityRepository.php`
- Use QueryBuilder for complex queries, DQL for simple ones
- Always inject `EntityManagerInterface`, not extend `ServiceEntityRepository`

### Naming
- Tables: plural, snake_case (`campaigns`, `notification_deliveries`)
- Columns: snake_case (`editorial_id`, `scheduled_at`)
- Indexes: `idx_table_columns` (`idx_campaigns_status_scheduled`)
- Unique constraints: `uniq_table_columns`

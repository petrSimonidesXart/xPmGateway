# JSON Schema Contracts

Sdílené kontrakty mezi adapterem (PHP) a workerem (Node.js) definující vstupní a výstupní schémata nástrojů.

## Umístění

```
packages/contracts/
├── create-task.input.json
├── create-task.output.json
├── get-task.input.json
├── get-task.output.json
├── export-tasks.input.json
├── export-tasks.output.json
├── export-filtered-tasks.input.json
├── export-filtered-tasks.output.json
├── get-job-status.input.json
├── get-job-status.output.json
├── list-my-recent-jobs.input.json
├── list-my-recent-jobs.output.json
├── verify-credentials.input.json
├── verify-credentials.output.json
└── openapi-v1.yaml
```

## Naming konvence

| Aspect | Pravidlo | Příklad |
|--------|---------|---------|
| Název souboru | `{tool-name}.{input\|output}.json` | `create-task.input.json` |
| Tool name → filename | podtržítka → pomlčky | `create_task` → `create-task` |
| JSON Schema verze | Draft-07 | `"$schema": "http://json-schema.org/draft-07/schema#"` |

## Pravidla schémat

### Povinná pravidla

- Každé schéma musí mít `"$schema": "http://json-schema.org/draft-07/schema#"`
- Root musí být `"type": "object"`
- Input schémata musí mít `"additionalProperties": false`
- Pole `required` musí obsahovat všechna povinná pole

### Doporučené typy

| Typ dat | JSON Schema |
|---------|-------------|
| UUID | `{ "type": "string", "format": "uuid" }` |
| Datum | `{ "type": "string", "format": "date" }` |
| Kladné číslo | `{ "type": "number", "minimum": 0 }` |
| Enum | `{ "type": "string", "enum": ["val1", "val2"] }` |
| Omezený text | `{ "type": "string", "minLength": 1, "maxLength": 500 }` |

## Příklad: input schéma

```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "type": "object",
    "properties": {
        "title": {
            "type": "string",
            "minLength": 1,
            "maxLength": 500
        },
        "project": {
            "type": "string",
            "minLength": 1,
            "maxLength": 200
        },
        "assignee": {
            "type": "string",
            "maxLength": 200
        }
    },
    "required": ["title", "project"],
    "additionalProperties": false
}
```

## Příklad: output schéma

```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "type": "object",
    "properties": {
        "task_id": {
            "type": "string"
        },
        "message": {
            "type": "string"
        }
    },
    "required": ["task_id", "message"]
}
```

## Jak se schémata používají

### Adapter (PHP)

`SchemaValidator` validuje vstupní data proti input schématu před vytvořením jobu:

```php
$errors = $this->schemaValidator->validate($params, 'create-task.input.json');
if ($errors !== null) {
    throw new McpException('Validation failed: ' . implode('; ', $errors), 422);
}
```

### V1Presenter (OpenAPI)

Input schémata se dynamicky vkládají do OpenAPI specifikace jako `requestBody.content.application/json.schema`. Output schémata se kontrolují na přítomnost klíčového slova `artifact` pro podmíněné zobrazení artifact endpointu.

### Worker (Node.js)

Handler přistupuje k datům přes `job.payload`. Validace proběhla na straně adapteru, handler může předpokládat validní data:

```typescript
const { title, project } = job.payload as { title: string; project: string };
```

## Přidání nového kontraktu

1. Vytvořte `{tool-name}.input.json` a `{tool-name}.output.json`
2. Dodržujte pravidla výše
3. Adapter automaticky najde schéma podle názvu toolu
4. Spusťte testy: `cd adapter && composer test` (SchemaValidator.phpt ověří základní validaci)

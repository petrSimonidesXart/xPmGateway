# Scénáře — kompozitní automatizace

Scénáře řetězí PM tooly do znovupoužitelných workflow. Automatické přihlášení, sdílená browser session, podpora podmínek a cyklů.

## Koncept

```
Scénář "přidej komentář"
  ↓ auto-login
  ↓ pm_open_project (query: {{input.project}})
  ↓ pm_open_task (query: {{input.task}})
  ↓ pm_create_comment (text: {{input.text}})
```

Uživatel zadá: `{ "project": "xartRECEPTY", "task": "test", "text": "Hotovo" }`

## Vytvoření scénáře

### V Admin UI

1. **Scénáře → Nový scénář**
2. Zadejte identifikátor (bez mezer), popis
3. Přidávejte kroky kliknutím na nástroje v pravém panelu
4. Vstupní schéma se generuje automaticky z `{{input.*}}` proměnných
5. Uložte → spusťte z detailu scénáře

### Přes API / MCP

Scénáře se automaticky zobrazí jako tooly:
- MCP: `scenario_{name}` v `tools/list`
- REST: `POST /api/v1/tools/scenario_{name}`

## JSON formát scénáře

### Kroky (steps)

```json
[
  {
    "id": "project",
    "type": "tool",
    "tool": "pm_open_project",
    "input": { "query": "{{input.project}}" }
  },
  {
    "id": "task",
    "type": "tool",
    "tool": "pm_open_task",
    "input": { "query": "{{input.task}}" }
  },
  {
    "id": "comment",
    "type": "tool",
    "tool": "pm_create_comment",
    "input": { "text": "{{input.text}}" }
  }
]
```

### Template výrazy

| Výraz | Popis |
|-------|-------|
| `{{input.project}}` | Vstupní parametr od volajícího |
| `{{project.output.path_info}}` | Výstup kroku s id "project" |
| `{{project.output.results[0].name}}` | Přístup do pole |
| `{{loop_var.name}}` | Proměnná z cyklu |

### Typy kroků

#### tool — spuštění nástroje
```json
{
  "id": "comment",
  "type": "tool",
  "tool": "pm_create_comment",
  "input": { "text": "{{input.text}}" },
  "expect": { "count": 1, "error": "Nenalezeno" }
}
```

#### condition — větvení
```json
{
  "id": "check",
  "type": "condition",
  "if": "{{search.output.count}} == 1",
  "then": [ ... ],
  "else": [ ... ]
}
```

#### loop — cyklus
```json
{
  "id": "create_all",
  "type": "loop",
  "over": "{{input.subtasks}}",
  "as": "subtask",
  "steps": [
    {
      "id": "create",
      "type": "tool",
      "tool": "pm_create_subtask",
      "input": { "name": "{{subtask.name}}" }
    }
  ]
}
```

### Expect klauzule

Volitelná validace výstupu kroku:

```json
"expect": {
  "count": 1,
  "error": "Projekt nenalezen nebo nejednoznačný"
}
```

Pokud `output.count` neodpovídá → krok selže s popisnou chybou.

## Vstupní schéma (input_schema)

JSON Schema definující parametry scénáře:

```json
{
  "type": "object",
  "required": ["project", "task", "text"],
  "properties": {
    "project": { "type": "string", "minLength": 1, "description": "Název projektu" },
    "task": { "type": "string", "minLength": 1, "description": "Název úkolu" },
    "text": { "type": "string", "minLength": 1, "description": "Text komentáře" }
  },
  "additionalProperties": false
}
```

Schéma se **generuje automaticky** při přidávání kroků v editoru.

## Zkratky (Lookups)

Tooly podporují zkratky z číselníků (Admin UI → Číselníky):

| Vstup | Resolves to |
|-------|-------------|
| `"assignee": "PS"` | Petr Simonides |
| `"label": "RESIT"` | ŘEŠIT |
| `"schedule": "TT"` | this_week |
| `"schedule": "DNES"` | dnešní datum (YYYY/MM/DD) |
| `"schedule": "PT"` | next_week |

Zkratky se resolvují ve workeru automaticky.

## Disambiguace

Pokud tool najde víc výsledků (např. více projektů):

1. Job přejde do stavu `awaiting_input` (fialový badge)
2. V UI se zobrazí výběr z možností
3. Po výběru se vytvoří nový job s `path_info` (přímá navigace, bez hledání)

Pro MCP/AI: klient dostane `needs_input: true` s opcemi a zavolá znovu.

## Live progress

Při běhu scénáře se v detailu jobu zobrazuje real-time průběh:
- Každý krok loguje zprávy (přihlašuji se, hledám projekt, ...)
- Tabulka "Průběh" se aktualizuje každé 2 sekundy
- Po dokončení se stránka automaticky refreshne

## Architektura

```
Admin UI / MCP / REST API
         ↓ vytvoří job (tool_name=run_scenario, payload={scenario, input})
    Job Queue (MariaDB)
         ↓ worker polluje
    Scenario Runner (scenarioRunner.ts)
         ↓ otevře browser, auto-login
         ↓ pro každý krok: resolve templates → spustí tool → uloží výstup
         ↓ podmínky/cykly zpracuje rekurzivně
         ↓ progress posílá přes API do DB
    Worker → result → job status = success/failed/awaiting_input
```

## DB tabulky

```sql
scenarios (id, name, description, input_schema, steps, is_active)
jobs.scenario_id     -- FK na scenarios
jobs.step_results    -- JSON průběh kroků
jobs.awaiting_input_context  -- kontext pro disambiguaci
pm_lookups (category, shortcut, value, description)
```

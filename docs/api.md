# REST API v1

REST API pro externí integrace (ChatGPT Actions, Make.com, n8n, Zapier).

## Autentizace

Všechny endpointy vyžadují Bearer token v hlavičce `Authorization`:

```
Authorization: Bearer <api-token>
```

Tokeny se spravují v Admin UI → API Tokens.

## Base URL

```
https://<your-domain>/api/v1
```

## Endpointy

### GET /api/v1/openapi.json

Dynamicky generovaná OpenAPI 3.1.0 specifikace. Obsahuje pouze nástroje, ke kterým má klient oprávnění. Kompatibilní s ChatGPT Actions.

```bash
curl -H "Authorization: Bearer $TOKEN" https://gateway.example.com/api/v1/openapi.json
```

### POST /api/v1/tools/{toolName}

Spustí nástroj. Pokud se job dokončí do 20 sekund, vrátí výsledek přímo (`mode: done`). Jinak vrátí `mode: queued` a klient musí pollovat stav.

```bash
curl -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title": "Nový úkol", "project": "Projekt X"}' \
  https://gateway.example.com/api/v1/tools/create_task
```

**Response (done):**
```json
{
  "mode": "done",
  "job_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "success",
  "task_id": "42"
}
```

**Response (queued):**
```json
{
  "mode": "queued",
  "job_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "pending"
}
```

### GET /api/v1/jobs/{id}

Stav jobu. Použij pro polling, když tool call vrátil `mode: queued`.

```bash
curl -H "Authorization: Bearer $TOKEN" \
  https://gateway.example.com/api/v1/jobs/550e8400-e29b-41d4-a716-446655440000
```

**Response:**
```json
{
  "status": "success",
  "result": {"task_id": "42", "message": "Task created"},
  "finished_at": "2026-03-15T10:00:00+01:00",
  "artifacts": [
    {
      "id": "art-uuid",
      "filename": "export.csv",
      "mime_type": "text/csv",
      "size_bytes": 1234,
      "download_url": "/api/v1/artifacts/art-uuid/download"
    }
  ]
}
```

### GET /api/v1/jobs

Seznam posledních jobů klienta.

**Query parametry:**
| Parametr | Typ | Default | Popis |
|----------|-----|---------|-------|
| limit | int | 10 | Max 50 |
| status | string | — | Filtr: pending, processing, success, failed, timeout |
| tool_name | string | — | Filtr podle názvu nástroje |

```bash
curl -H "Authorization: Bearer $TOKEN" \
  "https://gateway.example.com/api/v1/jobs?limit=5&status=failed"
```

### GET /api/v1/artifacts/{id}/download

Stáhne soubor artefaktu (CSV, PDF, obrázek...). Vrací binární obsah se správným `Content-Type`.

```bash
curl -H "Authorization: Bearer $TOKEN" \
  -o export.csv \
  https://gateway.example.com/api/v1/artifacts/art-uuid/download
```

## HTTP Status kódy

| Kód | Význam |
|-----|--------|
| 200 | Úspěch |
| 400 | Nevalidní JSON v body |
| 401 | Chybějící nebo neplatný token |
| 403 | Nemáte oprávnění k tomuto nástroji / IP není povolena |
| 404 | Nástroj / job / artefakt nenalezen |
| 405 | Špatná HTTP metoda |
| 422 | Vstupní data nesplňují JSON Schema |
| 429 | Překročen rate limit |

## Error formát

Všechny chyby mají jednotný formát:

```json
{
  "error": "Popis chyby"
}
```

## Rate Limiting

- Výchozí limit: 60 požadavků / minutu / token
- Po překročení: HTTP 429 s hlavičkou `Retry-After: 60`

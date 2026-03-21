# Worker Handler Guide

Jak napsat nový Playwright handler pro PM Gateway worker.

## Architektura

Worker je Node.js proces, který polluje adapter API pro nové joby. Každý job má `tool_name`, který určuje, jaký handler se zavolá. Handler dostane job data a API klienta, provede automatizaci přes Playwright a nahraje výsledek zpět.

```
Adapter → Job Queue → Worker → Handler → Playwright → Legacy PM
                             ↓
                        API klient → submitResult / uploadArtifact
```

## Handler interface

```typescript
type JobHandler = (job: Job, api: AdapterApi) => Promise<void>;
```

### Job objekt

```typescript
interface Job {
    id: string;                    // UUID jobu
    tool_name: string;             // Název nástroje (= handler klíč)
    payload: Record<string, unknown>; // Vstupní data z klienta
    service_account: {
        username: string;          // Přihlašovací jméno do legacy systému
        password: string;          // Heslo (dešifrované)
    };
    attempt: number;               // Číslo pokusu (1 = první)
    timeout_seconds: number;       // Max doba běhu
}
```

### AdapterApi metody

- `submitResult(jobId, payload)` — odešle výsledek (success/failed)
- `uploadArtifact(jobId, filePath, options?)` — nahraje soubor z disku
- `uploadArtifactContent(jobId, content, filename, mimeType)` — nahraje obsah z paměti

## Krok za krokem

### 1. Vytvořte JSON Schema kontrakty

```
packages/contracts/my-new-tool.input.json
packages/contracts/my-new-tool.output.json
```

Viz [Contracts dokumentace](contracts.md).

### 2. Vytvořte handler

`worker/src/handlers/myNewTool.ts`:

```typescript
import { chromium } from 'playwright';
import type { Job } from '../index.js';
import type { AdapterApi } from '../lib/api.js';
import { loginToLegacySystem } from '../lib/auth.js';
import { ScreenshotManager } from '../lib/screenshots.js';

export async function handleMyNewTool(job: Job, api: AdapterApi): Promise<void> {
    const screenshots = new ScreenshotManager(job.id);
    await screenshots.init();

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();

    try {
        // 1. Přihlášení
        await loginToLegacySystem(page, job.service_account.username, job.service_account.password);
        await screenshots.capture(page, 'after-login');

        // 2. Navigace a automatizace
        const { my_param } = job.payload as { my_param: string };
        // ... Playwright automatizace ...
        await screenshots.capture(page, 'result');

        // 3. Odeslání výsledku
        await api.submitResult(job.id, {
            status: 'success',
            result: { message: 'Done', data: '...' },
            screenshots: screenshots.getScreenshots(),
        });
    } catch (error) {
        await screenshots.capture(page, 'error');
        throw error; // Worker catch blok odešle failed status
    } finally {
        await context.close();
        await browser.close();
    }
}
```

### 3. Zaregistrujte handler

V `worker/src/index.ts` přidejte import a registraci:

```typescript
import { handleMyNewTool } from './handlers/myNewTool.js';

const handlers: Record<string, JobHandler> = {
    // ... existující handlery ...
    my_new_tool: handleMyNewTool,
};
```

### 4. Zaregistrujte tool v databázi

Přidejte tool přes Admin UI → Tools → Přidat, nebo SQL:

```sql
INSERT INTO tools (name, description, is_active)
VALUES ('my_new_tool', 'Popis nového nástroje', 1);
```

### 5. Nastavte oprávnění

V Admin UI → Clients → váš klient → přidejte oprávnění k novému toolu.

## Best practices

- **Vždy zachytávejte screenshoty** — pomáhají při debugování. Používejte `ScreenshotManager`.
- **Vždy zavírejte browser** v `finally` bloku — zabráníte úniku zdrojů.
- **Nový context pro každý job** — zajistí čistou session (cookies, storage).
- **Kontrolujte chybové stavy na stránce** — zkontrolujte `.error`, `.alert-danger` elementy po každé akci.
- **Nastavte timeouty** na Playwright operacích — `page.waitForSelector('...', { timeout: 30000 })`.
- **Nevracejte citlivá data** ve výsledku — hesla, tokeny apod.

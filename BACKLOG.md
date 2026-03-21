# PM Gateway — Backlog

## Milník 1: MVP

Dokončení základní funkcionality tak, aby systém byl nasaditelný a použitelný v produkci.

### Worker & Tooly
- [ ] **get_task handler**: robustní scraping — ošetřit chybějící pole, timeouty, fallbacky pro změněný DOM
- [ ] **export_filtered_tasks handler**: ověřit funkčnost CSV exportu, ošetřit případ kdy filtr vrátí 0 výsledků
- [ ] **Retry logika pro Playwright handlery**: opakovat přihlášení při selhání (network timeout, session expiry)
- [ ] **Graceful shutdown workeru**: při SIGTERM dokončit rozpracovaný job místo tvrdého ukončení
- [x] **Health-check workeru**: implicitní heartbeat z poll cyklu (`worker_heartbeats` tabulka), offline detekce v admin UI + email alert přes cron

### Adapter API
- [ ] **Error handling v REST API**: Tracy debug stránky nesmí uniknout ven — vždy JSON response
- [ ] **CORS hlavičky**: pro volání z ChatGPT Actions a externích klientů
- [ ] **Rate limit response headers**: `X-RateLimit-Remaining`, `Retry-After`
- [ ] **Timeout handling**: když job běží >20s a klient polluje, jasně komunikovat stav

### Admin UI
- [ ] **CRUD pro tools**: přidávání/editace/mazání nástrojů přímo v administraci
- [x] **Job detail view**: zobrazit výsledek jobu, artefakty, error message, trvání
- [x] **Artifact management**: zobrazit/stáhnout artefakty z admin UI
- [ ] **Service account CRUD**: správa service accountů (credentials) v UI místo přímého SQL
- [x] **Worker status bar**: horizontální lišta v admin UI ukazující stav workeru (idle/busy/offline), s expandovatelným detailem
- [x] **Video nahrávání jobů**: Playwright `recordVideo` — automatický záznam průběhu každého jobu, přehrání v admin UI
- [x] **Job retry**: tlačítko pro opakování selhané/timeout jobu (vytvoří nový job se stejnými parametry, propojení přes `retry_of_job_id`)
- [x] **Job cancel**: tlačítko pro zrušení pending/processing jobu z admin UI
- [x] **Audit log pro admin akce na jobech**: logování cancel/retry do audit logu

### Infrastruktura
- [ ] **Produkční deployment konfigurace**: Docker Compose / DDEV pro produkci
- [ ] **Environment-based config**: credentials, URLs, secrets z env proměnných (ne z kódu/migrace)
- [ ] **Logging**: strukturované logy (JSON) pro adapter i worker
- [ ] **Monitoring**: základní metriky — počet jobů, chybovost, průměrná doba zpracování

---

## Milník 2: Technologický dluh

Odstranění technických nedostatků, které brzdí další rozvoj nebo představují riziko.

### Bezpečnost
- [ ] **Odstranit výchozí admin heslo z migrací**: `password_hash('admin123')` v SQL seedu
- [ ] **Hashovat API tokeny**: v DB ukládat hash, ne plaintext (`api_tokens.token`)
- [ ] **CSRF ochrana** na admin formulářích
- [ ] **Šifrování credentials service accountů**: v DB jsou uloženy jako plaintext
- [ ] **Audit log cleanup**: automatické mazání starých záznamů (GDPR, úložiště)

### Architektura
- [ ] **Sjednotit UUID generování**: `ArtifactRepository` a `JobRepository` mají každý vlastní UUID generátor — extrahovat do sdílené utility
- [ ] **Sjednotit `LEGACY_PM_BASE_URL`**: každý handler má vlastní `process.env.LEGACY_PM_BASE_URL ?? 'https://hirola.xart.cz/...'` — centralizovat do konfigurace
- [ ] **Sjednotit contracts cestu**: `__DIR__ . '/../../../../packages/contracts/'` je na několika místech — předat přes DI
- [ ] **SchemaValidator**: cesta ke schématům je relativní a fragile — předat absolutní cestu přes config
- [x] **Job timeout handling**: `processTimeouts()` automaticky detekuje zaseklé joby (timeout_seconds + grace period), cron skript `cron-maintenance.php` běží nezávisle na workeru
- [ ] **DB indexy**: přidat chybějící indexy na `jobs.status`, `jobs.client_id`, `audit_log.created_at`
- [ ] **Migrace**: přejít na systémový migration tool (Nextras Migrations / Doctrine Migrations) místo ručních SQL souborů

### Worker
- [ ] **Browser pool / reuse**: každý job startuje nový browser — reusovat context pro výkon
- [ ] **Playwright resource management**: zajistit uvolnění browseru i při chybě (finally bloky)
- [ ] **Konfigurovatelný polling interval**: worker polling `GET /jobs/next` je hardcoded — přesunout do env
- [ ] **Structured error reporting**: worker vrací jen string error — přidat error codes, stack traces (v dev mode)

---

## Milník 3: Code Quality

Zlepšení kvality kódu, testovatelnosti a vývojářského komfortu.

### Testy
- [x] **Unit testy pro McpFacade**: mock repozitáře, otestovat auth flow, permission bypass, rate limiting
- [x] **Unit testy pro SchemaValidator**: validní/nevalidní vstupy pro každý tool
- [x] **Integration testy pro REST API**: HTTP requesty na V1Presenter, ověřit status kódy a response formát
- [x] **Worker handler testy**: mock Playwright page, ověřit scraping logiku
- [x] **E2E test**: celý flow MCP call → job → worker → result → polling
- [x] **CI pipeline**: GitHub Actions — lint, testy, type-check

### Typing & Lint
- [x] **PHP strict types všude**: ověřit že všechny soubory mají `declare(strict_types=1)`
- [x] **PHPStan / Psalm**: zavést statickou analýzu, vyřešit existující chyby
- [x] **ESLint + strict TS config**: pro worker (`strict: true`, `noUncheckedIndexedAccess`)
- [x] **PHP CS Fixer**: sjednotit coding style (PSR-12 nebo Nette coding standard)

### Dokumentace
- [x] **README**: setup instrukce, architektura, jak přidat nový tool
- [x] **API dokumentace**: popis autentizace, rate limitů, error formátu
- [x] **Worker handler guide**: jak napsat nový Playwright handler
- [x] **Contracts dokumentace**: popis JSON schémat, konvence pojmenování

---

## Milník 4: Nice to Have

Vylepšení, která nejsou kritická, ale zlepší UX, výkon nebo rozšiřitelnost.

### Funkce
- [ ] **Webhook notifikace**: po dokončení jobu poslat webhook na konfigurovanou URL
- [ ] **Batch operace**: spustit více toolů najednou, sledovat jako skupinu
- [ ] **Tool versioning**: verzování tool schémat, zpětná kompatibilita
- [ ] **Job priority**: možnost nastavit prioritu jobu (express vs. normal)
- [ ] **Scheduled jobs**: CRON-like plánování opakovaných tool callů
- [ ] **Artifact expiration**: automatické mazání artefaktů po X dnech
- [ ] **SSE/WebSocket pro job status**: real-time notifikace místo pollingu

### Admin UI
- [ ] **Dashboard s grafy**: počet jobů za den, chybovost, průměrná doba
- [ ] **Bulk operace**: hromadné mazání jobů, deaktivace tokenů
- [ ] **Dark mode**
- [x] **Notifikace v UI**: toast notifikace při dokončení/selhání jobu + worker status bar
- [ ] **Aktualizace textů v UI**: sjednotit české texty, přeložit anglické placeholdery, konzistentní terminologie

### Integrace
- [ ] **Make.com šablona**: předpřipravený scénář pro běžné use-cases
- [ ] **n8n node**: custom node pro PM Gateway
- [ ] **Slack bot**: notifikace o dokončených jobech do Slack kanálu
- [ ] **MCP SSE transport**: kromě HTTP i Server-Sent Events pro MCP klienty (Cursor, Windsurf)

### Výkon
- [ ] **Redis queue**: nahradit DB polling za Redis-based frontu (BullMQ)
- [ ] **Connection pooling**: pro DB připojení v adapteru
- [ ] **Response caching**: cachovat OpenAPI spec (invalidovat při změně permissions)
- [ ] **Worker scaling**: podpora více worker instancí s lock mechanismem

---

## Milník 5: Scenario Builder — vizuální tvorba toolů

Umožnit vytvářet nové Playwright tooly bez psaní kódu, pomocí nahrávání a vizuálního editoru.

### Fáze 1: JSON scénáře + generický runner
- [ ] **JSON scenario formát**: deklarativní popis kroků (goto, fill, click, wait, screenshot, scrape)
- [ ] **Scenario runner handler**: generický worker handler `run_scenario`, který vykoná libovolný JSON scénář
- [ ] **DB tabulka `scenarios`**: uložení scénářů (name, steps JSON, input schema, napojení na tool)
- [ ] **Admin UI CRUD scénářů**: vytváření/editace kroků, náhled, test run s videem/screenshoty

### Fáze 2: Import z Playwright codegen
- [ ] **Lokální nahrávání**: uživatel spustí `npx playwright codegen` u sebe, nakliká scénář
- [ ] **Import do admin UI**: upload/paste vygenerovaného kódu, parsování do JSON scénáře
- [ ] **Mapování proměnných**: UI pro namapování payload parametrů na selektory (co kam vyplnit)
- [ ] **Test & refine**: spuštění importovaného scénáře s test daty, úprava kroků

### Fáze 3 (experimentální): In-browser nahrávání přes noVNC
- [ ] **Xvfb + noVNC na serveru**: Playwright codegen s viditelným prohlížečem, streamovaný přes WebSocket
- [ ] **noVNC iframe v admin UI**: uživatel kliká přímo v admin UI, na pozadí se generuje scénář
- [ ] **Automatická konverze**: po ukončení nahrávání se codegen výstup parsuje do JSON scénáře
- [ ] **Bezpečnost**: izolace session, timeout, omezení přístupu k nahrávání

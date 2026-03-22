# PM Gateway — Backlog

## Milník 1: MVP

Dokončení základní funkcionality tak, aby systém byl nasaditelný a použitelný v produkci.

### Worker & Tooly
- ~~**get_task handler**~~ — nahrazeno PM tooly (`pm_read_task`, `pm_open_task`) v Milníku 5
- ~~**export_filtered_tasks handler**~~ — nahrazeno PM toolem `pm_export_csv` v Milníku 5
- [ ] **Retry logika pro Playwright handlery**: opakovat přihlášení při selhání (network timeout, session expiry)
- [ ] **Graceful shutdown workeru**: při SIGTERM dokončit rozpracovaný job místo tvrdého ukončení
- [x] **Health-check workeru**: implicitní heartbeat z poll cyklu (`worker_heartbeats` tabulka), offline detekce v admin UI + email alert přes cron

### Adapter API
- [x] **Error handling v REST API**: `sendErrorJson()` v V1Presenter — vždy JSON response, Tracy debug stránky neuniknou
- [ ] **CORS hlavičky**: pro volání z ChatGPT Actions a externích klientů
- [ ] **Rate limit response headers**: `X-RateLimit-Remaining`, `Retry-After` (RateLimitService existuje, ale neposílá hlavičky)
- [ ] **Timeout handling**: když job běží >20s a klient polluje, jasně komunikovat stav

### Admin UI
- [ ] **CRUD pro tools**: přidávání/editace/mazání nástrojů přímo v administraci (toggle active/inactive funguje, chybí edit/delete)
- [x] **Job detail view**: zobrazit výsledek jobu, artefakty, error message, trvání
- [x] **Artifact management**: zobrazit/stáhnout artefakty z admin UI
- [x] **Service account CRUD**: správa service accountů v UI — create, edit, delete + šifrování hesel přes EncryptionService
- [x] **Worker status bar**: horizontální lišta v admin UI ukazující stav workeru (idle/busy/offline), s expandovatelným detailem
- [x] **Video nahrávání jobů**: Playwright `recordVideo` — automatický záznam průběhu každého jobu, přehrání v admin UI
- [x] **Job retry**: tlačítko pro opakování selhané/timeout jobu (vytvoří nový job se stejnými parametry, propojení přes `retry_of_job_id`)
- [x] **Job cancel**: tlačítko pro zrušení pending/processing jobu z admin UI
- [x] **Audit log pro admin akce na jobech**: logování cancel/retry do audit logu

### Infrastruktura
- [ ] **Produkční deployment konfigurace**: Docker Compose / DDEV pro produkci
- [x] **Environment-based config**: credentials, URLs, secrets z env proměnných — `.env.example` + `config/common.neon`
- [ ] **Logging**: strukturované logy (JSON) pro adapter i worker (audit log v DB existuje, chybí JSON stream logy)
- [ ] **Monitoring**: základní metriky — počet jobů, chybovost, průměrná doba zpracování

---

## Milník 2: Technologický dluh

Odstranění technických nedostatků, které brzdí další rozvoj nebo představují riziko.

### Bezpečnost
- [ ] **Odstranit výchozí admin heslo z migrací**: `password_hash('admin123')` v SQL seedu
- [x] **Hashovat API tokeny**: v DB se ukládá `token_hash`, ne plaintext — `ApiTokenRepository::findByHash()`
- [x] **CSRF ochrana** na admin formulářích
- [x] **Šifrování credentials service accountů**: AES-256-CBC přes `EncryptionService`, sloupec `password_encrypted`
- [ ] **Audit log cleanup**: automatické mazání starých záznamů (GDPR, úložiště)

### Architektura
- [ ] **Sjednotit UUID generování**: `ArtifactRepository` a `JobRepository` mají každý vlastní UUID generátor — extrahovat do sdílené utility
- [ ] **Sjednotit `LEGACY_PM_BASE_URL`**: `FALLBACK_BASE_URL` se opakuje v `standaloneRunner.ts` i `scenarioRunner.ts` — centralizovat
- [ ] **Sjednotit contracts cestu**: `__DIR__ . '/../../../../packages/contracts/'` je na několika místech — předat přes DI
- [ ] **SchemaValidator**: cesta ke schématům je relativní a fragile — předat absolutní cestu přes config
- [x] **Job timeout handling**: `processTimeouts()` automaticky detekuje zaseklé joby (timeout_seconds + grace period), cron skript `cron-maintenance.php` běží nezávisle na workeru
- [ ] **DB indexy**: `jobs.status` a `audit_log.created_at` indexy existují, chybí `jobs.client_id`
- [ ] **Migrace**: přejít na systémový migration tool — `nextras/migrations` je v `composer.json`, ale nepoužívá se

### Worker
- [ ] **Browser pool / reuse**: každý job startuje nový browser — reusovat context pro výkon
- [x] **Playwright resource management**: `finally` bloky v `standaloneRunner.ts` i `scenarioRunner.ts` zajistí uvolnění browseru
- [x] **Konfigurovatelný polling interval**: `POLL_INTERVAL_MS` env proměnná (default 5000ms)
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
- [x] **Aktualizace textů v UI**: česká terminologie konzistentní napříč UI

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

## Milník 5: Scénáře a PM tooly

Kompozitní scénáře pro ovládání interní PM aplikace. Tooly = atomické browser operace, scénáře = řetězy toolů s větvením a cykly. Scénáře i tooly volatelné přes MCP/REST stejným rozhraním.

### Fáze 1: Infrastruktura scénářů ✅

#### DB a backend
- [x] **DB tabulka `scenarios`**: migrace 005 — `id, name, description, input_schema (JSON), steps (JSON), is_active, created_at, updated_at`
- [x] **Rozšíření tabulky `jobs`**: sloupce `scenario_id (FK NULL)`, `step_results (JSON)`, `awaiting_input_context`
- [x] **ScenarioRepository**: CRUD operace, `findByName()`, `findAllActive()`
- [x] **Template expression parser**: `templateParser.ts` — resolver pro `{{input.x}}`, `{{step_id.output.y}}`, `{{step_id.output.results[0].path_info}}`

#### Worker — scenario runner
- [x] **`run_scenario` handler**: `scenarioRunner.ts` — generický worker handler, parsuje JSON scénář a spouští kroky sekvenčně
- [x] **Shared browser session**: scenario runner drží jednu Playwright `page` a předává ji všem krokům
- [x] **Context bag**: akumulátor `{ input, [step_id]: { output, status, duration_ms } }` — každý krok zapisuje svůj výstup
- [x] **Step types**: podpora `tool`, `condition`, `loop` (`scenario` typ připraven, zatím neaktivní)
- [x] **Expect klauzule**: `"expect": { "count": 1 }` — automatická validace výstupu kroku s disambiguací
- [x] **Step-level screenshots**: infrastruktura připravena
- [x] **Error handling**: při selhání kroku zaloguje step_id, tool, error do step_results

#### Refaktor tool handlerů
- [x] **Dual-mode tooly**: každý handler funguje standalone (vlastní browser) i in-scenario (přijme existující `page`) přes `ToolContext`
- [x] **Standardizovaný output formát**: každý tool vrací `{ success: bool, ...data }`

#### API integrace
- [x] **Scénáře v MCP/REST**: scénář volatelný jako `POST /api/v1/tools/scenario_{name}`, auto-generované input schema
- [x] **Scénáře v OpenAPI spec**: scénáře se objeví vedle toolů v `GET /api/v1/openapi.json`

### Fáze 2: Atomické PM tooly ✅

Všech 14 PM toolů implementováno v `apps/worker/src/tools/pm/`.

#### Navigace a vyhledávání
- [x] **`pm_login`**: přihlášení do PM aplikace
- [x] **`pm_open_project`**: vyhledá projekt podle názvu a otevře ho, s disambiguací
- [x] **`pm_open_task`**: vyhledá úkol v aktuálním projektu a otevře ho, s disambiguací

#### Úkoly
- [x] **`pm_read_task`**: přečte detaily aktuálního úkolu
- [x] **`pm_update_task`**: upraví pole úkolu (popis, assignee, datum, ...)
- [x] **`pm_close_task`**: zavře aktuální úkol

#### Komentáře
- [x] **`pm_create_comment`**: vytvoří komentář k aktuálnímu úkolu
- [x] **`pm_search_comments`**: vyhledá v komentářích aktuálního úkolu

#### Podúkoly
- [x] **`pm_create_subtask`**: vytvoří podúkol
- [x] **`pm_search_subtasks`**: vyhledá v podúkolech
- [x] **`pm_close_subtask`**: zavře podúkol
- [x] **`pm_update_subtask`**: upraví podúkol

#### Výkazy a exporty
- [x] **`pm_time_track`**: vykáže čas na úkolu/podúkolu
- [x] **`pm_export_csv`**: exportuje úkoly nebo výkazy jako CSV artefakt

### Fáze 3: Hotové scénáře

Předpřipravené scénáře skladající PM tooly. Infrastruktura hotová, scénáře je třeba vytvořit přes Admin UI.

- [ ] **`add_comment`**: přidat komentář k úkolu — kroky: login → open_project → open_task → create_comment
- [ ] **`create_subtasks`**: vytvořit podúkoly — kroky: login → open_project → open_task → loop(create_subtask)
- [ ] **`close_task_with_comment`**: zavřít úkol s komentářem — kroky: login → open_project → open_task → condition(comment → create_comment) → close_task
- [ ] **`track_time`**: vykázat čas — kroky: login → open_project → open_task → time_track
- [ ] **`export_task_report`**: export úkolů jako CSV — kroky: login → open_project → export_csv(tasks)
- [ ] **`export_timesheet_report`**: export výkazů jako CSV — kroky: login → open_project → export_csv(timesheets)

### Fáze 4: Admin UI pro scénáře ✅

- [x] **Seznam scénářů**: stránka s přehledem všech scénářů (název, popis, počet kroků, aktivní/neaktivní)
- [x] **Detail/editor scénáře**: vizuální seznam kroků — pro každý krok výběr toolu, mapování vstupů, expect pravidla
- [x] **Input schema editor**: definice vstupních parametrů scénáře
- [x] **Test run**: spustit scénář s test daty, zobrazit výsledek každého kroku
- [x] **Step results v job detailu**: průběh kroků scénáře s výstupy v job detail view

### Fáze 5 (budoucnost): Pokročilé nástroje

- [ ] **Import z Playwright codegen**: upload/paste vygenerovaného kódu, parsování do tool handleru
- [ ] **noVNC nahrávání**: in-browser nahrávání scénářů přes Xvfb + noVNC iframe v admin UI
- [ ] **Scenario versioning**: verzování scénářů, rollback na předchozí verzi
- [ ] **Scenario scheduling**: CRON-like plánování opakovaného spouštění scénářů

---

## Milník 6: Admin UI Polish — UX/UI Review ✅

Výsledky komplexního UI/UX auditu admin rozhraní. Zaměřeno na škálovatelnost, bezpečnost, konzistenci a celkový polish.

### P0 — Kritické (blokuje škálování)
- [x] **Paginace na list stránkách**: Jobs a Audit Log nemají limit — při větším provozu stránky zamrznou. Tabler `pagination` + server-side `LIMIT/OFFSET`, default 50 řádků
- [x] **Destruktivní akce přes POST**: Cancel, Retry, Toggle, Delete, Revoke jsou GET `<a>` linky — CSRF zranitelnost. Zabalit do `<form method="post">` s CSRF tokenem

### P1 — Vysoká priorita
- [x] **Flash messages error handling**: typ `error`/`danger` se renderuje jako zelený success alert — rozšířit na mapping error→alert-danger, warning→alert-warning, success→alert-success
- [x] **Payload/Result `<pre>` max-height**: velký JSON odsune vše pod sebe — `max-height: 400px; overflow-y: auto`
- [x] **Vizuální indikace aktivních filtrů**: aktivní filtr na Job listu vypadá stejně jako prázdný — badge s počtem + "Reset filtrů" link
- [x] **Audit Log status badge**: raw string místo barevného badge — sjednotit s badge-{status} patternem
- [x] **Dashboard aktivita status badge**: tabulka "Poslední aktivita" má raw status vedle tabulky jobů s badge
- [x] **Klikatelné KPI karty na dashboardu**: "Pending" → `/jobs?status=pending`, "Selhané" → `/jobs?status=failed` atd.
- [x] **Modální potvrzení místo `confirm()`**: nahradit nativní dialogy Tabler modal komponentou
- [x] **Empty states ve všech tabulkách**: prázdný filtr → prázdná tabulka bez zprávy — přidat `{else}` větev s empty state

### P2 — Střední priorita (konzistence a polish)
- [x] **Sjednotit češtinu/angličtinu v navigaci**: "Dashboard", "Audit Log" vs "Klienti", "Tooly", "Joby" — hybridní tvary
- [x] **Standardizovat Job ID truncation**: 5 různých délek (8, 12, full) — sjednotit na 12 chars + `<code>` + `title`
- [x] **Sjednotit Edit button styling**: 3 různé patterny (dropdown, plain btn, btn-outline) — `btn-outline-secondary`
- [x] **Badge vs status dot konzistence**: detail page používá `status` dot, listy `badge` + "Ano"/"Ne"
- [x] **Schema panel loading state**: žádný spinner při načítání schématu po výběru toolu
- [x] **Schema tabulka popis column**: chybí sloupec s `description` z JSON Schema
- [x] **Worker status bar XSS**: `innerHTML` bez escapování `tool_name`/`client_name` — použít `textContent`
- [x] **Video player mimo tabulku**: embedovaný v `<tr>` — přesunout do vlastní sekce
- [x] **Screenshots responsive grid**: `col-md-4` → `col-sm-6 col-md-4 col-xl-3` + click-to-enlarge
- [x] **Pokusů tooltip na touch**: `title` atribut → Tabler `data-bs-toggle="tooltip"`
- [x] **Audit Log date inputs labely**: chybí placeholder/label "Od"/"Do"
- [x] **IP Whitelist formátování**: raw string → `<code>` badge/chipy

### P3 — Nice to have
- [x] **Breadcrumb navigace** na detail stránkách
- [x] **Login page background**: `bg-white` → `bg-body-secondary`
- [x] **Default title block**: chybějící `{block title}` → prázdný `<title>`
- [x] **Keyboard shortcut** pro "Nový job" (Alt+N)
- [x] **Worker bar `aria-expanded`** a `aria-controls`
- [x] **Sign-in form viditelné labely** (WCAG)
- [x] **Staleness indikátor** mezi pollingy
- [x] **Favicon**

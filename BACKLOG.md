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
- [x] **CSRF ochrana** na admin formulářích
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

## Milník 5: Scénáře a PM tooly

Kompozitní scénáře pro ovládání interní PM aplikace. Tooly = atomické browser operace, scénáře = řetězy toolů s větvením a cykly. Scénáře i tooly volatelné přes MCP/REST stejným rozhraním.

### Fáze 1: Infrastruktura scénářů

#### DB a backend
- [ ] **DB tabulka `scenarios`**: `id, name, description, input_schema (JSON), steps (JSON), is_active, created_at, updated_at`
- [ ] **Rozšíření tabulky `jobs`**: sloupce `scenario_id (FK NULL)`, `step_results (JSON)` pro záznam průběhu kroků
- [ ] **ScenarioRepository**: CRUD operace, `findByName()`, `findAllActive()`
- [ ] **Template expression parser**: resolver pro `{{input.x}}`, `{{step_id.output.y}}`, `{{step_id.output.results[0].path_info}}` — nahrazení v input mapě před spuštěním kroku

#### Worker — scenario runner
- [ ] **`run_scenario` handler**: generický worker handler, parsuje JSON scénář a spouští kroky sekvenčně
- [ ] **Shared browser session**: scenario runner drží jednu Playwright `page` a předává ji všem krokům
- [ ] **Context bag**: akumulátor `{ input, [step_id]: { output, status, duration_ms } }` — každý krok zapisuje svůj výstup
- [ ] **Step types**: podpora `tool`, `condition`, `loop`, `scenario` (volání vnořeného scénáře)
- [ ] **Expect klauzule**: `"expect": { "count": 1 }` — automatická validace výstupu kroku, selhání s popisnou chybou
- [ ] **Step-level screenshots**: screenshot po každém kroku pro debugging
- [ ] **Error handling**: při selhání kroku zalogovat step_id, tool, error, screenshot; scénář = failed s detailem „step 3/6 failed: …"

#### Refaktor tool handlerů
- [ ] **Dual-mode tooly**: každý handler musí fungovat standalone (vlastní browser) i in-scenario (přijme existující `page`)
- [ ] **Standardizovaný output formát**: každý tool vrací `{ success: bool, ...data }` pro konzistentní expect validaci

#### API integrace
- [ ] **Scénáře v MCP/REST**: scénář volatelný jako `POST /api/v1/tools/scenario_{name}`, auto-generované input schema
- [ ] **Scénáře v OpenAPI spec**: scénáře se objeví vedle toolů v `GET /api/v1/openapi.json`

### Fáze 2: Atomické PM tooly

Každý tool pracuje na úrovni jedné browser interakce. Tooly s prefixem `pm_open_*` kombinují vyhledání + otevření, s disambiguací (0 nebo >1 výsledek → selhání s popisným seznamem).

#### Navigace a vyhledávání
- [ ] **`pm_login`**: přihlášení do PM aplikace — input: `—`, output: `{ logged_in }`
- [ ] **`pm_open_project`**: vyhledá projekt podle názvu a otevře ho — input: `{ query }`, output: `{ name, path_info }`, expect 1 výsledek
- [ ] **`pm_open_task`**: vyhledá úkol v aktuálním projektu a otevře ho — input: `{ query }`, output: `{ task_id, name, path_info, status }`, expect 1 výsledek

#### Úkoly
- [ ] **`pm_read_task`**: přečte detaily aktuálního úkolu — input: `—`, output: `{ description, status, assignee, dates, ... }`
- [ ] **`pm_update_task`**: upraví pole úkolu (popis, assignee, datum, ...) — input: `{ fields }`, output: `{ success }`
- [ ] **`pm_close_task`**: zavře aktuální úkol — input: `—`, output: `{ success }`

#### Komentáře
- [ ] **`pm_create_comment`**: vytvoří komentář k aktuálnímu úkolu — input: `{ text }`, output: `{ success }`
- [ ] **`pm_search_comments`**: vyhledá v komentářích aktuálního úkolu — input: `{ query }`, output: `{ results[], count }`

#### Podúkoly
- [ ] **`pm_create_subtask`**: vytvoří podúkol — input: `{ name, assignee?, due_date? }`, output: `{ subtask_id, path_info }`
- [ ] **`pm_search_subtasks`**: vyhledá v podúkolech — input: `{ query }`, output: `{ results[], count }`
- [ ] **`pm_close_subtask`**: zavře podúkol — input: `{ path_info }`, output: `{ success }`
- [ ] **`pm_update_subtask`**: upraví podúkol — input: `{ path_info, fields }`, output: `{ success }`

#### Výkazy a exporty
- [ ] **`pm_time_track`**: vykáže čas na úkolu/podúkolu — input: `{ hours, date, note? }`, output: `{ success }`
- [ ] **`pm_export_csv`**: exportuje úkoly nebo výkazy — input: `{ type: 'tasks'|'timesheets', filters? }`, output: `{ artifact_id }` (CSV soubor jako artefakt)

### Fáze 3: Hotové scénáře

Předpřipravené scénáře skladající PM tooly. Každý scénář je JSON definice uložená v DB, volatelný přes MCP/REST.

- [ ] **`add_comment`**: přidat komentář k úkolu — input: `{ project, task, text }` — kroky: login → open_project → open_task → create_comment
- [ ] **`create_subtasks`**: vytvořit podúkoly — input: `{ project, task, subtasks[{ name, assignee?, due_date? }] }` — kroky: login → open_project → open_task → loop(create_subtask)
- [ ] **`close_task_with_comment`**: zavřít úkol s komentářem — input: `{ project, task, comment? }` — kroky: login → open_project → open_task → condition(comment → create_comment) → close_task
- [ ] **`track_time`**: vykázat čas — input: `{ project, task, hours, date, note? }` — kroky: login → open_project → open_task → time_track
- [ ] **`export_task_report`**: export úkolů jako CSV — input: `{ project, filters? }` — kroky: login → open_project → export_csv(tasks)
- [ ] **`export_timesheet_report`**: export výkazů jako CSV — input: `{ project, filters? }` — kroky: login → open_project → export_csv(timesheets)

### Fáze 4: Admin UI pro scénáře

- [ ] **Seznam scénářů**: stránka s přehledem všech scénářů (název, popis, počet kroků, aktivní/neaktivní)
- [ ] **Detail/editor scénáře**: vizuální seznam kroků — pro každý krok výběr toolu, mapování vstupů, expect pravidla
- [ ] **Input schema editor**: definice vstupních parametrů scénáře (jméno, typ, povinné/volitelné, popis)
- [ ] **Test run**: spustit scénář s test daty, zobrazit výsledek každého kroku (status, output, screenshot, trvání)
- [ ] **Step results v job detailu**: v existujícím job detail view zobrazit průběh kroků scénáře s výstupy a screenshoty

### Fáze 5 (budoucnost): Pokročilé nástroje

- [ ] **Import z Playwright codegen**: upload/paste vygenerovaného kódu, parsování do tool handleru
- [ ] **noVNC nahrávání**: in-browser nahrávání scénářů přes Xvfb + noVNC iframe v admin UI
- [ ] **Scenario versioning**: verzování scénářů, rollback na předchozí verzi
- [ ] **Scenario scheduling**: CRON-like plánování opakovaného spouštění scénářů

---

## Milník 6: Admin UI Polish — UX/UI Review

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

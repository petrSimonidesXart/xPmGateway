# PM Gateway — Backlog

## Milník 1: MVP

Dokončení základní funkcionality tak, aby systém byl nasaditelný a použitelný v produkci.

### Worker & Tooly
- [ ] **get_task handler**: robustní scraping — ošetřit chybějící pole, timeouty, fallbacky pro změněný DOM
- [ ] **export_filtered_tasks handler**: ověřit funkčnost CSV exportu, ošetřit případ kdy filtr vrátí 0 výsledků
- [ ] **Retry logika pro Playwright handlery**: opakovat přihlášení při selhání (network timeout, session expiry)
- [ ] **Graceful shutdown workeru**: při SIGTERM dokončit rozpracovaný job místo tvrdého ukončení
- [ ] **Health-check endpoint workeru**: aby orchestrátor (DDEV/Docker) věděl, že worker žije

### Adapter API
- [ ] **Error handling v REST API**: Tracy debug stránky nesmí uniknout ven — vždy JSON response
- [ ] **CORS hlavičky**: pro volání z ChatGPT Actions a externích klientů
- [ ] **Rate limit response headers**: `X-RateLimit-Remaining`, `Retry-After`
- [ ] **Timeout handling**: když job běží >20s a klient polluje, jasně komunikovat stav

### Admin UI
- [ ] **CRUD pro tools**: přidávání/editace/mazání nástrojů přímo v administraci
- [ ] **Job detail view**: zobrazit výsledek jobu, artefakty, error message, trvání
- [ ] **Artifact management**: zobrazit/stáhnout artefakty z admin UI
- [ ] **Service account CRUD**: správa service accountů (credentials) v UI místo přímého SQL

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
- [ ] **Job timeout handling**: worker nemá mechanismus pro timeout dlouho běžících jobů
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
- [ ] **Unit testy pro McpFacade**: mock repozitáře, otestovat auth flow, permission bypass, rate limiting
- [ ] **Unit testy pro SchemaValidator**: validní/nevalidní vstupy pro každý tool
- [ ] **Integration testy pro REST API**: HTTP requesty na V1Presenter, ověřit status kódy a response formát
- [ ] **Worker handler testy**: mock Playwright page, ověřit scraping logiku
- [ ] **E2E test**: celý flow MCP call → job → worker → result → polling
- [ ] **CI pipeline**: GitHub Actions — lint, testy, type-check

### Typing & Lint
- [ ] **PHP strict types všude**: ověřit že všechny soubory mají `declare(strict_types=1)`
- [ ] **PHPStan / Psalm**: zavést statickou analýzu, vyřešit existující chyby
- [ ] **ESLint + strict TS config**: pro worker (`strict: true`, `noUncheckedIndexedAccess`)
- [ ] **PHP CS Fixer**: sjednotit coding style (PSR-12 nebo Nette coding standard)

### Dokumentace
- [ ] **README**: setup instrukce, architektura, jak přidat nový tool
- [ ] **API dokumentace**: popis autentizace, rate limitů, error formátu
- [ ] **Worker handler guide**: jak napsat nový Playwright handler
- [ ] **Contracts dokumentace**: popis JSON schémat, konvence pojmenování

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
- [ ] **Dark mode** 🌙
- [ ] **Notifikace v UI**: při dokončení jobu, při chybě

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

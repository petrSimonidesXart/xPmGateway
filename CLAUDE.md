# xPmGateway

## GitHub

- GitHub CLI (`gh`) je nakonfigurované přes `GH_TOKEN` env proměnnou v `.claude/settings.local.json`
- Token patří work účtu **petrSimonidesXart** — veškeré `gh` operace (PR, issues, releases) běží pod tímto účtem
- Git push/pull používá SSH alias `github-work` (nastavený v SSH config)
- Při vytváření PR nebo interakci s GitHub API není potřeba přepínat účty — vše je automatické

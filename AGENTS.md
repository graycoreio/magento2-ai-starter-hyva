# AGENTS.md

Guidance for AI coding agents (Claude Code, etc.) working in this repo.

This is the **Magento AI Starter — Hyvä**: a Magento Open Source project with the Hyvä default theme, pre-wired for GitHub Codespaces. It is a learning / prototyping baseline, not a production store.

Users drive the project by typing natural-language requests at the `claude` prompt. The sections below map the requests in `README.md` to the exact commands you should run.

## Environment

- Working directory: `/workspace`
- Runtime: VS Code devcontainer (`.devcontainer/magento2-devcontainer/`) with PHP, MySQL, Redis, OpenSearch/Elasticsearch, RabbitMQ, and Mailpit running as sibling Docker services.
- Magento root is the repo root (not a subdirectory). `bin/magento`, `app/`, `pub/`, `vendor/` are at `/workspace`.
- Storefront is exposed on the forwarded `nginx:8000` port.

## Common user requests

### "Install Magento"

Run the installer script. It brings up MySQL / Redis / OpenSearch and runs `bin/magento setup:install`.

```bash
.devcontainer/magento2-devcontainer/bin/setup-install.sh | bash
```

### "Switch the storefront theme to Hyvä"

```bash
bin/magento config:set --lock-config design/theme/theme_id Hyva/default
bin/magento cache:clean
```

`--lock-config` writes to `app/etc/config.php` (not the `core_config_data` DB table), so the theme choice lives in code and gets committed.

### "Save a checkpoint" / "Save this"

Commit everything with a [Conventional Commits](https://www.conventionalcommits.org/) prefix. Pick the label from the nature of the change:

- `chore:` — setup, config, no user-visible change (no release bump)
- `docs:` — documentation only (no release bump)
- `fix:` — bug fix (patch bump)
- `feat:` — new user-visible feature (minor bump)

```bash
git add -A
git commit -m "<type>: <short description>"
```

`release-please` reads these labels and proposes versioned releases on push.

### "Save this and push it to GitHub"

Commit (as above) and then:

```bash
git push
```

### Storefront changes

- Edit Hyvä templates / CMS content, not core Magento or `vendor/`.
- Hyvä uses Tailwind + Alpine.js; prefer inline `<style>` blocks or Tailwind utility classes for small one-off touches.
- After template or config changes: `bin/magento cache:clean`.
- After changes to `app/etc/config.php` or DI: `bin/magento setup:upgrade && bin/magento cache:clean`.

## Conventions

- **Magento credentials / Hyvä auth** are provided by the user via `composer config --auth ...` or the `COMPOSER_AUTH` Codespaces secret. Never ask for them in a prompt and never log them.
- **Hyvä commercial credentials** live in `auth.json` (gitignored) or `$COMPOSER_AUTH`. Do not commit `auth.json`.
- **Theme is locked in `app/etc/config.php`** once switched — commit that file alongside the change.
- **CI**: `.github/workflows/check-store.yml` runs unit tests, coding standard, and a smoke test on every push and PR. Don't push changes that you haven't at least tried to build locally.

## Out of scope

This starter is explicitly **not production-ready**. If they do ask, flag the gap honestly rather than papering over it.

# Modern Catholic Plugin Suite

Part of **Modern Catholic** — modular WordPress tools for Catholic parish websites.

---

# Modern Catholic – Update Manager

![License: GPL-3.0-only](https://img.shields.io/badge/License-GPL--3.0--only-blue.svg)
![WordPress: 6.5+](https://img.shields.io/badge/WordPress-6.5%2B-21759b.svg)
![PHP: 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bbb.svg)

Trusted GitHub Release discovery, installation, and native WordPress update integration for Modern Catholic plugins and themes.

---

## Features

- Discovers conventionally named Modern Catholic plugin and theme repositories from trusted GitHub owners
- Maintains one administrator-managed module list with add and remove controls
- Shows the trusted GitHub catalog only from the dedicated Add module view
- Accepts stable, non-draft, non-prerelease GitHub Releases only
- Requires an exact installable `{slug}-{version}.zip` release asset
- Registers eligible releases with WordPress’s native plugin and theme update interfaces
- Installs registered components that are not yet present
- Protects local Git working copies from WordPress filesystem replacement
- Verifies GitHub-provided SHA-256 asset digests when available

---

## Private repositories

Public repositories require no credentials. Private access may use an administrator-managed, Git-ignored `.github-token.php` runtime file. The token is never stored in WordPress options, included in release packages, or displayed after it is saved.

External configuration through `MODERN_CATHOLIC_UPDATES_GITHUB_TOKEN`, a server environment variable, or the secure `modern_catholic_updates_github_token` filter takes precedence.

Never commit, package, log, or print a GitHub token.

---

## Installation

1. Upload the plugin ZIP through **Plugins → Add Plugin → Upload Plugin**.
2. Activate **Modern Catholic – Update Manager**.
3. Open **Plugins → Modern Catholic Updates**.
4. Configure private-repository access only if required.
5. Select **Check now** to refresh release metadata.

Local component directories containing `.git` are intentionally protected. Validate package replacement with separate ZIP-installed staging copies.

---

## Changelog

### 1.0.3

- Fix Install redirects so WordPress receives the selected repository, installer view, and security nonce instead of returning silently to the module list.
- Document runtime-validation approval requirements and ignore local Codex workspace files.

### 1.0.2

- Detect installed plugins by their canonical directory when a saved main-file name is stale, while still preferring the configured entrypoint.
- Run package installs through WordPress's interactive plugin and theme installer screens so restricted hosts can request filesystem credentials and display the underlying installation error.

### 1.0.1

- Simplify the administration screen to one managed module list and an on-demand Add module view.
- Allow built-in, discovered, and custom repositories to be removed and added back.
- Retire Editorial Sections from the built-in module defaults.
- Standardize the GitHub README with Modern Catholic branding, compatibility badges, security guidance, and GPL-3.0-only licensing.

### 1.0.0

- Add a GitHub-backed catalog of conventionally named Modern Catholic plugins and themes with add and install actions.

---

## License

Licensed under the GNU General Public License version 3.0 only (`GPL-3.0-only`).

=== Modern Catholic – Update Manager ===
Contributors: twitchd8
Tags: updates, github, plugins, themes
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Monitors trusted GitHub Releases and integrates exact release ZIP assets with WordPress plugin and theme updates.

== Description ==

Modern Catholic – Update Manager monitors registered GitHub repositories for stable releases. It can:

* Auto-detect installed plugins and themes that reference a trusted GitHub owner.
* Monitor the built-in Modern Catholic component registry.
* Add, disable, enable, and remove future repository definitions.
* Notify administrators when a newer release is available.
* Register updates with WordPress's native update screen.
* Install the latest release of a registered component that is not installed.
* Protect local Git working copies from WordPress filesystem replacement.
* Verify downloaded assets against GitHub-provided SHA-256 digests when available.
* Discover available Modern Catholic plugin and theme repositories visible to GitHub and install their exact stable release packages.

Only non-draft, non-prerelease GitHub Releases are accepted. Every release must include an exact installable ZIP asset named `{slug}-{version}.zip` unless the repository definition specifies another template. Automatically generated GitHub source archives are never used.

== Private repositories ==

Public repositories require no credentials. For private repositories, create a fine-grained GitHub token limited to the required repositories with Contents set to Read-only. Open Plugins > Modern Catholic Updates and paste it into the Private GitHub access field.

The plugin validates the token and writes it to `.github-token.php` inside the plugin directory. That dot-prefixed PHP file produces no browser output, is omitted from WordPress's Plugin File Editor, is ignored by Git, is excluded from release ZIPs, and is never saved in WordPress options or shown again. A normal self-update restores the credential file after replacing the plugin. Keep a secure copy because manually replacing the entire plugin folder can remove it.

Read-only deployments may instead define `MODERN_CATHOLIC_UPDATES_GITHUB_TOKEN` in `wp-config.php`, provide a server environment variable with that name, or use the `modern_catholic_updates_github_token` filter. External configuration takes precedence over the plugin credential file.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add Plugin > Upload Plugin.
2. Activate Modern Catholic – Update Manager.
3. Open Plugins > Modern Catholic Updates, or select Manage updates on the plugin row.
4. Configure private-repository access outside the plugin if needed.
5. Use Check now to refresh release metadata.

== Changelog ==

= Unreleased =
* Add a GitHub-backed catalog of conventionally named Modern Catholic plugins and themes with add and install actions.

= 0.1.2 =
* Add an administrator-only token field backed by an ignored, non-rendering PHP credential file.
* Validate private repository access before saving and preserve the file through normal self-updates.
* Keep constants, environment variables, and secure integration filters as read-only deployment alternatives.

= 0.1.1 =
* Move the management page beneath Plugins and add a direct plugin-row management link.
* Add private-repository setup guidance and secure server environment-variable token support.

= 0.1.0 =
* Initial GitHub release monitoring, native updates, protected Git checkout detection, repository discovery, manual registry, installation, and digest verification.

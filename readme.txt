=== Modern Catholic – Update Manager ===
Contributors: twitchd8
Tags: updates, github, plugins, themes
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
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

Only non-draft, non-prerelease GitHub Releases are accepted. Every release must include an exact installable ZIP asset named `{slug}-{version}.zip` unless the repository definition specifies another template. Automatically generated GitHub source archives are never used.

== Private repositories ==

Public repositories require no credentials. For private repositories, create a fine-grained, read-only GitHub token limited to the required repositories and define it outside the plugin source:

`define( 'MODERN_CATHOLIC_UPDATES_GITHUB_TOKEN', 'your-read-only-token' );`

The token is not stored in WordPress options or displayed in the administration screen. It may also be supplied through the `modern_catholic_updates_github_token` filter by a secure host-specific integration.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add Plugin > Upload Plugin.
2. Activate Modern Catholic – Update Manager.
3. Open Tools > Modern Catholic Updates.
4. Configure private-repository access outside the plugin if needed.
5. Use Check now to refresh release metadata.

== Changelog ==

= 0.1.0 =
* Initial GitHub release monitoring, native updates, protected Git checkout detection, repository discovery, manual registry, installation, and digest verification.

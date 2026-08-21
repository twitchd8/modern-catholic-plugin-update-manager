# Modern Catholic – Update Manager development

This directory is an independent Modern Catholic component repository.

- Develop on `dev`.
- Merge reviewed releases to `main`.
- Publish a stable `vX.Y.Z` GitHub Release from the exact `main` release commit.
- Attach `modern-catholic-plugin-update-manager-X.Y.Z.zip` with exactly one top-level directory named `modern-catholic-plugin-update-manager`.
- Do not publish GitHub's generated source archive as the WordPress installation package.
- Keep GitHub tokens outside the repository and WordPress database.
- `.github-token.php` is runtime-only: keep it ignored, exclude it from release archives, and never inspect or print its saved value.

The update manager intentionally suppresses native updates for component directories containing `.git`. Native package replacement belongs only in a separate ZIP-installed staging site and requires explicit smoke-test approval.

Repository catalog discovery is limited to trusted owners and names matching `modern-catholic-plugin-*`, `modern-catholic-theme`, or `modern-catholic-theme-*`. Catalog installation still requires the exact `{slug}-{version}.zip` stable release asset.

## Validation approval

Run targeted syntax/static checks by default. Native update discovery, package download/replacement, WordPress admin behavior, REST/HTTP behavior, and installation in a ZIP-based staging site are smoke tests. Describe the proposed scope and obtain explicit user approval before running them.

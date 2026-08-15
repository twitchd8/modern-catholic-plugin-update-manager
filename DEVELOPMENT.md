# Modern Catholic – Update Manager development

This directory is an independent Modern Catholic component repository.

- Develop on `dev`.
- Merge reviewed releases to `main`.
- Publish a stable `vX.Y.Z` GitHub Release from the exact `main` release commit.
- Attach `modern-catholic-plugin-update-manager-X.Y.Z.zip` with exactly one top-level directory named `modern-catholic-plugin-update-manager`.
- Do not publish GitHub's generated source archive as the WordPress installation package.
- Keep GitHub tokens outside the repository and WordPress database.
- `.github-token.php` is runtime-only: keep it ignored, exclude it from release archives, and never inspect or print its saved value.

The update manager intentionally suppresses native updates for component directories containing `.git`. Test native package replacement in a separate ZIP-installed staging site.

# Packagist Publishing Setup

The public distribution target for the PHP SDK is [Packagist](https://packagist.org/).
Consumers install it with:

```bash
composer require leapocr/leapocr-php
```

## Where to Publish

- Package registry: `packagist.org`
- Package name: `leapocr/leapocr-php`
- Source repository: `https://github.com/leapocr/leapocr-php`

Packagist uses Git tags as package versions. Do not add a static `version` field
to `composer.json`.

## One-Time Setup

### 1. Create the GitHub repository

The package should live in its own public repository:

```text
https://github.com/leapocr/leapocr-php
```

### 2. Submit the package on Packagist

1. Sign in to [Packagist](https://packagist.org/).
2. Click `Submit`.
3. Enter the repository URL:
   `https://github.com/leapocr/leapocr-php`
4. Confirm the detected package name is `leapocr/leapocr-php`.

### 3. Enable automatic updates

Choose one of these:

- Recommended: connect the repository in Packagist so GitHub pushes refresh the package automatically.
- Also supported by this repo's release workflow: create a Packagist API token and store it in GitHub Actions secrets so the workflow can explicitly notify Packagist after each tag.

### 4. Configure GitHub secrets

If you want the release workflow to ping Packagist automatically, add these
repository secrets:

- `PACKAGIST_USERNAME`
- `PACKAGIST_TOKEN`

These are optional. If they are not set, the workflow still creates the GitHub
release and Packagist can update via its normal GitHub sync/webhook path.

## Release Flow

1. Merge the release-ready changes to `main`.
2. Create an annotated SemVer tag:

```bash
git tag -a v0.1.0 -m "Release v0.1.0"
git push origin v0.1.0
```

3. The `release.yml` workflow will:
   - validate `composer.json`
   - install dependencies
   - run lint and unit tests on PHP 8.3
   - build a release zip via `composer archive`
   - create a GitHub release for the tag
   - notify Packagist if `PACKAGIST_USERNAME` and `PACKAGIST_TOKEN` are configured

## Notes

- Packagist versions come from Git tags like `v0.1.0`.
- The dist archive uses `.gitattributes` so CI files, tests, fixtures, and local tooling are not shipped to consumers.
- Integration tests remain a separate workflow because they require live API credentials.

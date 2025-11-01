# Contributing to csv-helper

Thank you for your interest in contributing to csv-helper! This document explains how to report issues, propose fixes, run tests and follow the project's coding standards so maintainers can review your changes quickly.

## Code of Conduct
Be respectful and constructive. If you need a formal Code of Conduct, open an issue to propose one — contributors should follow common open-source community norms.

## Getting started
1. Fork the repository on GitHub.
2. Clone your fork locally:

```bash
git clone git@github.com:<your-username>/csv-helper.git
cd csv-helper
composer install
```

3. Create a feature branch from `main`:

```bash
git checkout -b feat/my-change
```

## Branches & commits
- Use descriptive branch names: `feat/...`, `fix/...`, `chore/...`.
- Keep commits small and focused. Use present-tense commit messages and reference related issues when appropriate.

## Tests
Run the test suite locally before submitting a pull request.

Use the composer script:

```bash
composer test
# or
vendor/bin/phpunit
```

Add unit tests for any bug fix or new feature. Tests live under `tests/` using PHPUnit 12.

Generate code coverage (requires Xdebug enabled for CLI). This project provides a composer script that runs phpunit with coverage flags and outputs HTML and Clover XML:

```bash
# enable coverage mode for Xdebug, then run the script
composer coverage
# Serve the generated HTML (coverage/) locally
composer coverage:serve
# then open http://localhost:8000 in your browser
```

## Static analysis and style
This project includes phpstan and php-cs-fixer. Run the checks and fix style issues before opening a PR.

```bash
composer phpstan
composer lint   # dry-run check with php-cs-fixer
composer lint-fix  # automatically fix style
```

If you prefer the vendor binaries directly:

```bash
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run
```

Follow PSR-12 and prefer minimal, readable changes.

## Pull requests
1. Push your branch to your fork and open a pull request against `main`.
2. In the PR description, explain the goal, the approach, and any backwards-incompatible changes.
3. Include tests demonstrating the behavior (happy path and edge cases) where applicable.
4. Address review comments and keep the PR focused (split unrelated work into separate PRs).

CI will run tests and static analysis where configured. Maintainters may request changes — please respond to feedback.

## Reporting security issues
For potential security vulnerabilities, please open a private issue or contact a project maintainer rather than posting a public issue.

## Thank you
Thanks for helping improve csv-helper.  
Your contributions make the library better for everyone.

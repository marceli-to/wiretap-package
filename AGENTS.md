# Repository Guidelines

## Project Structure & Module Organization
Source code lives in `src/`, following the `MarceliTo\Wiretap` namespace. `src/Wiretap.php` contains the core logging orchestration, while `src/Providers/WiretapServiceProvider.php` wires bindings and handles publishing. The facade in `src/Facades/Wiretap.php` exposes the logger to Laravel consumers. Package defaults sit in `config/wiretap.php`; update it before tagging releases and keep examples in sync with README snippets. Place all new automated checks under `tests/`, grouping integration scenarios with Orchestra Testbench under `tests/Feature` and lightweight units under `tests/Unit`.

## Build, Test, and Development Commands
Run `composer install` to pull dependencies. Refresh autoload metadata with `composer dump-autoload` whenever namespaces or classes move. Execute the suite locally via `./vendor/bin/phpunit`; pass `--filter Wiretap` to target a specific class. If you need to validate the publishable config, spin up a Laravel sandbox and run `php artisan vendor:publish --provider="MarceliTo\Wiretap\Providers\WiretapServiceProvider" --tag=wiretap-config`.

## Coding Style & Naming Conventions
Adhere to PSR-12 formatting: four-space indentation, braces on new lines, and meaningful docblocks only when context is unclear. Class names use StudlyCase, methods camelCase, configuration keys snake_case. Constructor-injected dependencies should be typed, and avoid introducing global helpers—prefer Laravel facades or explicit imports. Let `composer dump-autoload -o` confirm PSR-4 mappings before opening a PR.

## Testing Guidelines
We rely on PHPUnit with Orchestra Testbench. Each feature or bug fix needs a covering test under `tests/Unit` or `tests/Feature`, named `<Subject>Test.php`. Mock external webhooks with Guzzle fakes instead of hitting the network. Aim to demonstrate both the Laravel log path and webhook path for any new behavior. Run the full suite (`./vendor/bin/phpunit`) before pushing and include failing reproduction tests when reporting bugs.

## Commit & Pull Request Guidelines
Keep commit messages short, imperative, and scoped, e.g., `Add webhook failure logging`. Squash fixup commits locally when possible. Pull requests should explain the problem and solution, list manual or automated test results, and reference related issues. Include screenshots or payload examples when UI output or webhook contracts change, and call out configuration migrations so downstream apps can adjust.

## Configuration & Security Tips
Never commit real webhook URLs or tokens; rely on `.env` overrides and document any new keys in `config/wiretap.php`. When updating defaults, review both README examples and this guide to keep guidance aligned. Validate that sensitive logging data is redacted before merging.

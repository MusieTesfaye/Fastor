# Contributing to Fastor

Thank you for considering contributing to Fastor! The contribution guide follows the standards of the PHP community to ensure Fastor remains a high-quality, high-performance framework.

## Code of Conduct

To ensure that the Fastor community is welcoming to all, please review and abide by our [Code of Conduct](CODE_OF_CONDUCT.md).

## Bug Reports

If you discover a bug in Fastor, please open an issue on the GitHub repository. To help us resolve the issue quickly, please include:

- A clear description of the bug.
- Steps to reproduce the bug (ideally a minimal code snippet).
- Your environment details (PHP version, OpenSwoole version, OS).

## Security Vulnerabilities


## Coding Standards

Fastor follows the [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standard and the [PSR-4](https://www.php-fig.org/psr/psr-4/) autoloader standard.

### Core Principles

- **Performance First**: Any changes must be benchmarked. Fastor targets >15k RPS for standard request/response validation cycles.
- **Async Awareness**: Code must be safe for use within OpenSwoole coroutines. Avoid stateful singletons or static variables that aren't coroutine-local or read-only after boot.
- **Type Safety**: Use strict typing and the latest PHP features (PHP 8.2+).
- **Extensibility**: When adding validation logic, implement the `Constraint` interface to allow for pre-compiled execution.

## Adding Custom Validators

To add a new built-in validator:
1. Create a new class in `Fastor\Validation\Attributes`.
2. Implement the `Fastor\Validation\Constraint` interface.
3. Ensure the attribute is targetable at `TARGET_PROPERTY`.
4. Add a unit test in `tests/Unit/ValidationTest.php`.

## Pull Request Process

1.  **Fork the Repository**: Create a fork of the Fastor repository.
2.  **Create a Branch**: Create a feature branch for your changes (e.g., `feature/awesome-new-feature` or `fix/issue-description`).
3.  **Run Tests**: Ensure all existing tests pass before submitting your PR.
    ```bash
    php tests/run.php
    ```
4.  **Add Tests**: If you are adding a new feature or fixing a bug, please include appropriate tests.
5.  **Submit PR**: Submit your pull request to the `main` branch. Provide a clear description of the changes.

## License

By contributing to Fastor, you agree that your contributions will be licensed under its MIT License.

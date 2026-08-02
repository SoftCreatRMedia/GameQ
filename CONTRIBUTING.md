# Contributing

Contributions are **welcome** and will be fully **credited**.

The [development wiki](https://github.com/SoftCreatRMedia/GameQ/wiki/Architecture) explains the architecture, [protocol workflow](https://github.com/SoftCreatRMedia/GameQ/wiki/Adding-a-Protocol), and [fixture-based tests](https://github.com/SoftCreatRMedia/GameQ/wiki/Testing).

## Pull Requests

- **Document behavior changes** - Keep `README.md`, `CHANGELOG.md`, and other relevant documentation up to date.
- **Create feature branches** - Don't ask us to pull from your master branch.
- **One pull request per feature** - If you want to do more than one thing, send multiple pull requests.
- **Preserve compatibility** - Prefer a documented deprecation and replacement API over removing or changing public and protected extension points.

## Coding Standard

- Use the [PSR-12 coding style](https://www.php-fig.org/psr/psr-12/).
- Keep PHPStan clean at its maximum level without baselines or inline suppressions.
- Use the following command to run the complete local check suite:

```sh
composer test
```

Run `composer bc-check` before changing public or protected APIs.

## Tests

- **Add tests!** - Your patch won't be accepted if it doesn't have tests.
- Run only the PHPUnit suite with:

```sh
composer phpunit
```

The code quality is validated by [GitHub Actions](.github).

# Can't Contribute?

If you do not feel comfortable writing a change, open a [new issue](https://github.com/SoftCreatRMedia/GameQ/issues/new) describing the game, feature, or bug.

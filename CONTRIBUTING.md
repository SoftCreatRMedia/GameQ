# Contributing

Contributions are **welcome** and will be fully **credited**.

## Pull Requests

- **Document any change in behavior** - Make sure the `README.md` and any other relevant documentation are kept up-to-date.
- **Create feature branches** - Don't ask us to pull from your master branch.
- **One pull request per feature** - If you want to do more than one thing, send multiple pull requests.

## Coding Standard

- The **[PSR-2 Coding Standard](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md)** is to be used for all code. 
- **[PHPStan](https://phpstan.org/)** is used to help keep the code clean. At the moment, PHPStan level 5 is enforced.
- Use the following commands to check your code before committing it:

```sh
$ composer phpcs
$ composer phpstan
```

## Tests

- **Add tests!** - Your patch won't be accepted if it doesn't have tests.
- Use the following command to run the tests:

```sh
$ composer phpunit
```

The code quality is validated by [GitHub Actions](.github).

# Can't Contribute?

If you do not feel comfortable writing your own changes, feel free open up a [new issue](https://github.com/SoftCreatRMedia/GameQ/issues/new) for to add a game or feature.

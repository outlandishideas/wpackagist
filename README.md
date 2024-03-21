WordPress Packagist
===

This is the repository for [wpackagist.org](https://wpackagist.org) which allows WordPress plugins and themes to be
managed along with other dependencies using [Composer](https://getcomposer.org).

More info and usage instructions at [wpackagist.org](https://wpackagist.org) or follow us on
Twitter [@wpackagist](https://twitter.com/wpackagist).

For support and discussion, please use the issue tracker above.

## Usage

Example composer.json:

```json
{
    "name": "acme/brilliant-wordpress-site",
    "description": "My brilliant WordPress site",
    "repositories":[
        {
            "type":"composer",
            "url":"https://wpackagist.org",
            "only": ["wpackagist-plugin/*", "wpackagist-theme/*"]
        }
    ],
    "require": {
        "aws/aws-sdk-php":"*",
        "wpackagist-plugin/akismet":"dev-trunk",
        "wpackagist-plugin/wordpress-seo":">=7.0.2",
        "wpackagist-theme/hueman":"*"
    },
    "autoload": {
        "psr-0": {
            "Acme": "src/"
        }
    }
}
```

## WordPress core

This does not provide WordPress itself.

See https://github.com/fancyguy/webroot-installer or https://github.com/roots/wordpress.

## How it works

WPackagist implements the `wordpress-plugin` and `wordpress-theme` Composer Installers
(https://github.com/composer/installers).

It essentially provides a lookup table from package (theme or plugin) name to WordPress.org
SVN repository. Versions correspond to different tags in their repository, with the special
`dev-trunk` version being mapped to `trunk`.

Note that to maintain Composer v1 compatibility (as well as v2)
for `dev-` versions, for now we need to use the `VersionParser` from
`composer/composer` v1.x and not a newer release branch. Correct resolution
of these depends on the legacy behaviour where `dev-trunk` et al. correspond to

    "version_normalized":"9999999-dev"

The lookup table is provided as a hierarchy of static JSON files. The entry point to these
files can be found at https://wpackagist.org/packages.json, which consists of a series of
sub-tables (each as its own JSON file). These sub-tables are grouped by last commit
date (trying to keep them roughly the same size), and contain references to individual packages.
Each package has its own JSON file detailing its versions; these can be found in
https://wpackagist.org/p/wpackagist-{theme|plugin}/{package-name-and-hash}.json.

### Version format limitations

Currently, Wpackagist can only process packages with up to 4 parts in their version numbers, in line with
the internal handling of Composer v1.x.

## Running Wpackagist

### Installing

1. Make sure you have Composer dependencies installed, including extensions.
2. Make `.env.local`, overriding anything you want to from `.env`.
3. (Only if you're going to skip using a database for `PackageStore`): ensure sure your `PACKAGE_PATH` directory is writable.
4. Run `composer install` to install dependencies.
5. Populate the database and package files (see steps below).
5. Point your Web server to [`web`](web/). A [`.htaccess`](web/.htaccess) is provided for Apache.

### Updating the database

The first database population may easily take hours. Be patient.

1. `bin/console doctrine:migrations:migrate`: Ensure the database schema is up to date with the code.
2. `bin/console refresh`: Query the WordPress.org SVN in order to find new and updated packages.
3. `bin/console update`: Update the version information for packages identified in `(2)`. Uses the WordPress.org API.
4. `bin/console build`: Rebuild all `PackageStore` data.

Each of these can be run with the `-vvv` verbosity flag, to give useful progress updates

## Running locally with Docker

This may be simpler than setting up native dependencies, but is
experimental.

To prepare environment variables:

    cp .env .env.local

and edit as necessary.

To set up and update the database:

    docker-compose run --rm cron composer install
    docker-compose run --rm cron deploy/migrate-db.sh
    docker-compose run --rm cron

To start a web server on `localhost:30100`:

    docker-compose up web adminer

#### Services

* Web: [localhost:30100](http://localhost:30100)
* Adminer: [localhost:30101](http://localhost:30101) (See credentials in `.env.postgres.local`)

## Live deployments

CircleCI is used to deploy the live app on ECS.

Automatic deploys run:

* from `develop` to [Staging](https://staging-wpackagist.out.re);
* from `main` to [Production](https://wpackagist.org/)

See [.circleci/config.yml](./.circleci/config.yml) for full configuration.

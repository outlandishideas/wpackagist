WordPress Packagist
===

This is the repository for [wpackagist.org](http://wpackagist.org) which allows WordPress plugins and themes to be
managed along with other dependencies using [Composer](http://getcomposer.org).

Example composer.json:

```json
{
    "name": "acme/brilliant-wordpress-site",
    "description": "My brilliant WordPress site",
    "repositories":[
        {
            "type":"composer",
            "url":"http://wpackagist.org"
        }
    ],
    "require": {
        "aws/aws-sdk-php":"*",
        "wpackagist-plugin/akismet":"dev-trunk",
        "wpackagist-plugin/captcha":">=3.9",
        "wpackagist-theme/hueman":"*"
    },
    "autoload": {
        "psr-0": {
            "Acme": "src/"
        }
    }
}
```

More info and usage instructions at [wpackagist.org](http://wpackagist.org) or follow us on
Twitter [@wpackagist](https://twitter.com/wpackagist).

For support and discussion, please use the issue tracker above.

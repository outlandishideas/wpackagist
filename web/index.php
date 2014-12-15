<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>WordPress Packagist: Manage your plugins and themes with Composer</title>
    <link href='http://fonts.googleapis.com/css?family=Noto+Sans' rel='stylesheet' type='text/css'>
    <link rel="stylesheet" href="foundation.css"/>
    <link rel="stylesheet" href="style.css"/>
    <link rel="shortcut icon" href="favicon.png"/>
    <script>
        (function (i, s, o, g, r, a, m) {
            i['GoogleAnalyticsObject'] = r;
            i[r] = i[r] || function () {
                (i[r].q = i[r].q || []).push(arguments)
            }, i[r].l = 1 * new Date();
            a = s.createElement(o),
                    m = s.getElementsByTagName(o)[0];
            a.async = 1;
            a.src = g;
            m.parentNode.insertBefore(a, m)
        })(window, document, 'script', '//www.google-analytics.com/analytics.js', 'ga');

        ga('create', 'UA-19510813-5', 'wpackagist.org');
        ga('send', 'pageview');

    </script>
</head>
<body>
    <div class="row">
        <div class="small-12 columns">

            <h1 id="title"><a href="?">WordPress Packagist</a></h1>

<?php
    if (isset($_GET['q'])) {
        include __DIR__ . '/includes/search.php';
    } else {
        include __DIR__ . '/includes/home.php';
    }
?>

            <div class="panel">

                <p class="right">
                    <a href="https://twitter.com/wpackagist" class="twitter-follow-button" data-show-count="false" data-size="large">Follow @wpackagist</a>
                    <script>!function (d, s, id) {
                        var js, fjs = d.getElementsByTagName(s)[0], p = /^http:/.test(d.location) ? 'http' : 'https';
                        if (!d.getElementById(id)) {
                            js = d.createElement(s);
                            js.id = id;
                            js.src = p + '://platform.twitter.com/widgets.js';
                            fjs.parentNode.insertBefore(js, fjs);
                        }
                    }(document, 'script', 'twitter-wjs');</script>
                </p>

                <p>An <a href="http://outlandish.com">Outlandish</a> experiment.</p>
            </div>

        </div>
    </div>
</body>
</html>

<?php

$app = require_once dirname(__DIR__).'/bootstrap.php';

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\DoctrineDbalSingleTableAdapter;

// Uncomment next line to activate the debug
$app['debug'] = true;

///////////////////
// CONFIGURATION //
///////////////////

// Register the form provider
$app->register(new Silex\Provider\FormServiceProvider());

// Register twig templates path
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/templates',
));

// Configure Twig provider
$app['twig'] = $app->share($app->extend('twig', function ($twig, $app) {
    // Custom filter to handle version parsing from the DB.
    $formatVersions = new Twig_SimpleFilter('format_versions', function ($versions) {
        $versions = array_keys((array) json_decode($versions, true));
        usort($versions, 'version_compare');

        return $versions;
    });

    $formatCategory = new Twig_SimpleFilter('format_category', function ($category) {
        return str_replace('Outlandish\Wpackagist\Package\\', '', $category);
    });

    $twig->addFilter($formatVersions);
    $twig->addFilter($formatCategory);

    return $twig;
}));

// Register translation provider because the default Symfony form template require it
$app->register(new Silex\Provider\TranslationServiceProvider());

// Register Pagination provider
$app->register(new FranMoreno\Silex\Provider\PagerfantaServiceProvider());

// Register Url generator provider, required by the pager.
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

// Search Form
$searchForm = $app['form.factory']->createNamedBuilder('', 'form', null, array('csrf_protection' => false))
    ->setAction('search')
    ->setMethod('GET')
    ->add('q', 'search')
    ->add('type', 'choice', array(
        'choices' => array(
            'any'     => 'All packages',
            'plugin'  => 'Plugins',
            'theme'   => 'Themes',
        ),
    ))
    ->add('search', 'submit')
    ->getForm();

////////////
// ROUTES //
////////////

// Home
$app->get('/', function (Request $request) use ($app, $searchForm) {
    return $app['twig']->render('index.twig', array(
       'title'      => "WordPress Packagist: Manage your plugins and themes with Composer",
       'searchForm' => $searchForm->handleRequest($request)->createView(),
    ));
});

// Search
$app->get('/search', function (Request $request) use ($app, $searchForm) {
    /** @var \Doctrine\DBAL\Query\QueryBuilder $queryBuilder */
    $queryBuilder = $app['db']->createQueryBuilder();
    $type         = $request->get('type');
    $active       = $request->get('active_only');
    $query        = trim($request->get('q'));

    $data = array(
        'title'              => "WordPress Packagist: Search packages",
        'searchForm'         => $searchForm->handleRequest($request)->createView(),
        'currentPageResults' => '',
        'error'              => '',
    );

    $queryBuilder
        ->select('*')
        ->from('packages', 'p');

    switch ($type) {
        case 'theme':
            $queryBuilder
                ->andWhere('class_name = :class')
                ->setParameter(':class', 'Outlandish\Wpackagist\Package\Theme');
            break;
        case 'plugin':
            $queryBuilder
                ->andWhere('class_name = :class')
                ->setParameter(':class', 'Outlandish\Wpackagist\Package\Plugin');
            break;
        default:
            break;
    }

    switch ($active) {
        case 1:
            $queryBuilder->andWhere('is_active');
            break;

        default:
            $queryBuilder->orderBy('is_active', 'DESC');
            break;
    }

    if (!empty($query)) {
        $queryBuilder
            ->andWhere('name LIKE :name')
            ->orWhere('display_name LIKE :name')
            ->addOrderBy('name LIKE :order', 'DESC')
            ->addOrderBy('name', 'ASC')
            ->setParameter(':name', "%{$query}%")
            ->setParameter(':order', "{$query}%");
    } else {
        $queryBuilder
            ->addOrderBy('last_committed', 'DESC');
    }

    $countField = 'p.name';
    $adapter    = new DoctrineDbalSingleTableAdapter($queryBuilder, $countField);
    $pagerfanta = new Pagerfanta($adapter);
    $pagerfanta->setMaxPerPage(30);
    $pagerfanta->setCurrentPage($request->query->get('page', 1));

    $data['pager']              = $pagerfanta;
    $data['currentPageResults'] = $pagerfanta->getCurrentPageResults();

    return $app['twig']->render('search.twig', $data);
});

$app->post('/update', function (Request $request) use ($app, $searchForm) {
    // first run the update command
    $name = $request->get('name');
    if (!trim($name)) {
        return new Response('Invalid Request',400);
    }

    $query = $app['db']->prepare('SELECT * FROM packages WHERE name = :name');
    $query->execute([ $name ]);
    $package = $query->fetch(PDO::FETCH_ASSOC);

    if (!$package) {
        return new Response('Not Found',404);
    }
    $safeName = $package['name'];

    $count = getRequestCountByIp($_SERVER['REMOTE_ADDR'], $app['db']);
    if ($count > 10) {
        return new Response('Too many requests. Try again in an hour.', 403);
    }

    $input = new ArrayInput(array(
        'command' => 'update',
        '--name' => $safeName
    ));
    $output = new NullOutput();
    $app['console']->doRun($input, $output);

    // then redirect to the search page
    return new RedirectResponse('/search?q=' . $safeName);
});

// Opensearch path
$app->get('/opensearch.xml', function (Request $request) use ($app) {
    return new Response($app['twig']->render(
            'opensearch.twig',
            array('host' => $request->getHttpHost())
        ),
        200,
        array('Content-Type' => 'application/opensearchdescription+xml')
    );
});

$app->run();

/**
 * @var $db \Doctrine\DBAL\Connection
 *
 * @return int The number of requests within the past 24 hours
 */
function getRequestCountByIp($ip, $db) {

    $prune = $db->prepare(
        "DELETE FROM requests WHERE last_request < DATETIME(CURRENT_TIMESTAMP, '-1 hour')"
    );
    $prune->execute();

    $query = $db->prepare(
        'SELECT * FROM requests WHERE ip_address = :ip'
    );
    $query->execute([ $ip ]);

    $requestHistory = $query->fetch(PDO::FETCH_ASSOC);
    if (!$requestHistory) {
        $insert = $db->prepare(
            'INSERT INTO requests (ip_address, last_request, request_count) VALUES (:ip_address, CURRENT_TIMESTAMP, 1)'
        );
        $insert->execute([ $ip ]);
        return 1;
    }

    $update = $db->prepare(
        'UPDATE requests SET request_count = request_count + 1, last_request = CURRENT_TIMESTAMP WHERE ip_address = :ip'
    );

    $update->execute([ $ip ]);
    return $requestHistory['request_count'] + 1;
}

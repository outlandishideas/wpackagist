<?php

$app = require_once dirname(__DIR__).'/bootstrap.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\FixedAdapter;
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
    $wporgClient  = \Rarst\Guzzle\WporgClient::getClient();
    $currentPage  = $request->query->get('page', 1);
    $type         = $request->get('type');
    $active       = $request->get('active_only');
    $query        = trim($request->get('q'));
    $results      = [];

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
            $wporgQueryResults = $wporgClient->getThemesBy('search', $query, $currentPage, 30, ['slug']);
            $slugs = array_column($wporgQueryResults['themes'], 'slug');
            break;
        case 'plugin':
            $queryBuilder
                ->andWhere('class_name = :class')
                ->setParameter(':class', 'Outlandish\Wpackagist\Package\Plugin');
            $wporgQueryResults = $wporgClient->getPluginsBy('search', $query, $currentPage, 30, ['slug']);
            $slugs = array_column($wporgQueryResults['plugins'], 'slug');
            break;
        default:
            break;
    }

    if (!empty($query)) {
        // We match the results from the wordpress API to our database.
        $queryBuilder
            ->andWhere('p.name IN (:name)')
            ->setParameter(':name', $slugs, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);

        // This gymnastic is needed because the SQLITE database doesn't support ordering by FIELD, so we need to
        // do it in PHP. We order the results from the database to match what the  wordpress API is returning us.
        $statement = $queryBuilder->execute();
        $packageResults = $statement->fetchAll(PDO::FETCH_ASSOC);
        $packageResults = array_column($packageResults, null, 'name');

        foreach ($slugs as $name) {
            $results[] = $packageResults[$name];
        }

        $adapter    = new FixedAdapter($wporgQueryResults['info']['results'], $results);
    } else {
        // If we have no search query, we just paginate based on last committed
        $queryBuilder
            ->addOrderBy('last_committed', 'DESC');
        $adapter    = new DoctrineDbalSingleTableAdapter($queryBuilder, 'p.name');
    }

    $pagerfanta = new Pagerfanta($adapter);
    $pagerfanta->setMaxPerPage(30);
    $pagerfanta->setCurrentPage($currentPage);

    $data['pager']              = $pagerfanta;
    $data['currentPageResults'] = $pagerfanta->getCurrentPageResults();

    return $app['twig']->render('search.twig', $data);
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

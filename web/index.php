<?php

$app = require_once dirname(__DIR__).'/bootstrap.php';

use Silex\Provider\FormServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\DoctrineDbalSingleTableAdapter;

// Enable debug only locally
if (!isset($_SERVER['HTTP_CLIENT_IP'])
    || !isset($_SERVER['HTTP_X_FORWARDED_FOR'])
    || in_array(@$_SERVER['REMOTE_ADDR'], array('127.0.0.1', 'fe80::1', '::1'))
) {
    $app['debug'] = true;
}

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
            'any'     => 'Any',
            'plugin'  => 'Plugin',
            'theme'   => 'Theme',
        ),
    ))
    ->add('active_only', 'choice', array(
        'choices' => array(
            0 => 'All',
            1 => 'Active',
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
    $queryBuilder = $app['db']->createQueryBuilder();
    $type         = $request->get('type');
    $active       = $request->get('active_only');
    $query        = trim($request->get('q'));
    $results      = array();

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
            $queryBuilder->andWhere('is_active', true);
            break;

        default:
            $queryBuilder->orderBy('is_active', 'DESC');
            break;
    }

    if (!empty($query)) {
        $queryBuilder
            ->where('name LIKE :name')
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

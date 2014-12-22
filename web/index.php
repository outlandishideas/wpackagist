<?php

require_once __DIR__.'/../vendor/autoload.php';

use Silex\Provider\FormServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\DoctrineDbalSingleTableAdapter;


$app = new Silex\Application();
// Uncomment next line to activate the debug
// $app['debug'] = true;

///////////////////
// CONFIGURATION //
///////////////////

// Register Doctrine provider
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver'   => 'pdo_sqlite',
        'path'     => __DIR__.'/../data/packages.sqlite',
    ),
));

// Register the form provider
$app->register(new FormServiceProvider());

// Register twig templates path
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/templates',
));

// Configure Twig provider
$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
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
					'any' => 'Any',
					'plugin' => 'Plugin',
					'theme' => 'Theme'
				)
			))
			->add('search', 'submit')
			->getForm();

////////////
// ROUTES //
////////////

// Home
$app->get('/', function (Request $request) use ($app, $searchForm) {
    return $app['twig']->render('index.twig', array(
       'title' => "WordPress Packagist: Manage your plugins and themes with Composer",
       'searchForm' => $searchForm->handleRequest($request)->createView()
    ));
});

// Search
$app->get('/search', function (Request $request) use ($app, $searchForm) {
	$queryBuilder = $app['db']->createQueryBuilder();
	$type = $request->get('type');
	$query = $request->get('q');
	$results = array();
	$data = array(
		'title' => "WordPress Packagist: Search packages",
		'searchForm' => $searchForm->handleRequest($request)->createView(),
		'currentPageResults' => '',
		'error' => ''
	);

	$queryBuilder
		->select('*')
		->from('packages', 'p')
		->where('name LIKE :name');

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

	$queryBuilder
		->orderBy('is_active', 'DESC')
		->addOrderBy('name LIKE :order', 'DESC')
		->addOrderBy('name', 'ASC')
		->setParameter(':name', "%{$query}%")
		->setParameter(':order', "{$query}%");
	
	$countField = 'p.name';
	$adapter = new DoctrineDbalSingleTableAdapter($queryBuilder, $countField);
    $pagerfanta = new Pagerfanta($adapter);
    $pagerfanta->setMaxPerPage(30);
    $pagerfanta->setCurrentPage($request->query->get('page', 1));
    $data['pager'] = $pagerfanta;
	$data['currentPageResults'] = $pagerfanta->getCurrentPageResults();

    return $app['twig']->render('search.twig', $data);
});

$app->run();

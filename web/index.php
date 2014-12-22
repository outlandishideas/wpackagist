<?php

require_once __DIR__.'/../vendor/autoload.php';

use Silex\Provider\FormServiceProvider;
use Symfony\Component\HttpFoundation\Request;

$app = new Silex\Application();
// $app['debug'] = true;

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
	    $versions = array_keys(json_decode($versions, true));
        usort($versions, 'version_compare');
        return implode(', ', $versions);
	});

	$formatCategory = new Twig_SimpleFilter('format_category', function ($category) {
		return str_replace('Outlandish\Wpackagist\Package\\', '', $category);
	});

    $twig->addFilter($formatVersions);
	$twig->addFilter($formatCategory);

    return $twig;
}));

// Register translation provider because the default Symfony require it
$app->register(new Silex\Provider\TranslationServiceProvider());

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


// Routes
$app->get('/', function (Request $request) use ($app, $searchForm) {
    return $app['twig']->render('index.twig', array(
       'title' => "WordPress Packagist: Manage your plugins and themes with Composer",
       'searchForm' => $searchForm->handleRequest($request)->createView()
    ));
});

$app->get('/search', function (Request $request) use ($app, $searchForm) {
	$dbp = new \Outlandish\Wpackagist\DatabaseProvider();
	$db = $dbp->getDb();
	$type = $request->get('type');
	$query = $request->get('q');
	$data = array(
		'title' => "WordPress Packagist: Search packages",
		'searchForm' => $searchForm->handleRequest($request)->createView(),
		'results' => ''
	);


	$sql = "SELECT * FROM packages WHERE name LIKE :name";
	$params = array(':name' => "%{$query}%", ':order' => "{$query}%");

	switch ($type) {
		case 'theme':
			$params['class'] = 'Outlandish\Wpackagist\Package\Theme';
			$sql .= " AND class_name = :class";
			break;
		case 'plugin':
			$params['class'] = 'Outlandish\Wpackagist\Package\Plugin';
			$sql .= " AND class_name = :class";
			break;
		default:
			# code...
			break;
	}

	$sql .= ' ORDER BY is_active DESC, name LIKE :order DESC, name ASC LIMIT 50';
	$query = $db->prepare($sql);

	if (!$query->execute($params)) {
	    $data['error'] = 'Database error.';
	}

	if ($row = $query->fetch()) {
	    $data['results'] =  $query->fetchAll(PDO::FETCH_ASSOC);
	}

    return $app['twig']->render('search.twig', $data);
});

$app->run();

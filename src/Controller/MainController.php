<?php

namespace Outlandish\Wpackagist\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Outlandish\Wpackagist\Entity\Package;
use Outlandish\Wpackagist\Entity\Plugin;
use Outlandish\Wpackagist\Entity\Theme;
use Outlandish\Wpackagist\Service;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Outlandish\Wpackagist\Storage;

class MainController extends AbstractController
{
    /** @var FormFactoryInterface */
    private $formFactory;
    /** @var FormInterface|null */
    private $form;
    /** @var Storage\Provider */
    private $storage;

    public function __construct(FormFactoryInterface $formFactory, Storage\Provider $storage)
    {
        $this->formFactory = $formFactory;
        $this->storage = $storage;
    }

    /**
     * @Route("packages.json", name="json_index")
     */
    public function packageIndexJson(): Response
    {
        $response = new Response($this->storage->load('packages.json'));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Requests with a directory get an individual plugin or theme's Composer data.
     * Those without are for the more specific package list metadata files referenced
     * in the top level `packages.json` in its `provider-includes`.
     *
     * @Route("p/{file}.json", name="json_provider")
     * @Route("p/{dir}/{file}.json", name="json_package")
     * @param ?string $dir   Directory: wpackagist-plugin or wpackagist-theme.
     * @param string $file  Filename excluding '.json'.
     * @return Response
     */
    public function packageJson(string $file, ?string $dir = null): Response
    {
        $dir = str_replace('.', '', $dir);
        $file = str_replace('.', '', $file);

        if (!empty($dir) && !in_array($dir, ['wpackagist-plugin', 'wpackagist-theme'], true)) {
            throw new BadRequestException('Unexpected package path');
        }

        $key = empty($dir) ? "p/$file.json" : "p/$dir/{$file}.json";

        $data = $this->storage->load($key);

        if (empty($data)) {
            throw new NotFoundHttpException();
        }

        $response = new Response($data);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    public function home(Request $request): Response
    {
        return $this->render('index.twig', [
            'title'      => 'WordPress Packagist: Manage your plugins and themes with Composer',
            'searchForm' => $this->getForm()->handleRequest($request)->createView(),
        ]);
    }

    public function search(Request $request, Connection $connection, EntityManagerInterface $entityManager): Response
    {
        $queryBuilder = new QueryBuilder($entityManager);

        $form = $this->getForm();
        $form->handleRequest($request);

        $data = $form->getData();
        $type = $data['type'] ?? null;
        $active = $data['active_only'] ?? false;
        $query = empty($data['q']) ? null : trim($data['q']);

        $data = [
            'title'              => "WordPress Packagist: Search packages",
            'searchForm'         => $form->createView(),
            'currentPageResults' => '',
            'error'              => '',
        ];

        // TODO move search query logic to PackageRepository.
        $queryBuilder
            ->select('p');

        switch ($type) {
            case 'theme':
                $queryBuilder->from(Theme::class, 'p');
                break;
            case 'plugin':
                $queryBuilder->from(Plugin::class, 'p');
                break;
            default:
                $queryBuilder->from(Package::class, 'p');
        }

        switch ($active) {
            case 1:
                $queryBuilder->andWhere('p.isActive = true');
                break;

            default:
                $queryBuilder->addOrderBy('p.isActive', 'DESC');
                break;
        }

        if (!empty($query)) {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->orX(
                    $queryBuilder->expr()->like('p.name', ':name'),
                    $queryBuilder->expr()->like('p.displayName', ':name')
                ))
                ->addOrderBy('p.name', 'ASC')
                ->setParameter('name', "%{$query}%");
        } else {
            $queryBuilder
                ->addOrderBy('p.lastCommitted', 'DESC');
        }

        $countField = 'p.name';
        $adapter    = new DoctrineORMAdapter($queryBuilder);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage(30);
        $pagerfanta->setCurrentPage($request->query->get('page', 1));

        $data['pager']              = $pagerfanta;
        $data['currentPageResults'] = $pagerfanta->getCurrentPageResults();

        return $this->render('search.twig', $data);
    }

    public function update(Request $request, Connection $connection, EntityManagerInterface $entityManager, LoggerInterface $logger, Service\Update $updateService, Storage\Provider $storage): Response
    {
        $storage->prepare();

        // first run the update command
        $name = $request->get('name');
        if (!trim($name)) {
            return new Response('Invalid Request',400);
        }

        $packages = $entityManager->getRepository(Package::class)->findBy(['name' => $name]);
        if (count($packages) === 0) {
            return new Response('Not Found',404);
        }

        $package = $packages[0];
        $safeName = $package->getName();

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR']) {
            $splitIp = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $userIp  = trim($splitIp[0]);
        } else {
            $userIp = $_SERVER['REMOTE_ADDR'];
        }

        $count = $this->getRequestCountByIp($userIp, $connection);
        if ($count > 5) {
            return new Response('Too many requests. Try again in an hour.', 403);
        }

        $updateService->update($logger, $safeName);

        $storage->finalise();

        return new RedirectResponse('/search?q=' . $safeName);
    }

    private function getForm(): FormInterface
    {
        if (!isset($this->form)) {
            $this->form = $this->formFactory
                // A named builder with blank name enables not having a param prefix like `formName[fieldName]`.
                ->createNamedBuilder('', FormType::class, null, ['csrf_protection' => false])
                ->setAction('search')
                ->setMethod('GET')
                ->add('q', SearchType::class)
                ->add('type', ChoiceType::class, [
                    'choices' => [
                        'All packages' => 'any',
                        'Plugins' => 'plugin',
                        'Themes' => 'theme',
                    ],
                ])
                ->add('search', SubmitType::class)
                ->getForm();
        }

        return $this->form;
    }

    /**
     * @param string $ip
     * @param Connection $db
     * @return int The number of requests within the past 24 hours
     * @throws \Doctrine\DBAL\DBALException
     * @todo move to a Repository helper?
     */
    private function getRequestCountByIp(string $ip, Connection $db): int
    {
        $query = $db->prepare(
            "SELECT * FROM requests WHERE ip_address = :ip AND last_request > :cutoff"
        );
        $query->execute([
            'cutoff' => (new \DateTime())->sub(new \DateInterval('PT1H'))->format($db->getDatabasePlatform()->getDateTimeFormatString()),
            'ip' => $ip,
        ]);

        $requestHistory = $query->fetch(PDO::FETCH_ASSOC);
        if (!$requestHistory) {
            $this->resetRequestCount($ip, $db);
            return 1;
        }

        $update = $db->prepare(
            'UPDATE requests SET request_count = request_count + 1, last_request = CURRENT_TIMESTAMP WHERE ip_address = :ip'
        );

        $update->execute([ $ip ]);
        return $requestHistory['request_count'] + 1;
    }

    /**
     * Add an entry to the requests table for the provided IP address.
     * Has the side effect of removing all expired entries.
     *
     * @param string $ip
     * @param Connection $db
     * @throws \Doctrine\DBAL\DBALException
     * @todo move to a Repository helper?
     */
    private function resetRequestCount(string $ip, Connection $db)
    {
        $prune = $db->prepare('DELETE FROM requests WHERE last_request < :cutoff');
        $prune->bindValue('cutoff', (new \DateTime())->sub(new \DateInterval('PT1H'))->format($db->getDatabasePlatform()->getDateTimeFormatString()));
        $prune->execute();
        $insert = $db->prepare(
            'INSERT INTO requests (ip_address, last_request, request_count) VALUES (:ip_address, CURRENT_TIMESTAMP, 1)'
        );
        $insert->execute([ $ip ]);
    }
}

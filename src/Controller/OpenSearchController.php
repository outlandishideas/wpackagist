<?php

namespace Outlandish\Wpackagist\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OpenSearchController extends AbstractController
{
    public function go(Request $request): Response
    {
        $response = $this->render('opensearch.twig', ['host' => $request->getHttpHost()]);
        $response->headers->add(['Content-Type' => 'application/opensearchdescription+xml']);

        return $response;
    }
}

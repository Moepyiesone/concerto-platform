<?php

namespace Concerto\PanelBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Concerto\PanelBundle\Service\PanelService;
use Concerto\PanelBundle\Service\FileService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PanelController {

    private $templating;
    private $service;
    private $fileService;

    public function __construct(EngineInterface $templating, PanelService $service, FileService $fileService) {
        $this->templating = $templating;
        $this->service = $service;
        $this->fileService = $fileService;
    }

    /**
     * @return Response
     */
    public function indexAction() {
        return $this->templating->renderResponse('ConcertoPanelBundle:Panel:index.html.twig');
    }

    /**
     * @return Response
     */
    public function breadcrumbsAction() {
        return $this->templating->renderResponse('ConcertoPanelBundle::breadcrumbs.html.twig');
    }

    /**
     * @return Response
     */
    public function loginAction(Request $request) {
        return $this->templating->renderResponse('ConcertoPanelBundle:Panel:login.html.twig', array(
                    'last_username' => $request->getSession()->get(Security::LAST_USERNAME),
                    'error' => $this->service->getLoginErrors($request->get(Security::AUTHENTICATION_ERROR), $request->getSession())));
    }

    /**
     * @return Response
     */
    public function changeLocaleAction(Request $request, $locale) {
        $request->setLocale($locale);
        $request->setDefaultLocale($locale);
        $this->service->setLocale($request->getSession(), $locale);

        return new RedirectResponse($request->getUriForPath("/admin"));
    }
}

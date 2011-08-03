<?php

namespace Knp\PaginatorBundle\Templating\Helper;

use Symfony\Bundle\FrameworkBundle\Templating\Helper\RouterHelper;
use Symfony\Component\Templating\Helper\Helper;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Knp\PaginatorBundle\Paginator\Paginator;
use Knp\PaginatorBundle\Paginator\Doctrine as Adapter;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Pagination view helper
 * Responsible for rendering pagination control
 * also sorting the inner fields
 */
class PaginationHelper extends Helper
{
    /**
     * Router helper for url generation
     *
     * @var RouterHelper
     */
    protected $routerHelper;

    /**
     * Template rendering engine
     * used for pagination control
     * rendering
     *
     * @var DelegatingEngine
     */
    protected $engine;

    /**
     * Translator
     *
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * Currently matched route
     *
     * @var string
     */
    private $route;

    /**
     * Request query parameters
     *
     * @var array
     */
    private $params;

    /**
     * Pagination control template
     *
     * @var string
     */
    private $template = 'KnpPaginatorBundle:Pagination:sliding.html.twig';

    /**
     * Initialize pagination helper
     *
     * @param EngineInterface $engine
     * @param RouterHelper $routerHelper
     * @param TranslatorInterface $translator
     */
    public function __construct(EngineInterface $engine, RouterHelper $routerHelper, TranslatorInterface $translator)
    {
        $this->engine = $engine;
        $this->routerHelper = $routerHelper;
        $this->translator = $translator;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST === $event->getRequestType()) {
            $request = $event->getRequest();

            $this->route = $request->attributes->get('_route');
            $this->params = array_merge($request->query->all(), $request->attributes->all());
            foreach ($this->params as $key => $param) {
                if (substr($key, 0, 1) == '_') {
                    unset($this->params[$key]);
                }
            }
        }
    }

    /**
     * Renders a pagination control, for a $paginator given.
     * Optionaly $template and $style can be specified to
     * override default from configuration.
     *
     * @param Zend\Paginator\Paginator $paginator
     * @param string $template
     * @param array $custom - custom parameters
     * @param array $routeparams - params for the route
     * @param string $route
     * @return string
     */
    public function paginate(Paginator $paginator, $template = null, $custom = array(), $routeparams = array(), $route = null)
    {
        if ($template) {
            $this->template = $template;
        }
        if (null === $route) {
            $route = $this->route;
        }

        $params = get_object_vars($paginator->getPages(new \Knp\PaginatorBundle\Paginator\ScrollingStyle\Sliding()));
        $params['route'] = $route;
        $params['alias'] = $this->getAlias($paginator);
        $params['query'] = array_merge($this->params, $routeparams);
        $params['custom'] = $custom;

        return $this->engine->render($this->template, $params);
    }

    /**
     * Get the alias of $paginator
     *
     * @param Zend\Paginator\Paginator $paginator
     * @return string
     */
    private function getAlias(Paginator $paginator)
    {
        $alias = '';
        $adapter = $paginator->getAdapter();
        if ($adapter instanceof Adapter) {
            $alias = $adapter->getAlias();
        }

        return $alias;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'pagination';
    }
}

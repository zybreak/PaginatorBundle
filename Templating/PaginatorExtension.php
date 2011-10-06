<?php

namespace Zybreak\PaginatorBundle\Templating;

use Symfony\Bundle\FrameworkBundle\Templating\Helper\RouterHelper;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Zybreak\PaginatorBundle\Paginator\Paginator;
use Zybreak\PaginatorBundle\Paginator\Doctrine as Adapter;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class PaginatorExtension extends \Twig_Extension
{

    /**
     * Router helper for url generation
     *
     * @var RouterHelper
     */
    private $routerHelper;

    /**
     * Template rendering engine
     * used for pagination control
     * rendering
     *
     * @var DelegatingEngine
     */
    private $engine;

    /**
     * Translator
     *
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * Request query parameters
     *
     * @var array
     */
    private $params;

    /**
     * Currently matched route
     *
     * @var string
     */
    private $route;

    /**
     * Initialize pagination extention
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

    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return array
     */
    public function getFilters()
    {
        return array(
            'sortable' => new \Twig_Filter_Method($this, 'sortable', array('is_safe' => array('html'))),
            'paginate' => new \Twig_Filter_Method($this, 'paginate', array('is_safe' => array('html')))
        );
    }

    /**
     * Create a sort url for the field named $title
     * and identified by $key which consists of
     * alias and field. $options holds all link
     * parameters like "alt, class" and so on.
     *
     * $key example: "article.title"
     *
     * @param Paginator $paginator
     * @param string $title
     * @param string $key
     * @param array $options
     * @param array $params
     * @param string $route
     * @return string
     */
    public function sortable(Paginator $paginator, $title, $key, $options = array(), $params = array(), $route = null)
    {
        $alias = $this->getAlias($paginator);
        $options = array_merge(array(
            'absolute' => false
        ), $options);

        if (null === $route) {
            $route = $this->route;
        }
        $params = array_merge($this->params, $params);
        $direction = isset($options[$alias.'direction']) ? $options[$alias.'direction'] : 'asc';

        $sorted = isset($params[$alias.'sort']) && $params[$alias.'sort'] == $key;
        if ($sorted) {
            $direction = $params[$alias.'direction'];
            $direction = (strtolower($direction) == 'asc') ? 'desc' : 'asc';
            $class = $direction == 'asc' ? 'desc' : 'asc';
            if (isset($options['class'])) {
                $options['class'] .= ' ' . $class;
            } else {
                $options['class'] = $class;
            }
        } else {
            $options['class'] = 'sortable';
        }
        if (is_array($title) && array_key_exists($direction, $title)) {
            $title = $title[$direction];
        }
        $params = array_merge(
            $params,
            array($alias.'sort' => $key, $alias.'direction' => $direction)
        );
        return $this->buildLink($params, $route, $this->translator->trans($title), $options);
    }

    /**
     * Renders a pagination control, for a $paginator given.
     *
     * @param Paginator $paginator
     * @return string
     */
    public function paginate(Paginator $paginator)
    {
        $route = $this->route;

        $params = get_object_vars($paginator->getPages(new \Zybreak\PaginatorBundle\Paginator\ScrollingStyle\Sliding()));
        $params['route'] = $route;
        $params['alias'] = $this->getAlias($paginator);
        $params['query'] = $this->params;

        return $this->engine->render('ZybreakPaginatorBundle:Pagination:sliding.html.twig', $params);
    }

    public function getName()
    {
        return 'paginator';
    }

    /**
     * Build a HTML link. $options holds all link parameters
     * like "alt, class" and so on. $title can also be an image
     * if required.
     *
     * @param array $paramsß url query params
     * @param string $route
     * @param string $title
     * @param array $options
     * @return string
     */
    private function buildLink($params, $route, $title, $options = array())
    {
        $options['href'] = $this->routerHelper->generate($route, $params, $options['absolute']);
        unset($options['absolute']);

        if (!isset($options['title'])) {
            $options['title'] = $title;
        }

        return $this->engine->render('ZybreakPaginatorBundle:Pagination:sortable_link.html.twig', array('options' => $options, 'title' => $title));
    }

    /**
     * Get the alias of $paginator
     *
     * @param Paginator $paginator
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
}

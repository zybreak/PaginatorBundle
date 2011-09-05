# Intro to PaginatorBundle

This is a new version of Paginator Bundle which has been made reusable, extensible,
highly customizable and simple to use Symfony2 paginating tool
based on Zend Paginator.

## Requirements:

- Doctrine ORM active bundle.

## Features:

- View helper for simplified pagination templates.
- Supports multiple paginators during one request

**Notice:** using multiple paginators requires setting the alias for adapter in order to keep non
conflicting parameters. Also it gets quite complicated with a twig template, since hash arrays cannot use
variables as keys.

## Installation and configuration:

### Add the namespaces to your autoloader

    // app/autoload.php
    $loader->registerNamespaces(array(
        'Zybreak'                       => __DIR__.'/../vendor/bundles',
        // ...
    ));


### Add PaginatorBundle to your application kernel

    // app/AppKernel.php
    public function registerBundles()
    {
        return array(
            // ...
            new Zybreak\PaginatorBundle\ZybreakPaginatorBundle(),
            // ...
        );
    }

## Usage examples:

### Controller

    $em = $this->get('doctrine.orm.entity_manager');
    $dql = "SELECT a FROM VendorBlogBundle:Article a";
    $query = $em->createQuery($dql);

    $adapter = $this->get('zybreak_paginator.adapter');
    $adapter->setQuery($query);
    $adapter->setDistinct(true);

    $paginator = new \Zybreak\PaginationBundle\Paginator\Paginator($adapter);
    $paginator->setCurrentPageNumber($this->get('request')->query->get('page', 1));
    $paginator->setItemCountPerPage(10);
    $paginator->setPageRange(5);

### View

    <table>
    {% for article in paginator %}
    <tr {% if loop.index is odd %}class="color"{% endif %}>
        <td>{{ article.id }}</td>
        <td>{{ article.title }}</td>
    </tr>
    {% endfor %}
    </table>
    {# display navigation #}
    <div id="navigation">
        {{ paginator|paginate }}
    </div>


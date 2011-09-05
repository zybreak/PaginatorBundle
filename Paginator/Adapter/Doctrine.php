<?php

namespace Zybreak\PaginatorBundle\Paginator\Adapter;

use Symfony\Component\DependencyInjection\ContainerInterface,
    Zybreak\PaginatorBundle\Query\Helper as QueryHelper,
    Zybreak\PaginatorBundle\Query\TreeWalker\Paginate\CountWalker,
    Zybreak\PaginatorBundle\Query\TreeWalker\Paginate\WhereInWalker,
    Zybreak\PaginatorBundle\Paginator\Adapter,
    Doctrine\ORM\Query;

/**
 * Doctrine Paginator Adapter.
 * Customized for the event based extendability.
 */
class Doctrine implements Adapter
{
    
    /**
     * AST Tree Walker for count operation
     */
    const TREE_WALKER_COUNT = 'Zybreak\PaginatorBundle\Query\TreeWalker\Paginate\CountWalker';

    /**
     * AST Tree Walker for primary key retrieval in case of distinct mode
     */
    const TREE_WALKER_LIMIT_SUBQUERY = 'Zybreak\PaginatorBundle\Query\TreeWalker\Paginate\LimitSubqueryWalker';

    /**
     * AST Tree Walker for loading the resultset by primary keys in case of distinct mode
     */
    const TREE_WALKER_WHERE_IN = 'Zybreak\PaginatorBundle\Query\TreeWalker\Paginate\WhereInWalker';

    /**
     * Query object for pagination query
     *
     * @var object - ORM or ODM query object
     */
    protected $query = null;

    /**
     * True to paginate in distinct mode
     *
     * @var boolean
     */
    protected $distinct = false;

    /**
     * Total item count
     *
     * @var integer
     */
    protected $rowCount = null;

    /**
     * Container used for tagged event loading.
     * Strictly private usage.
     *
     * @var Symfony\Component\DependencyInjection\ContainerInterface
     */
    private $container = null;

    /**
     * Used alias for the paginator to support
     * multiple paginators in one request
     *
     * @var string
     */
    private $alias = '';

    /**
     * Initialize the doctrine paginator adapter
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Set the distinct mode
     *
     * @param bool $distinct
     * @return Zybreak\PaginatorBundle\Paginator\Adapter\Doctrine
     */
    public function setDistinct($distinct)
    {
        $this->distinct = (bool)$distinct;

        return $this;
    }

    /**
     * Set the alias for this paginator, all
     * request parameters will be aliased by it
     *
     * @param string $alias
     * @return Zybreak\PaginatorBundle\Paginator\Adapter\Doctrine
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * Set the query object for the adapter
     * to be paginated.
     *
     * @param Query $query - The query to paginate
     * @param integer $numRows(optional) - number of rows
     * @throws InvalidArgumentException - if query type is not supported
     * @return Zybreak\PaginatorBundle\Paginator\Adapter\Doctrine
     */
    public function setQuery($query, $numRows = null)
    {
        $this->query = $query;
        $this->rowCount = is_null($numRows) ? null : intval($numRows);

        return $this;
    }
    
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Executes count on supplied query
     *
     * @throws UnexpectedValueException - if event is not finally processed or query not set
     * @return integer
     */
    public function count()
    {
        if (is_null($this->rowCount)) {
            if ($this->query === null) {
                throw new \UnexpectedValueException('Paginator Query must be supplied at this point');
            }

            $countQuery = QueryHelper::cloneQuery($this->query);
            $countQuery->setParameters($this->query->getParameters());
            QueryHelper::addCustomTreeWalker($countQuery, self::TREE_WALKER_COUNT);
            $countQuery->setHint(
                CountWalker::HINT_PAGINATOR_COUNT_DISTINCT,
                $this->distinct
            );
            $countQuery->setFirstResult(null)
                ->setMaxResults(null);
            $countResult = $countQuery->getResult(Query::HYDRATE_ARRAY);
            $this->rowCount = count($countResult) > 1 ? count($countResult) : current(current($countResult));
        }

        return $this->rowCount;
    }

    /**
     * Executes the pagination query
     *
     * @param integer $offset
     * @param integer $itemCountPerPage
     * @throws UnexpectedValueException - if event is not finally processed or query not set
     * @return mixed - resultset
     */
    public function getItems($offset, $itemCountPerPage)
    {
        if ($this->query === null) {
            throw new \UnexpectedValueException('Paginator Query must be supplied at this point');
        }

        $query = $this->query;
        $distinct = $this->distinct;
        $result = null;
        if ($distinct) {
            $limitSubQuery = QueryHelper::cloneQuery($query);
            $limitSubQuery->setParameters($query->getParameters());
            QueryHelper::addCustomTreeWalker($limitSubQuery, self::TREE_WALKER_LIMIT_SUBQUERY);

            $limitSubQuery->setFirstResult($offset)
                ->setMaxResults($itemCountPerPage);
            $ids = array_map('current', $limitSubQuery->getScalarResult());
            // create where-in query
            
            die(print_r($ids, true));
            $whereInQuery = QueryHelper::cloneQuery($query);
            QueryHelper::addCustomTreeWalker($whereInQuery, self::TREE_WALKER_WHERE_IN);
            $whereInQuery->setHint(WhereInWalker::HINT_PAGINATOR_ID_COUNT, count($ids))
                ->setFirstResult(null)
                ->setMaxResults(null);

            foreach ($ids as $i => $id) {
                $whereInQuery->setParameter(WhereInWalker::PAGINATOR_ID_ALIAS . '_' . ++$i, $id);
            }
            $result = $whereInQuery->getResult();
        } else {
            $query->setFirstResult($offset)
                ->setMaxResults($itemCountPerPage);
            $result = $query->getResult();
        }
        return $result;
    }
    
    public function setWhereIn($ids)
    {
        if ($this->query === null) {
            throw new \UnexpectedValueException('Paginator Query must be supplied at this point');
        }
        
        $whereInQuery = QueryHelper::cloneQuery($this->query);
        QueryHelper::addCustomTreeWalker($whereInQuery, self::TREE_WALKER_WHERE_IN);
        $whereInQuery->setHint(WhereInWalker::HINT_PAGINATOR_ID_COUNT, count($ids));

        foreach ($ids as $i => $id) {
            $whereInQuery->setParameter(WhereInWalker::PAGINATOR_ID_ALIAS . '_' . ++$i, $id);
        }

        $this->query = $whereInQuery;
    }

    /**
     * Clone the adapter. Resets rowcount and query
     */
    public function __clone()
    {
        $this->rowCount = null;
        $this->query = null;
    }
}

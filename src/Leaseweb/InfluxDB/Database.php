<?php
/**
 * @author Stephen "TheCodeAssassin" Hoogendijk
 */

namespace Leaseweb\InfluxDB;

use Leaseweb\InfluxDB\Database\RetentionPolicy;
use Leaseweb\InfluxDB\Query\Builder as QueryBuilder;
use Leaseweb\InfluxDB\Database\Exception as DatabaseException;

/**
 * Class Database
 *
 * @todo admin functionality
 *
 * @package Leaseweb\InfluxDB
 */
class Database
{

    /**
     * The name of the Database
     *
     * @var string
     */
    protected $name = '';

    /**
     * @var Client
     */
    protected $client;

    /**
     * Construct a database object
     *
     * @param string $name
     * @param Client $client
     *
     * @throws DatabaseException
     */
    public function __construct($name, Client $client)
    {
        $this->client = $client;

        if (!$name) {
            throw new DatabaseException('No database name provided');
        }

        $this->name = $name;

    }

    /**
     * @return string db name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Query influxDB
     *
     * @param string $query
     * @param array  $params
     *
     * @return ResultSet
     *
     * @throws Exception
     */
    public function query($query, $params = array())
    {
       return $this->client->query($this->name, $query, $params);
    }

    /**
     * Create this database
     *
     * @param RetentionPolicy $retentionPolicy
     *
     * @return ResultSet
     *
     * @throws DatabaseException
     * @throws Exception
     */
    public function create(RetentionPolicy $retentionPolicy = null)
    {
        try {
            $this->query(sprintf('CREATE DATABASE %s', $this->name));

            if ($retentionPolicy) {
                $this->createRetentionPolicy($retentionPolicy);
            }

        } catch (\Exception $e) {
            throw new DatabaseException(
                sprintf('Failed to created database %s, exception: %s', $this->name, $e->getMessage())
            );
        }
    }

    public function createRetentionPolicy(RetentionPolicy $retentionPolicy)
    {
        $this->query($this->getRetentionPolicyQuery('CREATE', $retentionPolicy));
    }

    /**
     * Writes points into INfluxdb
     *
     * @param array $points
     * @return ResultSet
     */
    public function writePoints(array $points)
    {
        $payload = array();

        foreach ($points as $point) {

            if (! $point instanceof Point) {
                throw new \InvalidArgumentException('Array of Point should be passed');
            }

            $payload[] = (string) $point;
        }

        return $this->query(implode("\n", $payload));
    }

    /**
     * @param RetentionPolicy $retentionPolicy
     */
    public function alterRetentionPolicy(RetentionPolicy $retentionPolicy)
    {
        $this->query($this->getRetentionPolicyQuery('ALTER', $retentionPolicy));
    }

    /**
     * @return array
     *
     * @throws Exception
     */
    public function listRetentionPolicies()
    {
        return $this->query(sprintf('SHOW RETENTION POLICIES %s', $this->name))->getPoints();
    }


    /**
     * Drop this database
     */
    public function drop()
    {
        $this->query(sprintf('DROP DATABASE %s', $this->name));
    }

    /**
     * Retrieve the query builder
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        return new QueryBuilder($this);
    }

    /**
     * @param                 $method
     * @param RetentionPolicy $retentionPolicy
     *
     * @return string
     */
    protected function getRetentionPolicyQuery($method, RetentionPolicy $retentionPolicy)
    {

        if (!in_array($method, array('CREATE', 'ALTER'))) {
            throw new \InvalidArgumentException(sprintf('%s is not a valid method'));
        }

        $query = sprintf(
            '%s RETENTION POLICY %s ON %s DURATION %s REPLICATION %s',
            $method,
            $retentionPolicy->name,
            $this->name,
            $retentionPolicy->duration,
            $retentionPolicy->replication
        );

        if ($retentionPolicy->default) {
            $query .= " DEFAULT";
        }

        return $query;
    }

}
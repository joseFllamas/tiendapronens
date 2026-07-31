<?php namespace Ozdemir\Datatables\DB;

use wpdb;
use Ozdemir\Datatables\Query;

/**
 * Class WPAdapter
 * @package Ozdemir\Datatables\DB
 */
class WPAdapter extends DBAdapter
{
    /**
     * @var wpdb
     */
    protected $wpdb;
    /**
     * @var array
     */
    protected $config;

    /**
     * WPAdapter constructor.
     * @param array $config
     */
    public function __construct($config)
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->config = $config;
    }

    /**
     * @return $this
     */
    public function connect()
    {
        // WordPress handles the database connection via $wpdb, so no explicit connection is needed here.
        return $this;
    }

    /**
     * @param Query $query
     * @param bool $array
     * @param bool $use_cache
     * @return mixed
     */
    public function query(Query $query, $array = true, $use_cache = true)
    {
        if ($array) {
            return $this->wpdb->get_results($query, ARRAY_A);
        } else {
            return $this->wpdb->get_results($query);
        }
    }

    /**
     * @param Query $query
     * @return mixed
     */
    public function count(Query $query)
    {
        $sql = "SELECT COUNT(*) as rowcount FROM ({$query}) AS t";
        $data = $this->wpdb->get_row($sql, ARRAY_A);
        return $data['rowcount'];
    }

    /**
     * @param string $string
     * @param Query $query
     * @return string
     */
    public function escape($string, Query $query)
    {
        return "'" . esc_sql($string) . "'";
    }
}
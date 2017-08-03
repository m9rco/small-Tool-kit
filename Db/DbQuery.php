<?php

/**
 * DbQuery
 *
 * @author   Pu ShaoWei
 * @date     2017/8/3
 * @license  Mozilla
 */
class DbQuery
{
    protected $query = array(
        'select' => array(),
        'from' => '',
        'join' => array(),
        'where' => array(),
        'group' => array(),
        'having' => array(),
        'order' => array(),
        'limit' => array('offset' => 0, 'limit' => 0),
    );

    public function select($fields) {
        $this->query['select'] = array();
        if (!empty($fields))
            $this->query['select'][] = $fields;

        return $this;
    }

    public function from($table, $alias = null) {
        $this->query['from'] = array();
        if (!empty($table))
            $this->query['from'][] = $table . ($alias ? ' ' . $alias : '');

        return $this;
    }

    public function join($join) {
        empty($this->query['join']) && $this->query['join'] = array();
        if (!empty($join))
            $this->query['join'][] = $join;

        return $this;
    }

    public function leftJoin($table, $alias = null, $on = null) {
        return $this->join('LEFT JOIN ' . $table . ($alias ? ' ' .  $alias  : '') . ($on ? ' ON ' . $on : ''));
    }

    public function innerJoin($table, $alias = null, $on = null) {
        return $this->join('INNER JOIN ' . $table . ($alias ? ' ' . $alias : '') . ($on ? ' ON ' . $on : ''));
    }

    public function leftOuterJoin($table, $alias = null, $on = null) {
        return $this->join('LEFT OUTER JOIN ' . $table . ($alias ? ' ' . $alias : '') . ($on ? ' ON ' . $on : ''));
    }

    public function naturalJoin($table, $alias = null) {
        return $this->join('NATURAL JOIN ' . $table . ($alias ? ' ' . $alias : ''));
    }

    public function where($restriction) {
        $this->query['where'] = array();
        if (!empty($restriction))
            $this->query['where'][] = $restriction;

        return $this;
    }

    public function having($restriction) {
        $this->query['having'] = array();
        if (!empty($restriction))
            $this->query['having'][] = $restriction;

        return $this;
    }

    public function orderBy($fields) {
        $this->query['order'] = array();
        if (!empty($fields))
            $this->query['order'][] = $fields;

        return $this;
    }

    public function groupBy($fields) {
        $this->query['group'] = array();
        if (!empty($fields))
            $this->query['group'][] = $fields;

        return $this;
    }

    public function limit($limit, $offset = 0) {
        $this->query['limit'] = array();
        $offset = (int)$offset;
        if ($offset < 0)
            $offset = 0;

        $this->query['limit'] = array(
            'offset' => $offset,
            'limit' => (int)$limit,
        );

        return $this;
    }

    public function build() {
        $sql = 'SELECT ' . ((($this->query['select'])) ? implode(",\n", $this->query['select']) : '*') . "\n";

        if (!$this->query['from'])
            die('DbQuery->build() missing from clause');
        $sql .= 'FROM ' . implode(', ', $this->query['from']) . "\n";

        if ($this->query['join'])
            $sql .= implode("\n", $this->query['join']) . "\n";

        if ($this->query['where'])
            $sql .= 'WHERE (' . implode(') AND (', $this->query['where']) . ")\n";

        if ($this->query['group'])
            $sql .= 'GROUP BY ' . implode(', ', $this->query['group']) . "\n";

        if ($this->query['having'])
            $sql .= 'HAVING (' . implode(') AND (', $this->query['having']) . ")\n";

        if ($this->query['order'])
            $sql .= 'ORDER BY ' . implode(', ', $this->query['order']) . "\n";

        if ($this->query['limit']['limit'])
        {
            $limit = $this->query['limit'];
            $sql .= 'LIMIT ' . (($limit['offset']) ? $limit['offset'] . ', ' . $limit['limit'] : $limit['limit']);
            $this->query['limit'] = array('offset' => 0, 'limit' => 0);
        }

        return $sql;
    }

    public function __toString() {
        return $this->build();
    }

}


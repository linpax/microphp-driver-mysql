<?php

namespace Micro\Driver\Mysql;

use Micro\Driver\Pdo\Pdo;


class Mysql extends Pdo
{

    /**
     * Set current database
     *
     * @access public
     *
     * @param string $dbName Database name
     *
     * @return boolean
     */
    public function switchDatabase($dbName)
    {
        return $this->conn->exec("USE {$dbName};") !== false;
    }
    /**
     * Info of database
     *
     * @access public
     * @param string $dbName Database name
     * @return array
     */
    public function infoDatabase($dbName)
    {
        $sth = $this->conn->query("SHOW TABLE STATUS FROM {$dbName};");
        $result = [];
        foreach ($sth->fetchAll() AS $row) {
            $result[] = [
                'name' => $row['Name'],
                'engine' => $row['Engine'],
                'rows' => $row['Rows'],
                'length' => $row['Avg_row_length'],
                'increment' => $row['Auto_increment'],
                'collation' => $row['Collation']
            ];
        }
        return $result;
    }
    /**
     * List database names on this connection
     *
     * @access public
     * @return array
     */
    public function listDatabases()
    {
        $sql = 'SHOW DATABASES;';
        if ($this->getDriverType() === 'pgsql') {
            $sql = 'SELECT datname FROM pg_database;';
        }
        $sth = $this->conn->query($sql);
        $result = [];
        foreach ($sth->fetchAll() AS $row) {
            $result[] = $row[0];
        }
        return $result;
    }
    /**
     * List tables in db
     *
     * @access public
     * @return array
     */
    public function listTables()
    {
        return $this->conn->query('SHOW TABLES;')->fetchAll(\PDO::FETCH_COLUMN, 0);
    }
    /**
     * Create a new table
     *
     * @param string $name Table name
     * @param array $elements Table elements
     * @param string $params Table params
     *
     * @return int
     */
    public function createTable($name, array $elements = [], $params = '')
    {
        return $this->conn->exec(
            sprintf('SELECT TABLE IF NOT EXISTS `%s` (%s) %s;', $name, implode(', ', $elements), $params)
        );
    }
    /**
     * Get array fields into table
     *
     * @access public
     *
     * @param string $table Table name
     *
     * @return array
     */
    public function listFields($table)
    {
        $sth = $this->conn->query("SHOW COLUMNS FROM {$table};");
        $result = [];
        foreach ($sth->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[] = [
                'field' => $row['Field'],
                'type' => $row['Type'],
                'null' => $row['Null'],
                'key' => $row['Key'],
                'default' => $row['Default'],
                'extra' => $row['Extra']
            ];
        }
        return $result;
    }
    /**
     * Insert row into table
     *
     * @access public
     *
     * @param string $table Table name
     * @param array $line Line or lines to added
     * @param bool $multi Is multi rows
     *
     * @return bool
     */
    public function insert($table, array $line = [], $multi = false)
    {
        $fields = '`'.implode('`, `', array_keys($multi ? $line[0] : $line)).'`';
        $values = ':'.implode(', :', array_keys($multi ? $line[0] : $line));
        $rows = $multi ? $line : [$line];
        $id = null;
        if ($rows) {
            $this->conn->beginTransaction();
            $dbh = null;
            foreach ($rows AS $row) {
                $dbh = $this->conn->prepare("INSERT INTO {$table} ({$fields}) VALUES ({$values});")->execute($row);
            }
            $id = $dbh ? $this->conn->lastInsertId() : false;
            $this->conn->commit();
        }
        return $id ?: false;
    }
    /**
     * Update row in table
     *
     * @access public
     *
     * @param string $table Table name
     * @param array $elements Elements to update
     * @param string $conditions Conditions for search
     *
     * @return bool
     */
    public function update($table, array $elements = [], $conditions = '')
    {
        $keys = array_keys($elements);
        if (0 === count($keys)) {
            return false;
        }
        $valStr = [];
        foreach ($keys as $key) {
            $valStr[] = '`'.$key.'` = :'.$key;
        }
        $valStr = implode(',', $valStr);
        if ($conditions) {
            $conditions = 'WHERE '.$conditions;
        }
        return $this->conn->prepare("UPDATE {$table} SET {$valStr} {$conditions};")->execute($elements);
    }
    /**
     * Exists element in the table by params
     *
     * @access public
     *
     * @param string $table Table name
     * @param array $params Params array
     *
     * @return bool
     */
    public function exists($table, array $params = [])
    {
        $keys = [];
        foreach ($params AS $key => $val) {
            $keys[] = '`'.$key.'`="'.$val.'""';
        }
        $sth = $this->conn->prepare('SELECT * FROM '.$table.' WHERE '.implode(' AND ', $keys).' LIMIT 1;');
        /** @noinspection PdoApiUsageInspection */
        $sth->execute();
        return (bool)$sth->rowCount();
    }
}
<?php

class DB
{
    public static $instance;
    public $pdo;
    public $prefix;
    public $table;
    public $action;
    public $where;
    public $where_values;
    public $order_by;
    public $data;


    /**
     * Create DataBase instance and set a PDO object plus a prefix
     * 
     * @param string $host
     * @param string $user
     * @param string $pass
     * @param string $name
     * @param string $prefix
     * @param string $charset
     * @return void
     */
    public function __construct(string $host, string $user, string $pass, string $name, string $prefix = '', string $charset = 'utf8')
    {
        $this->init();
        $this->prefix = $prefix;
        $this->pdo = new PDO("mysql:host=$host; dbname=$name", $user, $pass, [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES ".$charset,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => true
        ]);
    }


    /**
     * Init fields of DataBase instance to be ready to query
     * 
     * @return $this
     */
    public function init()
    {
        $this->table = "";
        $this->action = "";
        $this->where = array();
        $this->where_values = array();
        $this->order_by = array();
        $this->data = array();
        return $this;
    }


    /**
     * Connect DataBase and set static instance to be used
     * 
     * @return $this
     */
    public static function connect(string $host, string $user, string $pass, string $name, string $prefix = '', string $charset = 'utf8')
    {
        return (static::$instance = new static($host, $user, $pass, $name, $prefix, $charset));
    }


    /**
     * Used to call __tables and __table functions
     * 
     * @return $this
     */
    public function __call($name, $args)
    {
        if (method_exists(static::class, "__".$name)) {
            return call_user_func_array([$this, "__".$name], $args);
        }
    }


    /**
     * If an instance is set, call the __call function with the arguments
     * 
     * @return $this
     */
    public static function __callStatic($name, $args)
    {
        if (static::$instance) {
            return static::$instance->__call($name, $args);
        }
    }


    /**
     * Get list of tables with SHOW TABLES in DataBase
     *
     * @return array
     */
    public function __tables()
    {
        if (!$this->pdo) {
            throw new Exception("no PDO connection etablished");
        }

        if (!$pdo_statement = $this->pdo->query('SHOW TABLES')) {
            throw new Exception("The query() method has return false");
        }

        return $pdo_statement->fetchAll(PDO::FETCH_COLUMN);
    }
    

    /**
     * Set the table that we want to query
     *
     * @param string $table : the name of the table we want to query further
     * 
     * @return $this
     */
    public function __table(string $table)
    {
        $this->table = $this->prefix.$table;
        $this->action = "SELECT";
        return $this;
    }


    /**
     * Get list of columns in a table
     *
     * @return array
     */
    public function __columns(string $table)
    {
        if (!$this->pdo) {
            throw new Exception("You must set a PDO object with DB::setPDO() and DB::newPDO()");
        }

        if (!$pre = $this->pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?')) {
            throw new Exception("The prepare() method has return false");
        }

        if (!$pre->execute([$this->prefix.$table])) {
            throw new Exception("The execute() method has return false");
        }

        return $pre->fetchAll(PDO::FETCH_COLUMN);
    }


    public function select()
    {
        $this->action = "SELECT";
        return $this;
    }


    public function where($column, $operatorOrValue, $value = null)
    {
        $operator = is_null($value) ? '=' : $operatorOrValue;
        $value = is_null($value) ? $operatorOrValue : $value;
        $this->where[] = array($column, $operator, $value);
        $this->where_values = array_merge($this->where_values, is_array($value) ? $value : array($value));
        return $this;
    }


    public function orderBy(string $column, string $direction = "ASC")
    {
        $this->order_by[$column] = $direction; 
        return $this;
    }


    public function insert(array $data)
    {
        $this->action = "INSERT";
        $this->data = $data;
        return $this;
    }


    public function update(array $data)
    {
        $this->action = "UPDATE";
        $this->data = $data;
        return $this;
    }


    public function delete()
    {
        $this->action = "DELETE";
        return $this;
    }


    /**
     * Get the prepared SQL code of DB object
     */
    public function toSql()
    {
        $sql = "";
        if ($this->action === "SELECT") {
            $sql = "SELECT * FROM ".$this->table;
        } else if ($this->action === "INSERT") {
            $sql = "INSERT INTO ".$this->table."(`".implode("`,`", array_keys($this->data))."`) VALUES (?".str_repeat(",?", count($this->data) - 1).")";
        } else if ($this->action === "UPDATE") {
            $sql = "UPDATE ".$this->table." SET `".implode(" = ?`, `", array_keys($this->data))."` = ?";
        } else if ($this->action === "DELETE") {
            $sql = "DELETE FROM ".$this->table;
        } else if ($this->action === "COUNT") {
            $sql = "SELECT COUNT(*) FROM ".$this->table;
        }
        
        # where statement
        if ($this->where) {
            $sql .= " WHERE ";
            $lastKey = array_keys($this->where);
            $lastKey = end($lastKey); // get the last key of the array
            foreach ($this->where as $key => $w) {
                $sql .= $w[0]." ".$w[1];
                if (is_array($w[2])) {
                    $sql .= " (?".str_repeat(",?", count($w[2]) - 1).")";
                } else {
                    $sql .= " ?".($key !== $lastKey ? " AND " : "");
                }
            }
        }

        # order by statement
        if ($this->order_by) {
            $sql .= ' ORDER BY ';
            foreach ($this->order_by as $column => $direction) {
                $sql .= $column." ".$direction;
            }
        }

        return $sql;
    }




    public function run(string $classname = 'stdClass')
    {
        if (!$pre = $this->pdo->prepare($this->toSql())) {
            throw new Exception("The prepare() method return false");
        }

        $result = false;
        if ($this->action === "SELECT") {
            $result = $pre->execute($this->where_values) ? $pre->fetchAll(PDO::FETCH_CLASS, $classname) : false;
        } else if ($this->action === "INSERT") {
            // var_dump($this->toSql()); echo "<br>";
            // var_dump($this->data); echo "<br>";
            $result = $this->data && $pre->execute(array_values($this->data)) ? $this->pdo->lastInsertID() : false;
        } else if ($this->action === "UPDATE") {
            $result = $this->data && $pre->execute(array_merge(array_values($this->data), $this->where_values));
        } else if ($this->action === "DELETE") {
            $result = $pre->execute($this->where_values);
        } else if ($this->action === "COUNT") {
            $result = $pre->execute($this->where_values) ? (int)$pre->fetchColumn() : false;
        }
        
        $this->init();
        return $result;
    }
}


<?php
namespace App\Core;

/**
 * Database Class
 * 
 * PDO database wrapper
 */
class Database
{
    /**
     * Database instance (singleton)
     *
     * @var Database
     */
    private static $instance = null;
    
    /**
     * PDO instance
     *
     * @var \PDO
     */
    private $pdo;
    
    /**
     * PDO statement
     *
     * @var \PDOStatement
     */
    private $statement;
    
    /**
     * Database constructor
     */
    private function __construct()
    {
        $host = getenv('DB_HOST');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASS');
        $name = getenv('DB_NAME');
        
        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        
        $options = [
            \PDO::ATTR_PERSISTENT => true,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
            \PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        try {
            $this->pdo = new \PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get Database instance (singleton)
     *
     * @return Database
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Prepare SQL statement
     *
     * @param string $sql SQL query
     * @return Database
     */
    public function query($sql)
    {
        $this->statement = $this->pdo->prepare($sql);
        return $this;
    }
    
    /**
     * Bind parameters to statement
     *
     * @param array $params Parameters to bind
     * @return Database
     */
    public function bind($params = [])
    {
        if ($params) {
            foreach ($params as $param => $value) {
                $type = $this->getParamType($value);
                
                // Convert numeric strings to proper param
                if (is_numeric($param)) {
                    $param++;
                } else {
                    $param = ":{$param}";
                }
                
                $this->statement->bindValue($param, $value, $type);
            }
        }
        
        return $this;
    }
    
    /**
     * Execute prepared statement
     *
     * @return bool Success or failure
     */
    public function execute()
    {
        try {
            return $this->statement->execute();
        } catch (\PDOException $e) {
            error_log("SQL Error: " . $e->getMessage());
            error_log("SQL Query: " . $this->statement->queryString);
            return false;
        }
    }
    
    /**
     * Fetch all results
     *
     * @return array Results
     */
    public function fetchAll()
    {
        $this->execute();
        return $this->statement->fetchAll();
    }
    
    /**
     * Fetch single result
     *
     * @return object|null Result
     */
    public function fetch()
    {
        $this->execute();
        return $this->statement->fetch();
    }
    
    /**
     * Get row count
     *
     * @return int Row count
     */
    public function rowCount()
    {
        return $this->statement->rowCount();
    }
    
    /**
     * Get last insert ID
     *
     * @return int Last insert ID
     */
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Get number of affected rows
     *
     * @return int Number of affected rows
     */
    public function affectedRows()
    {
        return $this->statement->rowCount();
    }
    
    /**
     * Begin transaction
     *
     * @return bool Success or failure
     */
    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit transaction
     *
     * @return bool Success or failure
     */
    public function commit()
    {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback transaction
     *
     * @return bool Success or failure
     */
    public function rollback()
    {
        return $this->pdo->rollBack();
    }
    
    /**
     * Get parameter type for binding
     *
     * @param mixed $value Value to check
     * @return int PDO parameter type
     */
    private function getParamType($value)
    {
        if (is_int($value)) {
            return \PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            return \PDO::PARAM_BOOL;
        } elseif (is_null($value)) {
            return \PDO::PARAM_NULL;
        } else {
            return \PDO::PARAM_STR;
        }
    }
} 
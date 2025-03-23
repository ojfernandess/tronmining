<?php
namespace App\Core;

use App\Core\Database;

/**
 * Base Model Class
 * 
 * Provides database operations for all models
 */
class Model
{
    /**
     * Database instance
     *
     * @var Database
     */
    protected $db;
    
    /**
     * Table name
     *
     * @var string
     */
    protected $table;
    
    /**
     * Primary key
     *
     * @var string
     */
    protected $primaryKey = 'id';
    
    /**
     * Fillable fields
     *
     * @var array
     */
    protected $fillable = [];
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get all records from table
     *
     * @param string $orderBy Order by field
     * @param string $order Order direction (ASC, DESC)
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Records
     */
    public function all($orderBy = null, $order = 'ASC', $limit = null, $offset = null)
    {
        $query = "SELECT * FROM {$this->table}";
        
        if ($orderBy) {
            $query .= " ORDER BY {$orderBy} {$order}";
        }
        
        if ($limit) {
            $query .= " LIMIT {$limit}";
            
            if ($offset) {
                $query .= " OFFSET {$offset}";
            }
        }
        
        return $this->db->query($query)->fetchAll();
    }
    
    /**
     * Find record by ID
     *
     * @param int $id ID to find
     * @return object|null Record or null if not found
     */
    public function find($id)
    {
        $query = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        return $this->db->query($query)->bind(['id' => $id])->fetch();
    }
    
    /**
     * Find records by field value
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $operator Comparison operator
     * @return array Records
     */
    public function where($field, $value, $operator = '=')
    {
        $query = "SELECT * FROM {$this->table} WHERE {$field} {$operator} :value";
        return $this->db->query($query)->bind(['value' => $value])->fetchAll();
    }
    
    /**
     * Find first record by field value
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $operator Comparison operator
     * @return object|null Record or null if not found
     */
    public function firstWhere($field, $value, $operator = '=')
    {
        $query = "SELECT * FROM {$this->table} WHERE {$field} {$operator} :value LIMIT 1";
        return $this->db->query($query)->bind(['value' => $value])->fetch();
    }
    
    /**
     * Create new record
     *
     * @param array $data Data to insert
     * @return int|bool ID of inserted record or false on failure
     */
    public function create($data)
    {
        // Filter data to only include fillable fields
        $filteredData = array_intersect_key($data, array_flip($this->fillable));
        
        if (empty($filteredData)) {
            return false;
        }
        
        $fields = array_keys($filteredData);
        $placeholders = array_map(fn($field) => ":{$field}", $fields);
        
        $query = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") 
                 VALUES (" . implode(', ', $placeholders) . ")";
        
        $result = $this->db->query($query)->bind($filteredData)->execute();
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Update record
     *
     * @param int $id ID of record to update
     * @param array $data Data to update
     * @return bool Success or failure
     */
    public function update($id, $data)
    {
        // Filter data to only include fillable fields
        $filteredData = array_intersect_key($data, array_flip($this->fillable));
        
        if (empty($filteredData)) {
            return false;
        }
        
        $setStatements = array_map(fn($field) => "{$field} = :{$field}", array_keys($filteredData));
        
        $query = "UPDATE {$this->table} 
                 SET " . implode(', ', $setStatements) . " 
                 WHERE {$this->primaryKey} = :id";
        
        $filteredData['id'] = $id;
        
        return $this->db->query($query)->bind($filteredData)->execute();
    }
    
    /**
     * Delete record
     *
     * @param int $id ID of record to delete
     * @return bool Success or failure
     */
    public function delete($id)
    {
        $query = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        return $this->db->query($query)->bind(['id' => $id])->execute();
    }
    
    /**
     * Count records in table
     *
     * @param string $where Where condition
     * @param array $params Query parameters
     * @return int Number of records
     */
    public function count($where = null, $params = [])
    {
        $query = "SELECT COUNT(*) as count FROM {$this->table}";
        
        if ($where) {
            $query .= " WHERE {$where}";
        }
        
        $result = $this->db->query($query)->bind($params)->fetch();
        
        return $result ? $result->count : 0;
    }
    
    /**
     * Execute raw SQL query
     *
     * @param string $query SQL query
     * @param array $params Query parameters
     * @param bool $fetchAll Whether to fetch all results or just one
     * @return mixed Query results
     */
    public function raw($query, $params = [], $fetchAll = true)
    {
        $statement = $this->db->query($query)->bind($params);
        
        return $fetchAll ? $statement->fetchAll() : $statement->fetch();
    }
    
    /**
     * Execute SQL query
     *
     * @param string $query SQL query
     * @param array $params Query parameters
     * @return bool Success or failure
     */
    public function execute($query, $params = [])
    {
        return $this->db->query($query)->bind($params)->execute();
    }
    
    /**
     * Get number of rows affected by last query
     *
     * @return int Number of affected rows
     */
    public function affectedRows()
    {
        return $this->db->affectedRows();
    }
    
    /**
     * Begin transaction
     *
     * @return bool Success or failure
     */
    public function beginTransaction()
    {
        return $this->db->beginTransaction();
    }
    
    /**
     * Commit transaction
     *
     * @return bool Success or failure
     */
    public function commit()
    {
        return $this->db->commit();
    }
    
    /**
     * Rollback transaction
     *
     * @return bool Success or failure
     */
    public function rollback()
    {
        return $this->db->rollback();
    }
} 
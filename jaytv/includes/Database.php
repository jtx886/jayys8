<?php
/**
 * Jay影视 - 数据库类
 */

class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            die('数据库连接失败: ' . $this->conn->connect_error);
        }
        $this->conn->set_charset('utf8mb4');
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die('SQL错误: ' . $this->conn->error);
        }
        if ($params) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) $types .= 'i';
                elseif (is_float($param)) $types .= 'd';
                elseif (is_string($param)) $types .= 's';
                else $types .= 'b';
            }
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt;
    }
    
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row;
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
    
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $fields_str = implode('`, `', $fields);
        $sql = "INSERT INTO `$table` (`$fields_str`) VALUES ($placeholders)";
        $this->query($sql, array_values($data));
        return $this->conn->insert_id;
    }
    
    public function update($table, $data, $where, $where_params = []) {
        $set = [];
        foreach (array_keys($data) as $field) {
            $set[] = "`$field` = ?";
        }
        $set_str = implode(', ', $set);
        $sql = "UPDATE `$table` SET $set_str WHERE $where";
        $params = array_merge(array_values($data), $where_params);
        $this->query($sql, $params);
        return $this->conn->affected_rows;
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM `$table` WHERE $where";
        $this->query($sql, $params);
        return $this->conn->affected_rows;
    }
    
    public function begin_transaction() {
        $this->conn->begin_transaction();
    }
    
    public function commit() {
        $this->conn->commit();
    }
    
    public function rollback() {
        $this->conn->rollback();
    }
}

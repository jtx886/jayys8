<?php
/**
 * Jay影视 - 数据库类
 * 兼容PHP 7.4 - 8.x
 */

class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        mysqli_report(MYSQLI_REPORT_OFF);
        $this->conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_errno) {
            throw new Exception('MySQL连接失败 [' . $this->conn->connect_errno . ']: ' . $this->conn->connect_error);
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
            throw new Exception('SQL预处理错误: ' . $this->conn->error . ' | SQL: ' . substr($sql, 0, 150));
        }
        if (!empty($params)) {
            $types = '';
            $args = [];
            foreach ($params as $param) {
                if (is_int($param)) $types .= 'i';
                elseif (is_float($param) || is_double($param)) $types .= 'd';
                elseif (is_string($param)) $types .= 's';
                else $types .= 'b';
            }
            $args[] = $types;
            foreach ($params as $i => $param) {
                $args[] = &$params[$i];
            }
            call_user_func_array([$stmt, 'bind_param'], $args);
        }
        $stmt->execute();
        if ($stmt->errno) {
            throw new Exception('SQL执行错误: ' . $stmt->error);
        }
        return $stmt;
    }
    
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row;
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $result = $stmt->get_result();
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
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
    
    public function beginTransaction() {
        $this->conn->begin_transaction();
    }
    
    public function commit() {
        $this->conn->commit();
    }
    
    public function rollback() {
        $this->conn->rollback();
    }
}

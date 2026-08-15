<?php


class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    // --- Query methods ---
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function select($table, $where = [], $order = '', $limit = '') {
        $sql = "SELECT * FROM $table";
        $params = [];
        if (!empty($where)) {
            $conds = [];
            foreach ($where as $k => $v) {
                $conds[] = "$k = ?";
                $params[] = $v;
            }
            $sql .= " WHERE " . implode(' AND ', $conds);
        }
        if ($order) $sql .= " ORDER BY $order";
        if ($limit) $sql .= " LIMIT $limit";
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function insert($table, $data) {
        $keys = array_keys($data);
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table (" . implode(',', $keys) . ") VALUES ($placeholders)";
        $stmt = $this->query($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function update($table, $data, $where) {
        $set = [];
        $params = [];
        foreach ($data as $k => $v) {
            $set[] = "$k = ?";
            $params[] = $v;
        }
        $conds = [];
        foreach ($where as $k => $v) {
            $conds[] = "$k = ?";
            $params[] = $v;
        }
        $sql = "UPDATE $table SET " . implode(',', $set) . " WHERE " . implode(' AND ', $conds);
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function delete($table, $where) {
        $conds = [];
        $params = [];
        foreach ($where as $k => $v) {
            $conds[] = "$k = ?";
            $params[] = $v;
        }
        $sql = "DELETE FROM $table WHERE " . implode(' AND ', $conds);
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function count($table, $where = []) {
        $sql = "SELECT COUNT(*) as c FROM $table";
        $params = [];
        if (!empty($where)) {
            $conds = [];
            foreach ($where as $k => $v) {
                $conds[] = "$k = ?";
                $params[] = $v;
            }
            $sql .= " WHERE " . implode(' AND ', $conds);
        }
        $stmt = $this->query($sql, $params);
        return (int) $stmt->fetch()['c'];
    }


    public function escape($str) {
        return $this->pdo->quote($str);
    }
}


function db() {
    return Database::getInstance();
}

$pdo = db()->getConnection();
?>

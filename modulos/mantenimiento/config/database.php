<?php
// modulos/mantenimiento/config/database.php
require_once __DIR__ . '/../../../core/database/conexion.php';


if (!class_exists('Database')) {
    class Database
    {
        private $connection = null;

        public function __construct()
        {
            global $conn;
            $this->connection = $conn;
        }

        public function getConnection()
        {
            return $this->connection;
        }

        public function query($sql, $params = [])
        {
            try {
                $stmt = $this->connection->prepare($sql);
                $stmt->execute($params);
                return $stmt;
            } catch (PDOException $e) {
                throw new Exception("Error en consulta: " . $e->getMessage());
            }
        }

        public function fetchAll($sql, $params = [])
        {
            return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
        }

        public function fetchOne($sql, $params = [])
        {
            return $this->query($sql, $params)->fetch(PDO::FETCH_ASSOC);
        }

        public function lastInsertId()
        {
            return $this->connection->lastInsertId();
        }
    }
}

// Instancia global
$db = new Database();
?>
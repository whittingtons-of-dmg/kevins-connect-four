<?php

namespace MediumDubb\ConnectFour;

use Exception;
use PDO;
use PDOException;
use PDOStatement;

class PDOConnector
{
    public ?PDO $pdo = null;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        if (! isset($_ENV)) {
            echo "something went wrong";
            die;
        }

        $host = $_ENV['DB_HOST'] ?? "localhost";
        $dbName = $_ENV['DB_NAME'] ?? "dev_connect_four";
        $charset = $_ENV['DB_CHARSET'] ?? "utf8mb4";
        $username = $_ENV['DB_USERNAME'] ?? "root";
        $password = $_ENV['DB_PASSWORD'] ?? "root";

        $dsn = "mysql:host={$host};dbname={$dbName};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements
        ];

        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            // Log this error in production instead of echoing it directly
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        if (empty($params)) {
            return $this->pdo->query($sql);
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    public function test_connection(): string
    {
        return is_null($this->pdo) ? "Connection terminated" : "Connection active";
    }
}
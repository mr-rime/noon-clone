<?php

class Database
{
    private $host;
    private $username;
    private $password;
    private $db_name;
    private $port;

    public $conn;

    public function __construct()
    {
        $this->host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
        $this->username = $_ENV['DB_USER'] ?? getenv('DB_USER');
        $this->password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');
        $this->db_name = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
        $this->port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3307;
    }


    public function getConnection(): mysqli
    {
        $this->conn = new mysqli(
            $this->host,
            $this->username,
            $this->password,
            $this->db_name,
            $this->port
        );

        if ($this->conn->connect_error) {
            die(json_encode([
                "error" => "Connection failed: " . $this->conn->connect_error
            ]));
        }

        return $this->conn;
    }
}

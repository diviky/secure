<?php

declare(strict_types=1);

class UserManager
{
    private string $databaseHost;
    private int $maxConnections;
    public const DEFAULT_TIMEOUT = 30;

    public function __construct(string $host, int $connections = 10)
    {
        $this->databaseHost = $host;
        $this->maxConnections = $connections;
    }

    public function createUser(string $username, string $email): bool
    {
        $userData = [
            'username' => $username,
            'email' => $email,
            'created_at' => date('Y-m-d H:i:s')
        ];

        return $this->saveToDatabase($userData);
    }

    private function saveToDatabase(array $data): bool
    {
        $connection = $this->getConnection();

        if (!$connection) {
            return false;
        }

        $query = "INSERT INTO users SET username = ?, email = ?, created_at = ?";

        return $this->executeQuery($query, array_values($data));
    }

    private function getConnection()
    {
        // Simulate database connection
        return $this->databaseHost ? true : false;
    }

    private function executeQuery(string $query, array $params): bool
    {
        // Simulate query execution
        return !empty($query) && !empty($params);
    }

    public function getUserCount(): int
    {
        return $this->maxConnections;
    }
}

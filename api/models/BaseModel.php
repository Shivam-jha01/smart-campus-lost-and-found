<?php
/**
 * Base Model Class
 * Provides common database interaction methods and PostgreSQL RETURNING helpers.
 */
abstract class BaseModel {
    protected PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    protected function insert(string $table, array $data): int {
        $cols = array_keys($data);
        $fields = implode(', ', $cols);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders}) RETURNING id";
        $st = $this->pdo->prepare($sql);
        $st->execute(array_values($data));
        return (int)$st->fetchColumn();
    }

    protected function update(string $table, array $data, string $where, array $whereParams = []): bool {
        $sets = [];
        $values = [];
        foreach ($data as $col => $val) {
            $sets[] = "{$col} = ?";
            $values[] = $val;
        }
        $setString = implode(', ', $sets);
        $sql = "UPDATE {$table} SET {$setString} WHERE {$where}";
        $st = $this->pdo->prepare($sql);
        return $st->execute(array_merge($values, $whereParams));
    }

    protected function delete(string $table, string $where, array $whereParams = []): bool {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $st = $this->pdo->prepare($sql);
        return $st->execute($whereParams);
    }
}

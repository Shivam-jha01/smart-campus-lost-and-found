<?php
/**
 * Base Model Class
 * Provides common database interaction methods and PostgreSQL RETURNING helpers.
 * Validates table and column identifiers to protect against identifier injection.
 */
abstract class BaseModel {
    protected PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    protected function validateIdentifier(string $name, string $type = 'column'): void {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $name)) {
            throw new InvalidArgumentException("Invalid {$type} identifier: {$name}");
        }
    }

    protected function insert(string $table, array $data): int {
        $this->validateIdentifier($table, 'table');
        $cols = array_keys($data);
        foreach ($cols as $col) {
            $this->validateIdentifier($col, 'column');
        }

        $fields = implode(', ', $cols);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders}) RETURNING id";
        $st = $this->pdo->prepare($sql);
        $st->execute(array_values($data));
        return (int)$st->fetchColumn();
    }

    protected function update(string $table, array $data, string $where, array $whereParams = []): bool {
        $this->validateIdentifier($table, 'table');
        $sets = [];
        $values = [];
        foreach ($data as $col => $val) {
            $this->validateIdentifier($col, 'column');
            $sets[] = "{$col} = ?";
            $values[] = $val;
        }
        $setString = implode(', ', $sets);
        $sql = "UPDATE {$table} SET {$setString} WHERE {$where}";
        $st = $this->pdo->prepare($sql);
        return $st->execute(array_merge($values, $whereParams));
    }

    protected function delete(string $table, string $where, array $whereParams = []): bool {
        $this->validateIdentifier($table, 'table');
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $st = $this->pdo->prepare($sql);
        return $st->execute($whereParams);
    }
}

<?php
/**
 * Base model.  All other models extend this.
 *
 * Conventions:
 *   - $table is set by the child
 *   - Queries use prepared statements with bound parameters
 *   - $this->pdo is always the active PDO connection
 */
abstract class Model {

    protected ?PDO $pdo;
    protected string $table = '';

    public function __construct(?PDO $pdo = null) {
        if ($pdo === null) {
            global $pdo;
        }
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Model requires a PDO connection.');
        }
        $this->pdo = $pdo;
    }

    public function pdo(): PDO { return $this->pdo; }

    public function all(string $orderBy = 'id ASC', int $limit = 0): array {
        $sql = "SELECT * FROM `{$this->table}` ORDER BY {$orderBy}";
        if ($limit > 0) $sql .= " LIMIT " . (int) $limit;
        return $this->pdo->query($sql)->fetchAll();
    }

    public function find(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM `{$this->table}` WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findBy(string $column, $value): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM `{$this->table}` WHERE `{$column}` = ? LIMIT 1");
        $stmt->execute([$value]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function where(array $conditions, string $orderBy = null, int $limit = 0): array {
        $where = [];
        $params = [];
        foreach ($conditions as $col => $val) {
            $where[] = "`{$col}` = ?";
            $params[] = $val;
        }
        $sql = "SELECT * FROM `{$this->table}` WHERE " . implode(' AND ', $where);
        if ($orderBy) $sql .= " ORDER BY {$orderBy}";
        if ($limit > 0) $sql .= " LIMIT " . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(array $data): int {
        $data = $this->beforeCreate($data);
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => "`{$c}` = ?", $cols);
        $sql = "INSERT INTO `{$this->table}` SET " . implode(', ', $placeholders);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $data = $this->beforeUpdate($data);
        $set = [];
        $params = [];
        foreach ($data as $col => $val) {
            $set[] = "`{$col}` = ?";
            $params[] = $val;
        }
        $params[] = $id;
        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $set) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function count(array $conditions = []): int {
        $sql = "SELECT COUNT(*) AS c FROM `{$this->table}`";
        $params = [];
        if ($conditions) {
            $where = [];
            foreach ($conditions as $col => $val) {
                $where[] = "`{$col}` = ?";
                $params[] = $val;
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetch()['c'];
    }

    protected function beforeCreate(array $data): array { return $data; }
    protected function beforeUpdate(array $data): array { return $data; }
}

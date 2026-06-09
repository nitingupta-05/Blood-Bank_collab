<?php
class StorageController {
    private PDO $pdo;
    private StorageArea $model;
    private AlertService $alerts;
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->model = new StorageArea();
        $this->alerts = new AlertService($pdo);
    }

    public function add(array $data, int $bankId, int $actorId): array {
        $name = ValidationHelper::nonEmptyString($data['name'] ?? null, 100);
        $cap  = ValidationHelper::positiveInt($data['capacity'] ?? 0, 1);
        if (!$name) return ['ok' => false, 'error' => 'Name is required'];
        if ($cap === null) return ['ok' => false, 'error' => 'Capacity must be a positive integer'];
        if ($bankId <= 0) return ['ok' => false, 'error' => 'No blood bank context for this user'];

        $temp = isset($data['temperature']) && is_numeric($data['temperature']) ? (float) $data['temperature'] : null;

        $id = $this->model->create([
            'name'          => $name,
            'capacity'      => $cap,
            'current_temperature' => $temp,
        ]);

        if ($temp !== null) {
            $this->model->logTemperature($id, $temp);
            $this->alerts->storageTemperatureAlert($id, $temp);
        }
        audit($this->pdo, 'storage.add', $actorId, 'storage_areas:' . $id);
        return ['ok' => true, 'id' => $id];
    }

    public function listForBank(?int $bankId): array {
        return $this->model->listForBank($bankId);
    }

    public function logTemperature(int $storageId, float $temp, int $actorId): void {
        $this->model->logTemperature($storageId, $temp);
        $this->alerts->storageTemperatureAlert($storageId, $temp);
        audit($this->pdo, 'storage.temperature', $actorId, 'storage_areas:' . $storageId, ['temp' => $temp]);
    }

    public function getTemperatureHistory(int $storageId, int $hours = 24): array {
        return $this->model->getTemperatureHistory($storageId, $hours);
    }

    public function stats(): array {
        return $this->model->getStats();
    }
}

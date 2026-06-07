<?php
class DonorController {
    private PDO $pdo;
    private Donor $model;
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->model = new Donor($pdo);
    }

    public function add(array $data, int $actorId): array {
        $userId = (int) ($data['user_id'] ?? 0);
        $bg     = ValidationHelper::bloodGroup($data['blood_group'] ?? null);
        $age    = ValidationHelper::age($data['age'] ?? null);
        $weight = ValidationHelper::weight($data['weight'] ?? null);

        if ($userId <= 0 || !$bg) return ['ok' => false, 'error' => 'User and blood group are required'];
        if ($age === null)    return ['ok' => false, 'error' => 'Age must be between ' . MIN_DONOR_AGE . ' and ' . MAX_DONOR_AGE];
        if ($weight === null) return ['ok' => false, 'error' => 'Weight must be at least ' . MIN_DONOR_WEIGHT . ' kg'];

        $existing = $this->model->getByUserId($userId);
        if ($existing) return ['ok' => false, 'error' => 'Donor already exists for this user'];

        $eligible = ($age >= MIN_DONOR_AGE && $age <= MAX_DONOR_AGE && $weight >= MIN_DONOR_WEIGHT) ? 'eligible' : 'ineligible';

        $id = $this->model->create([
            'user_id'            => $userId,
            'blood_group'        => $bg,
            'age'                => $age,
            'weight'             => $weight,
            'medical_history'    => ValidationHelper::sanitizeForOutput($data['medical_history'] ?? '') ?: null,
            'eligibility_status' => $eligible,
        ]);
        audit($this->pdo, 'donor.add', $actorId, 'donors:' . $id, ['bg' => $bg]);
        return ['ok' => true, 'id' => $id];
    }

    public function list(): array {
        $stmt = $this->pdo->query(
            "SELECT d.*, u.first_name, u.last_name, u.phone, u.city, u.email
             FROM `donors` d
             JOIN `users` u ON u.id = d.user_id
             ORDER BY d.created_at DESC"
        );
        return $stmt->fetchAll();
    }

    public function stats(): array {
        return $this->model->getStats();
    }
}

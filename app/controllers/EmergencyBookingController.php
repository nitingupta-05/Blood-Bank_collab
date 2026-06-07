<?php
class EmergencyController {
    private PDO $pdo;
    private AlertService $alerts;
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->alerts = new AlertService($pdo);
    }

    public function create(array $data, int $actorId, bool $actorIsHospital): array {
        $bg      = ValidationHelper::bloodGroup($data['blood_group'] ?? null);
        $qty     = ValidationHelper::positiveInt($data['quantity'] ?? 0, 1);
        $urgency = $data['urgency_level'] ?? 'moderate';
        $loc     = ValidationHelper::nonEmptyString($data['location'] ?? null, 255);
        $name    = ValidationHelper::nonEmptyString($data['patient_name'] ?? ($data['patient_details'] ?? null), 255);
        $phone   = ValidationHelper::phone($data['contact_phone'] ?? null);

        if (!in_array($urgency, URGENCY_LEVELS, true)) {
            return ['ok' => false, 'error' => 'Invalid urgency level'];
        }
        if (!$bg || $qty === null || !$loc || !$name || !$phone) {
            return ['ok' => false, 'error' => 'All fields are required'];
        }

        // Only allow hospital/admin to post; otherwise attribute to a "guest" hospital if it exists, else reject.
        $hospitalId = $actorIsHospital ? $actorId : $this->firstHospitalId();
        if (!$hospitalId) return ['ok' => false, 'error' => 'No hospital context available; please log in as a hospital user'];

        $ttl = EMERGENCY_TTL_HOURS[$urgency] ?? 36;
        $lat = isset($data['latitude']) && is_numeric($data['latitude']) ? (float) $data['latitude'] : null;
        $lon = isset($data['longitude']) && is_numeric($data['longitude']) ? (float) $data['longitude'] : null;

        $stmt = $this->pdo->prepare(
            "INSERT INTO `emergency_requests`
                (blood_group, quantity, urgency_level, hospital_id, location, patient_name, contact_phone, latitude, longitude, expires_at, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), 'active')"
        );
        $stmt->execute([$bg, $qty, $urgency, $hospitalId, $loc, $name, $phone, $lat, $lon, $ttl]);
        $id = (int) $this->pdo->lastInsertId();

        $notified = $this->alerts->emergencyPosted($id);
        audit($this->pdo, 'emergency.create', $actorId, 'emergency_requests:' . $id, ['bg' => $bg, 'urgency' => $urgency]);
        return ['ok' => true, 'id' => $id, 'notified' => $notified];
    }

    public function fulfill(int $id, int $actorId): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE `emergency_requests` SET status = 'fulfilled', fulfilled_at = NOW() WHERE id = ? AND status = 'active'"
        );
        $ok = $stmt->execute([$id]) && $stmt->rowCount() > 0;
        if ($ok) audit($this->pdo, 'emergency.fulfill', $actorId, 'emergency_requests:' . $id);
        return $ok;
    }

    public function activeFeed(): array {
        $model = new EmergencyRequest($this->pdo);
        return $model->activeFeed();
    }

    public function stats(): array {
        $model = new EmergencyRequest($this->pdo);
        return $model->getStats();
    }

    private function firstHospitalId(): ?int {
        $row = $this->pdo->query("SELECT id FROM `users` WHERE role = 'hospital' AND is_active = 1 ORDER BY id ASC LIMIT 1")->fetch();
        return $row ? (int) $row['id'] : null;
    }
}

class BookingController {
    private PDO $pdo;
    private AlertService $alerts;
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->alerts = new AlertService($pdo);
    }

    public function create(array $data, int $actorId, bool $actorIsHospital): array {
        $bg   = ValidationHelper::bloodGroup($data['blood_group'] ?? null);
        $qty  = ValidationHelper::positiveInt($data['quantity'] ?? 0, 1);
        $date = ValidationHelper::dateYmd($data['required_date'] ?? null);
        if (!$bg || $qty === null || !$date) {
            return ['ok' => false, 'error' => 'Blood group, quantity, and required date are required'];
        }
        $hospitalId = $actorIsHospital ? $actorId : $this->firstHospitalId();
        if (!$hospitalId) return ['ok' => false, 'error' => 'No hospital context; please log in as a hospital user'];

        $patientName = ValidationHelper::nonEmptyString($data['patient_name'] ?? null, 255);
        $stmt = $this->pdo->prepare(
            "INSERT INTO `blood_bookings` (hospital_id, blood_group, quantity, required_date, patient_name, status)
             VALUES (?, ?, ?, ?, ?, 'pending')"
        );
        $stmt->execute([$hospitalId, $bg, $qty, $date, $patientName]);
        $id = (int) $this->pdo->lastInsertId();
        audit($this->pdo, 'booking.create', $actorId, 'blood_bookings:' . $id, ['bg' => $bg, 'qty' => $qty]);
        return ['ok' => true, 'id' => $id];
    }

    public function setStatus(int $bookingId, string $status, int $actorId): array {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return ['ok' => false, 'error' => 'Invalid status'];
        }
        $stmt = $this->pdo->prepare(
            "UPDATE `blood_bookings` SET status = ?, approved_by = ?, approval_date = NOW() WHERE id = ?"
        );
        $ok = $stmt->execute([$status, $actorId, $bookingId]);
        if ($ok) $this->alerts->bookingStatusChanged($bookingId, $status);
        return ['ok' => $ok];
    }

    public function list(int $page = 1, int $perPage = ITEMS_PER_PAGE): array {
        $model = new Booking($this->pdo);
        return $model->listRecent($perPage, max(0, ($page - 1) * $perPage));
    }

    public function stats(): array {
        $model = new Booking($this->pdo);
        return $model->getStats();
    }

    private function firstHospitalId(): ?int {
        $row = $this->pdo->query("SELECT id FROM `users` WHERE role = 'hospital' AND is_active = 1 ORDER BY id ASC LIMIT 1")->fetch();
        return $row ? (int) $row['id'] : null;
    }
}

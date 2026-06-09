<?php
/**
 * Wires business events to the NotificationDispatcher.
 *
 * One method per business event:
 *   - emergency posted
 *   - booking approved/rejected
 *   - blood unit expired
 *   - storage temperature out of range
 *   - low stock
 *   - donor newly eligible
 */
class AlertService {

    private PDO $pdo;
    private NotificationDispatcher $notif;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->notif = new NotificationDispatcher($pdo);
    }

    public function emergencyPosted(int $requestId): int {
        $stmt = $this->pdo->prepare("SELECT * FROM `emergency_requests` WHERE id = ?");
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();
        if (!$req) return 0;

        $msg = sprintf(
            "URGENT: %s needs %s x%d in %s (urgency: %s). Contact %s.",
            $req['patient_name'], $req['blood_group'], $req['quantity'],
            $req['location'], strtoupper($req['urgency_level']), $req['contact_phone']
        );

        $notified = $this->notif->sendToEligibleDonorsInCity(
            (string) $req['location'], 'emergency', $msg, 'in_app'
        );

        $this->notif->sendToAdmins('emergency', "Emergency posted: {$msg}", 'in_app');

        return $notified;
    }

    public function bookingStatusChanged(int $bookingId, string $status): void {
        $stmt = $this->pdo->prepare(
            "SELECT b.*, u.id AS hospital_user_id FROM `blood_bookings` b
             JOIN `users` u ON u.id = b.hospital_id
             WHERE b.id = ?"
        );
        $stmt->execute([$bookingId]);
        $b = $stmt->fetch();
        if (!$b) return;

        $msg = sprintf("Booking #%d (%s x%d) is now %s.", $bookingId, $b['blood_group'], $b['quantity'], strtoupper($status));
        $this->notif->send((int) $b['hospital_user_id'], 'booking', $msg, 'in_app');
    }

    public function storageTemperatureAlert(int $storageId, float $temp): void {
        $tpdo = tenant_pdo() ?: $this->pdo;
        $stmt = $tpdo->prepare("SELECT name FROM `storage_areas` WHERE id = ?");
        $stmt->execute([$storageId]);
        $sa = $stmt->fetch();
        if (!$sa) return;
        if ($temp < STORAGE_TEMP_MIN || $temp > STORAGE_TEMP_MAX) {
            $this->notif->sendToAdmins(
                'temperature',
                sprintf("Storage '%s' temperature is %.1f°C (allowed %d-%d°C).", $sa['name'], $temp, STORAGE_TEMP_MIN, STORAGE_TEMP_MAX)
            );
        }
    }

    public function lowStockAlert(string $bloodGroup, int $available): void {
        if ($available >= LOW_STOCK_THRESHOLD) return;
        $this->notif->sendToAdmins('low_stock', sprintf("Low stock: %s has only %d units left.", $bloodGroup, $available));
    }
}

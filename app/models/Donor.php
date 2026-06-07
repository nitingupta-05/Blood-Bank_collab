<?php
class Donor extends Model {

    protected string $table = 'donors';

    public function getByUserId(int $userId): ?array {
        return $this->findBy('user_id', $userId);
    }

    public function isEligible(array $donor): bool {
        if (!is_array($donor) || empty($donor)) return false;
        $age = (int) ($donor['age'] ?? 0);
        $wt  = (float) ($donor['weight'] ?? 0);
        if ($age < MIN_DONOR_AGE || $age > MAX_DONOR_AGE) return false;
        if ($wt  < MIN_DONOR_WEIGHT) return false;
        if (!empty($donor['last_donation_date'])) {
            $next = DateHelper::getNextEligibleDate($donor['last_donation_date']);
            if (strtotime($next) > time()) return false;
        }
        return true;
    }

    public function recordDonation(int $donorId): bool {
        $donor = $this->find($donorId);
        if (!$donor) return false;
        return $this->update($donorId, [
            'last_donation_date' => DateHelper::today(),
            'next_eligible_date' => DateHelper::addDays(DateHelper::today(), DONATION_INTERVAL),
            'eligibility_status' => 'ineligible',
            'total_donations'    => (int) $donor['total_donations'] + 1,
        ]);
    }

    public function findEligibleByGroupAndCity(string $bloodGroup, string $city): array {
        $stmt = $this->pdo->prepare(
            "SELECT d.*, u.first_name, u.last_name, u.phone, u.city, u.latitude, u.longitude
             FROM `donors` d
             JOIN `users` u ON u.id = d.user_id
             WHERE d.blood_group = ?
               AND d.eligibility_status = 'eligible'
               AND u.is_active = 1
               AND u.city LIKE ?
             ORDER BY u.first_name
             LIMIT 50"
        );
        $stmt->execute([$bloodGroup, '%' . $city . '%']);
        return $stmt->fetchAll();
    }

    public function getStats(): array {
        $total = (int) $this->pdo->query("SELECT COUNT(*) AS c FROM `donors`")->fetch()['c'];
        $eligible = (int) $this->pdo->query("SELECT COUNT(*) AS c FROM `donors` WHERE eligibility_status='eligible'")->fetch()['c'];
        $donations = (int) $this->pdo->query("SELECT COALESCE(SUM(total_donations),0) AS c FROM `donors`")->fetch()['c'];
        return [
            'total'           => $total,
            'eligible'        => $eligible,
            'ineligible'      => max(0, $total - $eligible),
            'total_donations' => $donations,
        ];
    }
}

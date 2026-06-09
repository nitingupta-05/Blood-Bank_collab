<?php
class User extends Model {

    protected string $table = 'users';

    protected function beforeCreate(array $data): array {
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
            unset($data['password']);
        }
        return $data;
    }

    public function findByEmail(string $email): ?array {
        return $this->findBy('email', strtolower(trim($email)));
    }

    public function findByResetToken(string $token): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `users`
             WHERE password_reset_token = ? AND password_reset_expires > NOW()
             LIMIT 1"
        );
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updatePassword(int $id, string $newPassword): bool {
        return $this->update($id, [
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
            'password_reset_token' => null,
            'password_reset_expires' => null,
        ]);
    }

    public function recordSuccessfulLogin(int $id): void {
        $this->update($id, [
            'last_login_at' => date('Y-m-d H:i:s'),
            'failed_login_attempts' => 0,
            'lockout_until' => null,
        ]);
    }

    public function recordFailedLogin(int $id): void {
        $this->pdo->prepare(
            "UPDATE `users` SET failed_login_attempts = failed_login_attempts + 1,
              lockout_until = IF(failed_login_attempts + 1 >= ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), lockout_until)
             WHERE id = ?"
        )->execute([LOGIN_MAX_ATTEMPTS, LOGIN_LOCKOUT_MINUTES, $id]);
    }

    public function authenticate(string $email, string $password): ?array {
        $user = $this->findByEmail($email);
        if (!$user) return null;

        if (!empty($user['lockout_until']) && strtotime($user['lockout_until']) > time()) {
            return null;
        }

        if (password_verify($password, (string) $user['password_hash'])) {
            if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
                $this->update($user['id'], ['password_hash' => password_hash($password, PASSWORD_BCRYPT)]);
            }
            $this->recordSuccessfulLogin((int) $user['id']);
            return $user;
        }

        $this->recordFailedLogin((int) $user['id']);
        return null;
    }

    public function getByRole(string $role): array {
        return $this->where(['role' => $role], 'created_at DESC');
    }

    public function getAdmins(): array {
        $stmt = $this->pdo->query(
            "SELECT id FROM `users` WHERE role IN ('super_admin','blood_bank_admin','staff') AND is_active = 1"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}

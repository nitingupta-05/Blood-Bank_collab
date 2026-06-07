<?php
/**
 * Resolves the blood-bank context for the current user.
 *
 * Cross-tenant data (blood units, storage areas) is scoped to the bank
 * the current user is associated with, so controllers repeatedly need
 * to answer "which blood bank should this query be filtered to?".
 *
 * Resolution rules:
 *   - super_admin         → null (sees all banks; no filter)
 *   - any other role with a user row in `users` → id of the bank whose
 *                           email matches the user's, falling back to a
 *                           bank in the user's city.  Returns null if
 *                           no matching bank exists.
 *   - unauthenticated     → null
 *
 * Resolution always re-reads email/city from the `users` table to avoid
 * stale session data (a user could have updated their profile since they
 * logged in).
 */
function current_blood_bank_id(?array $user = null, ?PDO $pdo = null): ?int {
    $user = $user ?? current_user();
    if (!$user) return null;
    if (($user['role'] ?? '') === 'super_admin') return null;

    $pdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
    if (!$pdo instanceof PDO) return null;

    $stmt = $pdo->prepare("SELECT email, city FROM `users` WHERE id = ?");
    $stmt->execute([(int) $user['id']]);
    $u = $stmt->fetch();
    if (!$u) return null;

    $stmt = $pdo->prepare("SELECT id FROM `blood_banks` WHERE email = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$u['email']]);
    $bank = $stmt->fetch();
    if ($bank) return (int) $bank['id'];

    $stmt = $pdo->prepare("SELECT id FROM `blood_banks` WHERE city = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$u['city'] ?? '']);
    $bank = $stmt->fetch();
    return $bank ? (int) $bank['id'] : null;
}

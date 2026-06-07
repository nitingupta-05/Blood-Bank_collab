<?php
/**
 * Handles web authentication: login, register, forgot/reset password, logout.
 */
class AuthController {

    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function login(array $_params): void {
        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!ValidationHelper::email($email) || !ValidationHelper::password($password)) {
            $_SESSION['error'] = 'Please enter a valid email and a password of at least 8 characters.';
            header('Location: ' . app_url('login'));
            exit;
        }
        if (is_account_locked($this->pdo, $email)) {
            $_SESSION['error'] = 'Too many failed login attempts. Please try again in ' . LOGIN_LOCKOUT_MINUTES . ' minutes.';
            header('Location: ' . app_url('login'));
            exit;
        }

        $user = (new User($this->pdo))->authenticate($email, $password);
        record_login_attempt($this->pdo, $email, (bool) $user);

        if (!$user) {
            $_SESSION['error'] = 'Invalid credentials.';
            header('Location: ' . app_url('login'));
            exit;
        }
        if ((int) $user['is_active'] !== 1) {
            $_SESSION['error'] = 'Your account is disabled. Please contact the administrator.';
            header('Location: ' . app_url('login'));
            exit;
        }

        login_user($user);
        audit($this->pdo, 'auth.login', (int) $user['id']);
        header('Location: ' . app_url(redirect_after_login((string) $user['role'])));
        exit;
    }

    public function registerDonor(array $_params): void {
        $fullName  = trim((string) ($_POST['full_name'] ?? ''));
        $email     = trim((string) ($_POST['email'] ?? ''));
        $password  = (string) ($_POST['password'] ?? '');
        $age       = (int) ($_POST['age'] ?? 0);
        $gender    = trim((string) ($_POST['gender'] ?? ''));
        $bloodGroup= (string) ($_POST['blood_group'] ?? '');
        $weight    = (float) ($_POST['weight'] ?? 0);
        $phone     = trim((string) ($_POST['phone'] ?? ''));
        $address   = trim((string) ($_POST['address'] ?? ''));
        $city      = trim((string) ($_POST['city'] ?? ''));
        $lastDonation = trim((string) ($_POST['last_donation_date'] ?? ''));
        $preferredBank = (int) ($_POST['preferred_blood_bank_id'] ?? 0);

        $first = $last = '';
        if ($fullName !== '') {
            $parts = preg_split('/\s+/', $fullName);
            $first = $parts[0] ?? '';
            $last  = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
        }

        if ($fullName === '' || !ValidationHelper::email($email) || !ValidationHelper::password($password)) {
            $_SESSION['error'] = 'Please provide a valid name, email, and password (min 8 characters).';
            header('Location: ' . app_url('register')); exit;
        }
        if (!in_array($bloodGroup, BLOOD_GROUPS, true) || !ValidationHelper::age($age) || !ValidationHelper::weight($weight)) {
            $_SESSION['error'] = 'Please provide valid medical details.';
            header('Location: ' . app_url('register')); exit;
        }
        if (!ValidationHelper::phone($phone) || $address === '' || $city === '') {
            $_SESSION['error'] = 'Phone, address, and city are required.';
            header('Location: ' . app_url('register')); exit;
        }
        $lastDonationDate = null; $nextEligible = null; $eligible = true;
        if ($lastDonation !== '') {
            if (!ValidationHelper::dateYmd($lastDonation)) {
                $_SESSION['error'] = 'Invalid last donation date.';
                header('Location: ' . app_url('register')); exit;
            }
            $lastDonationDate = $lastDonation;
            $nextEligible = DateHelper::getNextEligibleDate($lastDonation);
            $eligible = strtotime($nextEligible) <= time();
        }

        $userModel = new User($this->pdo);
        if ($userModel->findByEmail($email)) {
            $_SESSION['error'] = 'That email is already registered. Please log in.';
            header('Location: ' . app_url('login')); exit;
        }

        $userId = $userModel->create([
            'role'       => 'donor',
            'first_name' => $first,
            'last_name'  => $last,
            'email'      => strtolower($email),
            'password'   => $password,
            'phone'      => $phone,
            'address'    => $address,
            'city'       => $city,
            'is_active'  => 1,
        ]);

        (new Donor($this->pdo))->create([
            'user_id'              => $userId,
            'preferred_blood_bank_id' => $preferredBank > 0 ? $preferredBank : null,
            'blood_group'          => $bloodGroup,
            'age'                  => $age,
            'weight'               => $weight,
            'gender'               => in_array(strtolower($gender), ['male', 'female', 'other'], true) ? strtolower($gender) : null,
            'medical_history'      => $gender !== '' ? ('Gender: ' . $gender) : null,
            'last_donation_date'   => $lastDonationDate,
            'next_eligible_date'   => $nextEligible,
            'eligibility_status'   => $eligible ? 'eligible' : 'ineligible',
        ]);

        audit($this->pdo, 'auth.register_donor', (int) $userId);
        $_SESSION['success'] = 'Donor registered successfully. Please log in.';
        header('Location: ' . app_url('login')); exit;
    }

    public function forgotPassword(array $_params): void {
        $email = trim((string) ($_POST['email'] ?? ''));
        if (!ValidationHelper::email($email)) {
            $_SESSION['error'] = 'Please enter a valid email.';
            header('Location: ' . app_url('forgot-password')); exit;
        }
        $user = (new User($this->pdo))->findByEmail($email);
        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            $this->pdo->prepare(
                "UPDATE `users` SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?"
            )->execute([$token, $expires, (int) $user['id']]);

            $link = APP_URL . '/index.php?route=reset-password&token=' . urlencode($token);
            $sent = EmailService::send($email, 'Password reset', "Use this link to reset your password: {$link}");
            $_SESSION['success'] = $sent
                ? 'Password reset link sent to your email.'
                : 'If your email is registered, a reset link has been sent. (Server mail not configured; check logs.)';
        } else {
            $_SESSION['success'] = 'If that email exists, a reset link was sent.';
        }
        header('Location: ' . app_url('login')); exit;
    }

    public function resetPassword(array $_params): void {
        $token    = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['confirm_password'] ?? '');

        if (!ValidationHelper::password($password) || $password !== $confirm) {
            $_SESSION['error'] = 'Password must be at least 8 characters and match the confirmation.';
            header('Location: ' . app_url('reset-password') . '&token=' . urlencode($token)); exit;
        }
        $user = (new User($this->pdo))->findByResetToken($token);
        if (!$user) {
            $_SESSION['error'] = 'Invalid or expired reset token.';
            header('Location: ' . app_url('forgot-password')); exit;
        }
        (new User($this->pdo))->updatePassword((int) $user['id'], $password);
        audit($this->pdo, 'auth.reset_password', (int) $user['id']);
        $_SESSION['success'] = 'Password reset successful. Please log in.';
        header('Location: ' . app_url('login')); exit;
    }

    public function logout(array $_params): void {
        $u = current_user();
        if ($u) audit($this->pdo, 'auth.logout', $u['id']);
        logout_user();
        header('Location: ' . app_url('home'));
        exit;
    }
}

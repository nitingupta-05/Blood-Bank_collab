<?php
/**
 * Single API controller.  All endpoints in routes/api.php are dispatched here.
 *
 * Each action receives the array of route parameters (including "_user" with the
 * authenticated user) and is expected to call json_out() to terminate the request.
 */
class ApiController {

    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    /* ============================================================
     *  PUBLIC ENDPOINTS
     * ============================================================ */

    public function publicStats(array $_): void {
        $stats = [
            'donors' => (int) $this->pdo->query("SELECT COUNT(*) FROM `donors`")->fetchColumn(),
            'units'  => (int) $this->pdo->query("SELECT COUNT(*) FROM `blood_units` WHERE status='available' AND expiry_date > CURDATE()")->fetchColumn(),
            'banks'  => (int) $this->pdo->query("SELECT COUNT(*) FROM `blood_banks`")->fetchColumn(),
            'emerg'  => (int) $this->pdo->query("SELECT COUNT(*) FROM `emergency_requests` WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
        ];
        json_out(['ok' => true, 'stats' => $stats]);
    }

    public function publicBloodStock(array $_): void {
        $rows = $this->pdo->query(
            "SELECT blood_group, COUNT(*) AS c
             FROM `blood_units` WHERE status='available' AND expiry_date > CURDATE()
             GROUP BY blood_group"
        )->fetchAll();
        $map = [];
        foreach ($rows as $r) $map[$r['blood_group']] = (int) $r['c'];
        $available = array_map(fn($g) => $map[$g] ?? 0, BLOOD_GROUPS);
        json_out(['ok' => true, 'labels' => BLOOD_GROUPS, 'available' => $available]);
    }

    public function publicBloodBanks(array $_): void {
        $items = (new BankController($this->pdo))->listPublic();
        json_out(['ok' => true, 'items' => $items]);
    }

    public function publicEmergencyFeed(array $_): void {
        $items = (new EmergencyController($this->pdo))->activeFeed();
        json_out(['ok' => true, 'items' => $items]);
    }

    public function bloodSearch(array $_): void {
        $bg   = ValidationHelper::bloodGroup($_GET['blood_group'] ?? null);
        $city = ValidationHelper::nonEmptyString($_GET['city'] ?? null, 100);
        $qty  = max(1, (int) ($_GET['units_required'] ?? 1));
        $lat  = isset($_GET['latitude'])  && is_numeric($_GET['latitude'])  ? (float) $_GET['latitude']  : null;
        $lon  = isset($_GET['longitude']) && is_numeric($_GET['longitude']) ? (float) $_GET['longitude'] : null;
        if (!$bg || !$city) json_out(['ok' => false, 'error' => 'Blood group and city are required'], 422);

        $result = (new BankController($this->pdo))->bloodSearch($bg, $city, $qty, $lat, $lon);
        json_out(['ok' => true] + $result);
    }

    public function publicBooking(array $params): void {
        $user = $params['_user'] ?? null;
        json_out((new BookingController($this->pdo))->create(
            read_json_body() ?: $_POST,
            $user['id'] ?? 0,
            $user && $user['role'] === 'hospital'
        ));
    }

    public function publicEmergency(array $params): void {
        $user = $params['_user'] ?? null;
        json_out((new EmergencyController($this->pdo))->create(
            read_json_body() ?: $_POST,
            $user['id'] ?? 0,
            $user && $user['role'] === 'hospital'
        ));
    }

    public function registerBloodBank(array $_): void {
        json_out((new BankController($this->pdo))->register(read_json_body() ?: $_POST));
    }

    /* ============================================================
     *  DASHBOARD
     * ============================================================ */

    public function dashboardStats(array $params): void {
        $stats = (new DashboardController($this->pdo))->stats();
        json_out(['ok' => true, 'stats' => $stats]);
    }

    public function dashboardBloodStock(array $params): void {
        $user = $params['_user'] ?? null;
        $notif = new NotificationDispatcher($this->pdo);
        $data = (new DashboardController($this->pdo))->bloodStock((int) ($user['id'] ?? 0), $notif);
        json_out(['ok' => true] + $data);
    }

    public function dashboardMonthly(array $_): void {
        $data = (new DashboardController($this->pdo))->monthlyDonations();
        json_out(['ok' => true] + $data);
    }

    /* ============================================================
     *  PROFILE / SETTINGS
     * ============================================================ */

    public function profileCurrent(array $params): void {
        $user = $params['_user'] ?? null;
        if (!$user) json_out(['ok' => false, 'error' => 'Unauthorized'], 401);
        $stmt = $this->pdo->prepare("SELECT id, email, role, first_name, last_name, phone, address, city, is_active, created_at FROM `users` WHERE id = ?");
        $stmt->execute([$user['id']]);
        $u = $stmt->fetch() ?: [];
        $bank = null;
        $bankId = $this->resolveBloodBankId($user);
        if ($bankId) {
            $stmt = $this->pdo->prepare("SELECT * FROM `blood_banks` WHERE id = ?");
            $stmt->execute([$bankId]);
            $bank = $stmt->fetch() ?: null;
        }
        json_out(['ok' => true, 'user' => $u, 'blood_bank' => $bank]);
    }

    public function getHospitalDetails(array $params): void {
        $user = $params['_user'] ?? null;
        $ctrl = new SettingsController($this->pdo);
        json_out(['ok' => true, 'hospital' => $ctrl->getHospitalDetails($user['id'])]);
    }

    public function updateHospitalDetails(array $params): void {
        $user = $params['_user'] ?? null;
        $ctrl = new SettingsController($this->pdo);
        json_out($ctrl->updateHospitalDetails($user['id'], read_json_body() ?: $_POST));
    }

    public function getNotificationPrefs(array $params): void {
        $user = $params['_user'] ?? null;
        $ctrl = new SettingsController($this->pdo);
        json_out(['ok' => true, 'settings' => $ctrl->getNotificationSettings($user['id'])]);
    }

    public function updateNotificationPrefs(array $params): void {
        $user = $params['_user'] ?? null;
        $ctrl = new SettingsController($this->pdo);
        json_out($ctrl->updateNotificationSettings($user['id'], read_json_body() ?: $_POST));
    }

    public function systemStats(array $_): void {
        json_out(['ok' => true, 'stats' => (new SettingsController($this->pdo))->getSystemStats()]);
    }

    /* ============================================================
     *  NOTIFICATIONS
     * ============================================================ */

    public function notificationsList(array $params): void {
        $user = $params['_user'] ?? null;
        $limit = max(1, min(200, (int) ($_GET['limit'] ?? 50)));
        $list = (new NotificationController($this->pdo))->list($user['id'], $limit);
        json_out(['ok' => true, 'notifications' => $list]);
    }

    public function notificationsCount(array $params): void {
        $user = $params['_user'] ?? null;
        json_out(['ok' => true, 'count' => (new NotificationController($this->pdo))->countPending($user['id'])]);
    }

    public function notificationsMarkRead(array $params): void {
        $user = $params['_user'] ?? null;
        $nid = (int) (read_json_body()['notification_id'] ?? 0);
        if ($nid <= 0) json_out(['ok' => false, 'error' => 'notification_id required'], 422);
        $ok = (new NotificationController($this->pdo))->markRead($user['id'], $nid);
        json_out(['ok' => $ok]);
    }

    public function notificationsSend(array $params): void {
        $body = read_json_body() ?: $_POST;
        $userId = (int) ($body['user_id'] ?? 0);
        $type   = (string) ($body['type'] ?? '');
        $msg    = (string) ($body['message'] ?? '');
        $channel= (string) ($body['channel'] ?? 'in_app');
        if ($userId <= 0 || !$type || !$msg) json_out(['ok' => false, 'error' => 'user_id, type, message required'], 422);
        $id = (new NotificationController($this->pdo))->send($userId, $type, $msg, $channel);
        json_out(['ok' => $id > 0, 'id' => $id]);
    }

    /* ============================================================
     *  EMERGENCIES
     * ============================================================ */

    public function emergencyStats(array $_): void {
        json_out(['ok' => true, 'stats' => (new EmergencyController($this->pdo))->stats()]);
    }

    public function emergencyFulfill(array $params): void {
        $id = (int) ($params['id'] ?? 0);
        $user = $params['_user'] ?? null;
        if ($id <= 0) json_out(['ok' => false, 'error' => 'Invalid id'], 422);
        $ok = (new EmergencyController($this->pdo))->fulfill($id, (int) $user['id']);
        json_out(['ok' => $ok], $ok ? 200 : 422);
    }

    /* ============================================================
     *  BOOKINGS
     * ============================================================ */

    public function bookingStats(array $_): void {
        json_out(['ok' => true, 'stats' => (new BookingController($this->pdo))->stats()]);
    }

    public function bookingsList(array $_): void {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $items = (new BookingController($this->pdo))->list($page, ITEMS_PER_PAGE);
        json_out(['ok' => true, 'items' => $items, 'page' => $page]);
    }

    public function bookingApprove(array $params): void {
        $user = $params['_user'] ?? null;
        json_out((new BookingController($this->pdo))->setStatus((int) $params['id'], 'approved', (int) $user['id']));
    }

    public function bookingReject(array $params): void {
        $user = $params['_user'] ?? null;
        json_out((new BookingController($this->pdo))->setStatus((int) $params['id'], 'rejected', (int) $user['id']));
    }

    /* ============================================================
     *  DONORS
     * ============================================================ */

    public function donorsList(array $_): void {
        json_out(['ok' => true, 'items' => (new DonorController($this->pdo))->list()]);
    }

    public function donorStats(array $_): void {
        json_out(['ok' => true, 'stats' => (new DonorController($this->pdo))->stats()]);
    }

    public function donorsAdd(array $params): void {
        $user = $params['_user'] ?? null;
        json_out((new DonorController($this->pdo))->add(read_json_body() ?: $_POST, (int) $user['id']));
    }

    /* ============================================================
     *  STORAGE
     * ============================================================ */

    public function storageList(array $params): void {
        $user = $params['_user'] ?? null;
        $bankId = $this->resolveBloodBankId($user);
        json_out(['ok' => true, 'items' => (new StorageController($this->pdo))->listForBank($bankId)]);
    }

    public function storageStats(array $_): void {
        json_out(['ok' => true, 'stats' => (new StorageController($this->pdo))->stats()]);
    }

    public function storageAdd(array $params): void {
        $user = $params['_user'] ?? null;
        $bankId = $this->resolveBloodBankId($user);
        if (!$bankId) json_out(['ok' => false, 'error' => 'No blood bank context for this user'], 422);
        json_out((new StorageController($this->pdo))->add(read_json_body() ?: $_POST, $bankId, (int) $user['id']));
    }

    public function storageLogTemp(array $params): void {
        $user = $params['_user'] ?? null;
        $body = read_json_body() ?: $_POST;
        $sid  = (int) ($body['storage_id'] ?? 0);
        $temp = (float) ($body['temperature'] ?? 0);
        if ($sid <= 0) json_out(['ok' => false, 'error' => 'storage_id required'], 422);
        (new StorageController($this->pdo))->logTemperature($sid, $temp, (int) $user['id']);
        json_out(['ok' => true]);
    }

    /* ============================================================
     *  BLOOD UNITS
     * ============================================================ */

    public function bloodUnitsList(array $params): void {
        $user = $params['_user'] ?? null;
        $bankId = $this->resolveBloodBankId($user);
        $filters = [
            'blood_group'      => $_GET['blood_group'] ?? '',
            'status'           => $_GET['status'] ?? '',
            'storage_location' => $_GET['storage_location'] ?? '',
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $items = (new BloodUnitController($this->pdo))->list($filters, $bankId, $page, ITEMS_PER_PAGE);
        json_out(['ok' => true, 'items' => $items, 'page' => $page]);
    }

    public function bloodUnitsAdd(array $params): void {
        $user = $params['_user'] ?? null;
        $bankId = $this->resolveBloodBankId($user);
        json_out((new BloodUnitController($this->pdo))->add(read_json_body() ?: $_POST, (int) $user['id'], $bankId));
    }

    public function bloodUnitsUpdateStatus(array $params): void {
        $user = $params['_user'] ?? null;
        $body = read_json_body() ?: $_POST;
        $id   = (int) ($body['id'] ?? 0);
        $status = (string) ($body['status'] ?? '');
        if ($id <= 0 || !$status) json_out(['ok' => false, 'error' => 'id and status required'], 422);
        json_out((new BloodUnitController($this->pdo))->updateStatus($id, $status, (int) $user['id']));
    }

    public function bloodUnitsDelete(array $params): void {
        $user = $params['_user'] ?? null;
        $body = read_json_body() ?: $_POST;
        $id   = (int) ($body['id'] ?? 0);
        if ($id <= 0) json_out(['ok' => false, 'error' => 'id required'], 422);
        $ok = (new BloodUnitController($this->pdo))->delete($id, (int) $user['id']);
        json_out(['ok' => $ok]);
    }

    /* ============================================================
     *  REPORTS
     * ============================================================ */

    public function reportsBloodStock(array $_): void {
        $rows = $this->pdo->query(
            "SELECT blood_group, COUNT(*) AS c
             FROM `blood_units` WHERE status='available' AND expiry_date > CURDATE()
             GROUP BY blood_group ORDER BY blood_group"
        )->fetchAll();
        $map = [];
        foreach ($rows as $r) $map[$r['blood_group']] = (int) $r['c'];
        $available = array_map(fn($g) => $map[$g] ?? 0, BLOOD_GROUPS);
        json_out(['ok' => true, 'labels' => BLOOD_GROUPS, 'available' => $available]);
    }

    public function reportsAiInsights(array $_): void {
        json_out(['ok' => true, 'insights' => AIService::getIndiaBloodInsights()]);
    }

    public function reportsPreview(array $params): void {
        $user = $params['_user'] ?? null;
        $type = $_GET['type'] ?? 'inventory';
        $filters = ['blood_group' => $_GET['blood_group'] ?? '', 'status' => $_GET['status'] ?? ''];
        $ctrl = new ReportController($this->pdo);
        $items = match ($type) {
            'inventory' => $ctrl->inventory($filters),
            'donors'    => $ctrl->donors(),
            'emergency' => $ctrl->emergencies(),
            'bookings'  => $ctrl->bookings(),
            default     => null,
        };
        if ($items === null) json_out(['ok' => false, 'error' => 'Invalid report type'], 422);

        $hospital = '';
        $bankId = $this->resolveBloodBankId($user);
        if ($bankId) {
            $stmt = $this->pdo->prepare("SELECT name FROM `blood_banks` WHERE id = ?");
            $stmt->execute([$bankId]);
            $hospital = (string) ($stmt->fetch()['name'] ?? '');
        }
        json_out(['ok' => true, 'items' => $items, 'hospital' => $hospital]);
    }

    public function reportsExportCsv(array $params): void {
        $user = $params['_user'] ?? null;
        $type = $_GET['type'] ?? 'inventory';
        $filters = ['blood_group' => $_GET['blood_group'] ?? '', 'status' => $_GET['status'] ?? ''];
        $ctrl = new ReportController($this->pdo);
        $items = match ($type) {
            'inventory' => $ctrl->inventory($filters),
            'donors'    => $ctrl->donors(),
            'emergency' => $ctrl->emergencies(),
            'bookings'  => $ctrl->bookings(),
            default     => null,
        };
        if ($items === null) json_out(['ok' => false, 'error' => 'Invalid report type'], 422);

        $hospital = '';
        $bankId = $this->resolveBloodBankId($user);
        if ($bankId) {
            $stmt = $this->pdo->prepare("SELECT name FROM `blood_banks` WHERE id = ?");
            $stmt->execute([$bankId]);
            $hospital = (string) ($stmt->fetch()['name'] ?? '');
        }
        $ctrl->exportCsv($items, ucfirst($type) . ' Report', $type . '_report', $hospital);
    }

    /* ============================================================
     *  SEARCH
     * ============================================================ */

    public function globalSearch(array $_): void {
        $q = (string) ($_GET['q'] ?? '');
        $type = (string) ($_GET['type'] ?? 'all');
        json_out(['ok' => true, 'items' => (new SearchController($this->pdo))->globalSearch($q, $type)]);
    }

    /* ============================================================
     *  Helpers
     * ============================================================ */

    /**
     * Resolve the blood bank id for the current user.
     * Delegates to current_blood_bank_id() in app/helpers/CurrentBankHelper.php.
     */
    private function resolveBloodBankId(?array $user): ?int {
        return current_blood_bank_id($user, $this->pdo);
    }
}

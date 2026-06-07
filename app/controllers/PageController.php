<?php
/**
 * Renders the dashboard-style pages.
 * All these require authentication (enforced by the route entry's "roles" option).
 */
class PageController {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function dashboard(array $_) { view('dashboard/index'); }
    public function inventory(array $_) { view('inventory/index'); }
    public function donors(array $_)    { view('donors/index'); }
    public function storage(array $_)   { view('storage/index'); }
    public function emergency(array $_) { view('emergency/index'); }
    public function bookings(array $_)  { view('bookings/index'); }
    public function reports(array $_)   { view('reports/index'); }
    public function settings(array $_)  { view('settings/index'); }
}

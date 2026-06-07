<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Blood Bookings</h1>
        <p class="page-subtitle">Manage blood unit reservations</p>
    </div>
</div>

<!-- Booking Stats -->
<div class="dashboard-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="bk-total">0</div>
                <div class="stat-card-label">Total Bookings</div>
            </div>
            <div class="stat-card-icon primary">
                <i class="fas fa-calendar-check"></i>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="bk-pending">0</div>
                <div class="stat-card-label">Pending Approval</div>
            </div>
            <div class="stat-card-icon warning">
                <i class="fas fa-hourglass-half"></i>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="bk-approved">0</div>
                <div class="stat-card-label">Approved</div>
            </div>
            <div class="stat-card-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="bk-completed">0</div>
                <div class="stat-card-label">Completed</div>
            </div>
            <div class="stat-card-icon info">
                <i class="fas fa-check"></i>
            </div>
        </div>
    </div>
</div>

<!-- Bookings Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Booking Requests</h3>
        <button class="btn btn-sm btn-secondary" onclick="Bookings.exportPdf()">
            <i class="fas fa-download"></i> Export PDF
        </button>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Hospital</th>
                        <th>Blood Group</th>
                        <th>Quantity</th>
                        <th>Required Date</th>
                        <th>Patient</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="bookingsTbody">
                    <tr>
                        <td>City Hospital</td>
                        <td><span class="blood-group o-positive">O+</span></td>
                        <td>3 units</td>
                        <td>20 Jan 2024</td>
                        <td>John Doe</td>
                        <td><span class="badge badge-warning"><i class="fas fa-hourglass-half"></i> Pending</span></td>
                        <td>
                            <button class="btn btn-sm btn-success" title="Approve">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" title="Reject">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td>Central Medical</td>
                        <td><span class="blood-group a-positive">A+</span></td>
                        <td>2 units</td>
                        <td>18 Jan 2024</td>
                        <td>Jane Smith</td>
                        <td><span class="badge badge-success"><i class="fas fa-check"></i> Approved</span></td>
                        <td>
                            <button class="btn btn-sm btn-secondary" title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td>St. Mary's Hospital</td>
                        <td><span class="blood-group b-positive">B+</span></td>
                        <td>4 units</td>
                        <td>22 Jan 2024</td>
                        <td>Mike Johnson</td>
                        <td><span class="badge badge-info"><i class="fas fa-check"></i> Completed</span></td>
                        <td>
                            <button class="btn btn-sm btn-secondary" title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const Bookings = {
    api: `<?php echo APP_URL; ?>/api.php`,
    async load() {
        const s = await (await fetch(`${this.api}?route=bookings/stats`)).json();
        if (s.ok) {
            document.getElementById('bk-total').textContent = s.stats.total;
            document.getElementById('bk-pending').textContent = s.stats.pending;
            document.getElementById('bk-approved').textContent = s.stats.approved;
            document.getElementById('bk-completed').textContent = s.stats.completed;
        }
        const l = await (await fetch(`${this.api}?route=bookings/list`)).json();
        const tb = document.getElementById('bookingsTbody');
        tb.innerHTML = '';
        (l.items || []).forEach(b => {
            const tr = document.createElement('tr');
            const hospital = `${b.first_name || ''} ${b.last_name || ''}`.trim();
            const actions = b.status === 'pending' ? `
                <button class="btn btn-sm btn-success" onclick="Bookings.approve(${b.id})"><i class="fas fa-check"></i></button>
                <button class="btn btn-sm btn-danger" onclick="Bookings.reject(${b.id})"><i class="fas fa-times"></i></button>` : '';
            tr.innerHTML = `<td>${hospital}</td><td>${b.blood_group}</td><td>${b.quantity} units</td>
                <td>${b.required_date}</td><td>${b.patient_name || '-'}</td>
                <td><span class="badge badge-primary">${b.status}</span></td><td>${actions}</td>`;
            tb.appendChild(tr);
        });
        if (!(l.items || []).length) tb.innerHTML = '<tr><td colspan="7" class="text-muted text-center">No bookings.</td></tr>';
    },
    async approve(id) {
        await fetch(`${this.api}?route=bookings/${id}/approve`, { method: 'POST', headers: {'Content-Type':'application/json'}, body: '{}' });
        Toast.success('Approved'); this.load();
    },
    exportPdf() {
        window.open(`${this.api}?route=reports/export-csv&type=bookings`, '_blank');
    },
    async reject(id) {
        await fetch(`${this.api}?route=bookings/${id}/reject`, { method: 'POST', headers: {'Content-Type':'application/json'}, body: '{}' });
        Toast.warning('Rejected'); this.load();
    }
};
document.addEventListener('DOMContentLoaded', () => Bookings.load());
</script>

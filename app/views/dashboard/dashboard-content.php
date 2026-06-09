<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Welcome back! Here's your blood bank overview.</p>
    </div>
    <button class="btn btn-primary" onclick="openAddBloodModal()">
        <i class="fas fa-plus"></i> Add Blood Unit
    </button>
</div>

<!-- Alert Banner -->
<div class="alert alert-warning" id="dashboardEmergencyAlert" style="display:none;">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong id="dashboardEmergencyAlertTitle">0 Emergency Requests</strong>
        <span id="dashboardEmergencyAlertBody">No urgent blood requests.</span>
    </div>
    <button class="alert-close" onclick="this.parentElement.style.display='none';">
        <i class="fas fa-times"></i>
    </button>
</div>

<!-- Statistics Cards -->
<div class="dashboard-grid">
    <!-- Total Blood Units -->
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="dash-total-units">0</div>
                <div class="stat-card-label">Total Blood Units</div>
            </div>
            <div class="stat-card-icon primary">
                <i class="fas fa-vial"></i>
            </div>
        </div>
        <div class="stat-card-change positive">
            <i class="fas fa-arrow-up"></i> 12% from last month
        </div>
    </div>

    <!-- Available Donors -->
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="dash-available-donors">0</div>
                <div class="stat-card-label">Available Donors</div>
            </div>
            <div class="stat-card-icon success">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <div class="stat-card-change positive">
            <i class="fas fa-arrow-up"></i> 8% from last month
        </div>
    </div>

    <!-- Pending Requests -->
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="dash-active-emergencies">0</div>
                <div class="stat-card-label">Pending Requests</div>
            </div>
            <div class="stat-card-icon warning">
                <i class="fas fa-hourglass-half"></i>
            </div>
        </div>
        <div class="stat-card-change negative">
            <i class="fas fa-arrow-down"></i> 5% from last month
        </div>
    </div>

    <!-- Expiring Soon -->
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="dash-expiring-soon-units">0</div>
                <div class="stat-card-label">Expiring Soon</div>
            </div>
            <div class="stat-card-icon danger">
                <i class="fas fa-exclamation-circle"></i>
            </div>
        </div>
        <div class="stat-card-change negative">
            <i class="fas fa-arrow-up"></i> 3% from last month
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="dash-fulfilled-today">0</div>
                <div class="stat-card-label">Fulfilled Today</div>
            </div>
            <div class="stat-card-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
        <div class="stat-card-change positive">
            <i class="fas fa-sync"></i> Updated live
        </div>
    </div>
</div>

<!-- Charts Section -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Blood Stock Chart -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Blood Stock by Group</h3>
            <button class="btn btn-sm btn-secondary">
                <i class="fas fa-download"></i> Export
            </button>
        </div>
        <div class="card-body">
            <canvas id="bloodStockChart"></canvas>
        </div>
    </div>

    <!-- Monthly Donations Chart -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Monthly Donations</h3>
            <button class="btn btn-sm btn-secondary">
                <i class="fas fa-download"></i> Export
            </button>
        </div>
        <div class="card-body">
            <canvas id="donationsChart"></canvas>
        </div>
    </div>
</div>

<!-- Recent Activity Section -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">
    <!-- Recent Donations -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Donations</h3>
            <a href="?route=donors" class="text-primary">View All</a>
        </div>
        <div class="card-body" id="recentDonationsContainer">
            <div style="text-align: center; color: var(--text-light);">Loading...</div>
        </div>
    </div>

    <!-- Emergency Requests -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Emergency Requests</h3>
            <a href="?route=emergency" class="text-primary">View All</a>
        </div>
        <div class="card-body" id="emergencyRequestsContainer">
            <div style="text-align: center; color: var(--text-light);">Loading...</div>
        </div>
    </div>
</div>

<script>
    (function () {
        const stockCanvas = document.getElementById('bloodStockChart');
        const donationsCanvas = document.getElementById('donationsChart');
        if (!stockCanvas || !donationsCanvas) return;

        const stockCtx = stockCanvas.getContext('2d');
        const donationsCtx = donationsCanvas.getContext('2d');

        let stockChart = null;
        let donationsChart = null;

        async function getJSON(endpoint) {
            const r = await fetch(apiUrl(endpoint), { method: 'GET', headers: { 'Accept': 'application/json' } });
            return r.json();
        }

        async function loadDashboard() {
            try {
                const statsRes = await getJSON('dashboard/stats');
                if (statsRes.ok) {
                    document.getElementById('dash-total-units').textContent = statsRes.stats.total_units ?? 0;
                    document.getElementById('dash-available-donors').textContent = statsRes.stats.available_donors ?? 0;
                    document.getElementById('dash-active-emergencies').textContent = statsRes.stats.active_emergency_requests ?? 0;
                    document.getElementById('dash-expiring-soon-units').textContent = statsRes.stats.expiring_soon_units ?? 0;
                    document.getElementById('dash-fulfilled-today').textContent = statsRes.stats.fulfilled_today ?? 0;
                    const alert = document.getElementById('dashboardEmergencyAlert');
                    const title = document.getElementById('dashboardEmergencyAlertTitle');
                    const body = document.getElementById('dashboardEmergencyAlertBody');
                    const active = Number(statsRes.stats.active_emergency_requests || 0);
                    if (active > 0) {
                        alert.style.display = 'flex';
                        title.textContent = `${active} Active Emergency Request${active === 1 ? '' : 's'}`;
                        body.textContent = ' - visible to all connected blood banks.';
                    } else {
                        alert.style.display = 'none';
                    }
                }

                const stockRes = await getJSON('dashboard/blood-stock');
                if (stockRes.ok) {
                    const labels = stockRes.labels || [];
                    const data = stockRes.available || [];
                    const colors = labels.map(l => {
                        // Use JS helper color mapping when possible.
                        return BloodGroup.getColor(l);
                    });

                    if (stockChart) stockChart.destroy();
                    stockChart = new Chart(stockCtx, {
                        type: 'bar',
                        data: {
                            labels,
                            datasets: [{
                                label: 'Available Units',
                                data,
                                backgroundColor: colors,
                                borderRadius: 8,
                                borderSkipped: false
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, grid: { color: 'rgba(0, 0, 0, 0.05)' } },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                }

                const donationsRes = await getJSON('dashboard/monthly-donations');
                if (donationsRes.ok) {
                    const labels = donationsRes.labels || [];
                    const data = donationsRes.donations || [];
                    if (donationsChart) donationsChart.destroy();
                    donationsChart = new Chart(donationsCtx, {
                        type: 'line',
                        data: {
                            labels,
                            datasets: [{
                                label: 'Donations',
                                data,
                                borderColor: '#D32F2F',
                                backgroundColor: 'rgba(211, 47, 47, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 5,
                                pointBackgroundColor: '#D32F2F',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, grid: { color: 'rgba(0, 0, 0, 0.05)' } },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                }
            } catch (e) {
                // Avoid spamming toasts in refresh loops.
                console.error(e);
            }
        }

        loadDashboard();
        setInterval(loadDashboard, 60000);

        // Load recent donations
        async function loadRecentDonations() {
            try {
                const res = await getJSON('donors/list');
                const container = document.getElementById('recentDonationsContainer');
                if (res.ok && res.items && res.items.length > 0) {
                    const items = res.items.slice(0, 3);
                    container.innerHTML = items.map(d => `
                        <div style="display: flex; align-items: center; gap: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                            <div class="blood-group o-positive">${htmlEscape(d.blood_group)}</div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: var(--text-dark);">${htmlEscape(d.first_name)} ${htmlEscape(d.last_name)}</div>
                                <div style="font-size: 0.85rem; color: var(--text-light);">${htmlEscape(d.last_donation_date || 'Never')}</div>
                            </div>
                            <span class="badge badge-success">
                                <i class="fas fa-check"></i> ${htmlEscape(d.eligibility_status)}
                            </span>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = '<div style="text-align: center; color: var(--text-light);">No recent donations</div>';
                }
            } catch (e) {
                console.error(e);
            }
        }

        // Load emergency requests
        async function loadEmergencies() {
            try {
                const res = await getJSON('emergency/feed');
                const container = document.getElementById('emergencyRequestsContainer');
                if (res.ok && res.items && res.items.length > 0) {
                    const items = res.items.slice(0, 3);
                    container.innerHTML = items.map(em => {
                        const badgeClass = em.urgency_level === 'critical' ? 'badge-danger' : em.urgency_level === 'high' ? 'badge-warning' : 'badge-info';
                        const bgClass = em.urgency_level === 'critical' ? 'rgba(244, 67, 54, 0.05)' : 'rgba(255, 152, 0, 0.05)';
                        const borderColor = em.urgency_level === 'critical' ? 'var(--danger)' : 'var(--warning)';
                        return `
                            <div style="padding: 1rem; background: ${bgClass}; border-left: 4px solid ${borderColor}; border-radius: 8px; margin-bottom: 1rem;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                    <div>
                                        <div style="font-weight: 600; color: var(--text-dark);">${htmlEscape(em.first_name)} ${htmlEscape(em.last_name)}</div>
                                        <div style="font-size: 0.85rem; color: var(--text-light);">Needs ${htmlEscape(em.blood_group)} Blood</div>
                                    </div>
                                    <span class="${badgeClass}">
                                        <i class="fas fa-exclamation"></i> ${htmlEscape(em.urgency_level)}
                                    </span>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--text-light);">Qty: ${em.quantity} units</div>
                            </div>
                        `;
                    }).join('');
                } else {
                    container.innerHTML = '<div style="text-align: center; color: var(--text-light);">No active emergencies</div>';
                }
            } catch (e) {
                console.error(e);
            }
        }

        loadRecentDonations();
        loadEmergencies();
    })();
</script>

<script>
function submitAddBloodForm() {
    const formData = {
        blood_group: document.getElementById('bloodGroup').value,
        collection_date: document.getElementById('collectionDate').value,
        expiry_date: document.getElementById('expiryDate').value,
        storage_location: document.getElementById('storageLocation').value
    };
    
    if (!formData.blood_group || !formData.collection_date || !formData.expiry_date || !formData.storage_location) {
        Toast.error('Please fill all required fields');
        return;
    }
    
    BloodUnitManager.add(formData).then(result => {
        if (result) {
            document.getElementById('addBloodForm').reset();
        }
    });
}
</script>

<!-- Add Blood Modal -->
<div class="modal" id="addBloodModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Blood Unit</h2>
            <button class="modal-close" onclick="closeAddBloodModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="addBloodForm">
                <div class="form-group">
                    <label class="form-label">Blood Group *</label>
                    <select id="bloodGroup" class="form-control" required>
                        <option value="">Select Blood Group</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Collection Date *</label>
                    <input type="date" id="collectionDate" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Expiry Date *</label>
                    <input type="date" id="expiryDate" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Storage Location *</label>
                    <select id="storageLocation" class="form-control" required>
                        <option value="">Select Location</option>
                        <option value="Fridge A - Rack 1">Fridge A - Rack 1</option>
                        <option value="Fridge A - Rack 2">Fridge A - Rack 2</option>
                        <option value="Fridge B - Rack 1">Fridge B - Rack 1</option>
                        <option value="Fridge B - Rack 2">Fridge B - Rack 2</option>
                        <option value="Fridge C - Rack 1">Fridge C - Rack 1</option>
                        <option value="Fridge C - Rack 2">Fridge C - Rack 2</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeAddBloodModal()">Cancel</button>
            <button class="btn btn-primary" type="button" onclick="submitAddBloodForm()">Add Blood Unit</button>
        </div>
    </div>
</div>

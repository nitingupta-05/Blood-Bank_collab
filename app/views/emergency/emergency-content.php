<div class="page-header">
    <div>
        <h1 class="page-title">Emergency Blood Network</h1>
        <p class="page-subtitle">Timed hospital requests across connected blood banks.</p>
    </div>
    <button class="btn btn-danger" onclick="Emergency.openModal()">
        <i class="fas fa-bell"></i> New Emergency
    </button>
</div>

<div class="dashboard-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="emerg-active-count">0</div>
                <div class="stat-card-label">Active Emergencies</div>
            </div>
            <div class="stat-card-icon danger"><i class="fas fa-triangle-exclamation"></i></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="emerg-fulfilled-today">0</div>
                <div class="stat-card-label">Fulfilled Today</div>
            </div>
            <div class="stat-card-icon success"><i class="fas fa-check-circle"></i></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="emerg-expired-count">0</div>
                <div class="stat-card-label">Expired Requests</div>
            </div>
            <div class="stat-card-icon warning"><i class="fas fa-hourglass-end"></i></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="emerg-avg-response">0h</div>
                <div class="stat-card-label">Avg Response</div>
            </div>
            <div class="stat-card-icon info"><i class="fas fa-clock"></i></div>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <h3 class="card-title">Request Filters</h3>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:1rem;">
            <input id="emergencySearch" class="form-control" placeholder="Search patient, city, blood group">
            <select id="emergencyUrgencyFilter" class="form-control">
                <option value="">All urgency</option>
                <option value="critical">Critical</option>
                <option value="moderate">Moderate</option>
                <option value="low">Low</option>
            </select>
            <button class="btn btn-secondary" type="button" onclick="Emergency.loadFeed()"><i class="fas fa-filter"></i> Apply</button>
        </div>
    </div>
</div>

<div id="emergencyFeed" style="display:flex; flex-direction:column; gap:1rem;"></div>

<div class="modal" id="emergencyModal">
    <div class="modal-content" style="max-width: 760px;">
        <div class="modal-header">
            <h2 class="modal-title">Post Emergency Request</h2>
            <button class="modal-close" onclick="Emergency.closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="emergencyForm">
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Patient Name *</label>
                        <input id="emergencyPatientName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Phone *</label>
                        <input id="emergencyContactPhone" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Blood Group *</label>
                        <select id="emergencyBloodGroup" class="form-control" required>
                            <option value="">Select</option>
                            <?php foreach (BLOOD_GROUPS as $group): ?>
                                <option value="<?php echo $group; ?>"><?php echo $group; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Units Needed *</label>
                        <input id="emergencyQuantity" type="number" min="1" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Urgency *</label>
                        <select id="emergencyUrgencyLevel" class="form-control" required>
                            <option value="critical">Critical - expires in 24 hr</option>
                            <option value="moderate">Moderate - expires in 36 hr</option>
                            <option value="low">Low - expires in 48 hr</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">City / Location *</label>
                        <input id="emergencyLocation" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Patient Details</label>
                    <textarea id="emergencyPatientDetails" class="form-control" placeholder="Ward, condition, notes"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="Emergency.closeModal()">Cancel</button>
            <button class="btn btn-danger" type="button" onclick="Emergency.create()"><i class="fas fa-bell"></i> Post Request</button>
        </div>
    </div>
</div>

<script>
const Emergency = {
    apiBase: `<?php echo APP_URL; ?>/api.php`,
    timer: null,

    openModal() { document.getElementById('emergencyModal').classList.add('active'); },
    closeModal() { document.getElementById('emergencyModal').classList.remove('active'); },

    badge(urgency) {
        if (urgency === 'critical') return { cls: 'badge badge-danger', label: 'Critical', color: 'var(--danger)' };
        if (urgency === 'moderate' || urgency === 'high') return { cls: 'badge badge-warning', label: 'Moderate', color: 'var(--warning)' };
        return { cls: 'badge badge-info', label: 'Low', color: 'var(--info)' };
    },

    timeLeft(expiresAt) {
        if (!expiresAt) return 'No deadline';
        const diff = new Date(expiresAt.replace(' ', 'T')) - new Date();
        if (diff <= 0) return 'Expired';
        const hours = Math.floor(diff / 3600000);
        const minutes = Math.floor((diff % 3600000) / 60000);
        return `${hours}h ${minutes}m left`;
    },

    async loadStats() {
        const response = await fetch(`${this.apiBase}?route=emergency/stats`);
        const data = await response.json();
        if (!data.ok) return;
        document.getElementById('emerg-active-count').textContent = data.stats.active || 0;
        document.getElementById('emerg-fulfilled-today').textContent = data.stats.fulfilled_today || 0;
        document.getElementById('emerg-expired-count').textContent = data.stats.expired || 0;
        document.getElementById('emerg-avg-response').textContent = `${data.stats.avg_response_hours || 0}h`;
    },

    async loadFeed() {
        await this.loadStats();
        const response = await fetch(`${this.apiBase}?route=emergency/feed`, { headers: { 'Accept': 'application/json' } });
        const data = await response.json();
        const feed = document.getElementById('emergencyFeed');
        const query = document.getElementById('emergencySearch').value.trim().toLowerCase();
        const urgencyFilter = document.getElementById('emergencyUrgencyFilter').value;
        let items = data.items || [];

        if (urgencyFilter) {
            items = items.filter(item => (item.urgency_level === urgencyFilter) || (urgencyFilter === 'moderate' && item.urgency_level === 'high') || (urgencyFilter === 'low' && item.urgency_level === 'medium'));
        }
        if (query) {
            items = items.filter(item => `${item.patient_name || ''} ${item.contact_phone || ''} ${item.blood_group || ''} ${item.location || ''} ${item.first_name || ''} ${item.last_name || ''}`.toLowerCase().includes(query));
        }

        feed.innerHTML = '';
        if (!items.length) {
            feed.innerHTML = '<div class="card text-center text-muted">No active emergency requests.</div>';
            return;
        }

        items.forEach(item => {
            const meta = this.badge(item.urgency_level);
            const card = document.createElement('div');
            card.className = 'card';
            card.style.borderLeft = `5px solid ${meta.color}`;
            card.innerHTML = `
                <div class="card-header">
                    <div>
                        <h3 class="card-title">${htmlEscape(item.patient_name || 'Unnamed Patient')} needs ${htmlEscape(item.blood_group)}</h3>
                        <p class="page-subtitle">${htmlEscape(item.location || '-')} | ${item.quantity} unit(s) | ${htmlEscape(item.first_name || '')} ${htmlEscape(item.last_name || '')}</p>
                    </div>
                    <span class="${meta.cls}"><i class="fas fa-clock"></i> ${meta.label}</span>
                </div>
                <div class="card-body">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; margin-bottom:1rem;">
                        <div><strong>Contact</strong><div class="text-muted">${htmlEscape(item.contact_phone || item.phone || '-')}</div></div>
                        <div><strong>Deadline</strong><div class="text-danger countdown" data-expires="${htmlEscape(item.expires_at || '')}">${this.timeLeft(item.expires_at)}</div></div>
                        <div><strong>Posted</strong><div class="text-muted">${htmlEscape(item.created_at || '-')}</div></div>
                        <div><strong>Status</strong><div class="text-muted">${htmlEscape(item.status)}</div></div>
                    </div>
                    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                        <button class="btn btn-success" onclick="Emergency.fulfill(${item.id})"><i class="fas fa-check"></i> Mark as Fulfilled</button>
                        <a class="btn btn-secondary" href="tel:${htmlEscape(item.contact_phone || item.phone || '')}"><i class="fas fa-phone"></i> Call Contact</a>
                    </div>
                </div>
            `;
            feed.appendChild(card);
        });
    },

    refreshCountdowns() {
        document.querySelectorAll('.countdown').forEach(el => {
            el.textContent = this.timeLeft(el.dataset.expires);
        });
    },

    async create() {
        const body = {
            patient_name: document.getElementById('emergencyPatientName').value.trim(),
            contact_phone: document.getElementById('emergencyContactPhone').value.trim(),
            blood_group: document.getElementById('emergencyBloodGroup').value,
            quantity: parseInt(document.getElementById('emergencyQuantity').value || '0', 10),
            urgency_level: document.getElementById('emergencyUrgencyLevel').value,
            location: document.getElementById('emergencyLocation').value.trim(),
            patient_details: document.getElementById('emergencyPatientDetails').value.trim()
        };

        const response = await fetch(`${this.apiBase}?route=emergency/create`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await response.json();
        if (data.ok) {
            Toast.success(`Emergency posted. Donors notified: ${data.notified || 0}`);
            document.getElementById('emergencyForm').reset();
            this.closeModal();
            this.loadFeed();
        } else {
            Toast.error(data.error || 'Emergency request failed.');
        }
    },

    async fulfill(id) {
        if (!confirm('Mark this emergency request as fulfilled?')) return;
        const response = await fetch(`${this.apiBase}?route=${encodeURIComponent(`emergency/${id}/fulfill`)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: '{}'
        });
        const data = await response.json();
        if (data.ok) {
            Toast.success('Emergency marked as fulfilled.');
            this.loadFeed();
        } else {
            Toast.error(data.error || 'Could not fulfill request.');
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    Emergency.loadFeed();
    setInterval(() => Emergency.loadFeed(), 60000);
    setInterval(() => Emergency.refreshCountdowns(), 30000);
});
</script>

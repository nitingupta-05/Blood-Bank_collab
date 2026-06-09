<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Blood Inventory</h1>
        <p class="page-subtitle">Manage and track blood units</p>
    </div>
    <button class="btn btn-primary" onclick="openAddBloodModal()">
        <i class="fas fa-plus"></i> Add Blood Unit
    </button>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div class="form-group">
                <label class="form-label">Blood Group</label>
                    <select id="invFilterBloodGroup" class="form-control">
                        <option value="">All Blood Groups</option>
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
                <label class="form-label">Status</label>
                    <select id="invFilterStatus" class="form-control">
                        <option value="">All Status</option>
                        <option value="available">Available</option>
                        <option value="reserved">Reserved</option>
                        <option value="used">Used</option>
                        <option value="expired">Expired</option>
                        <option value="discarded">Discarded</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Storage Location</label>
                    <select id="invFilterStorageLocation" class="form-control">
                        <option value="">All Locations</option>
                        <option value="Fridge A - Rack 1">Fridge A - Rack 1</option>
                        <option value="Fridge A - Rack 2">Fridge A - Rack 2</option>
                        <option value="Fridge B - Rack 1">Fridge B - Rack 1</option>
                        <option value="Fridge B - Rack 2">Fridge B - Rack 2</option>
                        <option value="Fridge C - Rack 1">Fridge C - Rack 1</option>
                        <option value="Fridge C - Rack 2">Fridge C - Rack 2</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">&nbsp;</label>
                    <button class="btn btn-primary w-100" type="button" onclick="Inventory.load()">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Blood Stock Summary -->
<div class="dashboard-grid" style="margin-bottom: 2rem;" id="bloodStockSummary">
    <div style="text-align: center; color: var(--text-light); grid-column: 1 / -1;">Loading blood stock...</div>
</div>

<!-- Inventory Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Blood Units</h3>
        <div style="display: flex; gap: 0.5rem;">
            <button class="btn btn-sm btn-secondary">
                <i class="fas fa-download"></i> Export
            </button>
            <button class="btn btn-sm btn-secondary">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Barcode</th>
                        <th>Blood Group</th>
                        <th>Collection Date</th>
                        <th>Expiry Date</th>
                        <th>Storage Location</th>
                        <th>Donor</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="inventoryTbody">
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-light);">Loading inventory...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

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
            <button class="btn btn-primary" type="button" onclick="Inventory.add()">Add Blood Unit</button>
        </div>
    </div>
</div>

<script>
    function openAddBloodModal() {
        document.getElementById('addBloodModal').classList.add('active');
    }

    function closeAddBloodModal() {
        document.getElementById('addBloodModal').classList.remove('active');
    }

    const Inventory = {
        apiBase: `<?php echo APP_URL; ?>/api.php`,

        statusBadge(status) {
            switch (status) {
                case 'available': return 'badge badge-success';
                case 'reserved': return 'badge badge-warning';
                case 'used': return 'badge badge-info';
                case 'expired': return 'badge badge-danger';
                case 'discarded': return 'badge badge-danger';
                default: return 'badge badge-primary';
            }
        },

        async load() {
            const bg = document.getElementById('invFilterBloodGroup').value;
            const status = document.getElementById('invFilterStatus').value;
            const loc = document.getElementById('invFilterStorageLocation').value;

            const params = new URLSearchParams();
            params.set('endpoint', 'blood-units/list');
            if (bg) params.set('blood_group', bg);
            if (status) params.set('status', status);
            if (loc) params.set('storage_location', loc);

            const url = `${this.apiBase}?${params.toString()}`;
            try {
                const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (r.status === 401) {
                    Toast.warning('Please login to manage inventory.');
                    window.location = '?route=login';
                    return;
                }
                const data = await r.json();
                const tbody = document.getElementById('inventoryTbody');
                tbody.innerHTML = '';

                const items = data.items || [];
                items.forEach(it => {
                    const donor = it.first_name || it.last_name
                        ? `${it.first_name || ''} ${it.last_name || ''}`.trim()
                        : '-';

                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><code>${htmlEscape(it.barcode || '')}</code></td>
                        <td><span class="blood-group o-positive">${htmlEscape(it.blood_group)}</span></td>
                        <td>${htmlEscape(it.collection_date ? DateUtil.formatDate(it.collection_date, 'd M Y') : '-')}</td>
                        <td>${htmlEscape(it.expiry_date ? DateUtil.formatDate(it.expiry_date, 'd M Y') : '-')}</td>
                        <td>${htmlEscape(it.storage_location || '-')}</td>
                        <td>${htmlEscape(donor)}</td>
                        <td><span class="${this.statusBadge(it.status)}"><i class="fas fa-tint"></i> ${htmlEscape(it.status)}</span></td>
                        <td>
                            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                                <button class="btn btn-sm btn-success" title="Mark Used" onclick="Inventory.setStatus(${it.id}, 'used')">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" title="Delete" onclick="Inventory.del(${it.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(row);
                });

                if (items.length === 0) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td colspan="8" class="text-muted" style="text-align:center;">No blood units found for the selected filters.</td>`;
                    tbody.appendChild(tr);
                }
            } catch (e) {
                console.error(e);
                Toast.error('Failed to load inventory.');
            }
        },

        async add() {
            const bloodGroup = document.getElementById('bloodGroup').value;
            const collectionDate = document.getElementById('collectionDate').value;
            const expiryDate = document.getElementById('expiryDate').value;
            const storageLocation = document.getElementById('storageLocation').value;

            if (!bloodGroup) return Toast.warning('Select a blood group.');
            if (!collectionDate) return Toast.warning('Collection date is required.');
            if (!expiryDate) return Toast.warning('Expiry date is required.');
            if (!storageLocation) return Toast.warning('Storage location is required.');

            try {
                const r = await fetch(`${this.apiBase}?route=blood-units/add`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        blood_group: bloodGroup,
                        collection_date: collectionDate,
                        expiry_date: expiryDate,
                        storage_location: storageLocation
                    })
                });
                const data = await r.json();
                if (!data.ok) throw new Error(data.error || 'Add failed');

                Toast.success('Blood unit added.');
                closeAddBloodModal();
                this.load();
            } catch (e) {
                console.error(e);
                Toast.error(e.message || 'Failed to add.');
            }
        },

        async setStatus(id, status) {
            try {
                const r = await fetch(`${this.apiBase}?route=blood-units/update-status`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ id, status })
                });
                const data = await r.json();
                if (!data.ok) throw new Error(data.error || 'Update failed');
                Toast.success('Status updated.');
                this.load();
            } catch (e) {
                console.error(e);
                Toast.error(e.message || 'Failed to update status.');
            }
        },

        async del(id) {
            if (!confirm('Delete this blood unit record?')) return;
            try {
                const r = await fetch(`${this.apiBase}?route=blood-units/delete`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ id })
                });
                const data = await r.json();
                if (!data.ok) throw new Error('Delete failed');
                Toast.success('Deleted.');
                this.load();
            } catch (e) {
                console.error(e);
                Toast.error(e.message || 'Failed to delete.');
            }
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        Inventory.load();
        
        // Load blood stock summary
        async function loadBloodStockSummary() {
            try {
                const res = await fetch(`${Inventory.apiBase}?route=reports/blood-stock`);
                const data = await res.json();
                if (data.ok && data.labels && data.available) {
                    const container = document.getElementById('bloodStockSummary');
                    const items = data.labels.map((label, idx) => ({
                        blood_group: label,
                        available: data.available[idx]
                    }));
                    container.innerHTML = items.map(item => `
                        <div class="stat-card">
                            <div style="text-align: center;">
                                <div class="blood-group o-positive" style="width: 60px; height: 60px; font-size: 1.5rem; margin: 0 auto 1rem;">${htmlEscape(item.blood_group)}</div>
                                <div class="stat-card-value">${item.available || 0}</div>
                                <div class="stat-card-label">Units Available</div>
                            </div>
                        </div>
                    `).join('');
                }
            } catch (e) {
                console.error(e);
            }
        }
        
        loadBloodStockSummary();
    });
</script>

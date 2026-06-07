<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Donor Management</h1>
        <p class="page-subtitle">Manage and track donors</p>
    </div>
    <button class="btn btn-primary" onclick="openAddDonorModal()">
        <i class="fas fa-plus"></i> Add Donor
    </button>
</div>

<!-- Donor Stats -->
<div class="dashboard-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="donor-total">0</div>
                <div class="stat-card-label">Total Donors</div>
            </div>
            <div class="stat-card-icon success">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="donor-eligible">0</div>
                <div class="stat-card-label">Eligible Donors</div>
            </div>
            <div class="stat-card-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="donor-ineligible">0</div>
                <div class="stat-card-label">Ineligible Donors</div>
            </div>
            <div class="stat-card-icon warning">
                <i class="fas fa-exclamation-circle"></i>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="donor-donations">0</div>
                <div class="stat-card-label">Total Donations</div>
            </div>
            <div class="stat-card-icon primary">
                <i class="fas fa-vial"></i>
            </div>
        </div>
    </div>
</div>

<!-- Donors Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Donors List</h3>
        <button class="btn btn-sm btn-secondary">
            <i class="fas fa-download"></i> Export
        </button>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Blood Group</th>
                        <th>Age</th>
                        <th>Phone</th>
                        <th>Last Donation</th>
                        <th>Total Donations</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="donorsTbody">
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-light);">Loading donors...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Donor Modal -->
<div class="modal" id="addDonorModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Donor</h2>
            <button class="modal-close" onclick="closeAddDonorModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="addDonorForm">
                <div class="form-group">
                    <label class="form-label">User ID *</label>
                    <input type="number" id="donorUserId" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Blood Group *</label>
                    <select id="donorBloodGroup" class="form-control" required>
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
                    <label class="form-label">Age *</label>
                    <input type="number" id="donorAge" class="form-control" min="18" max="65" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Weight (kg) *</label>
                    <input type="number" id="donorWeight" class="form-control" step="0.1" min="50" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Medical History</label>
                    <textarea id="donorMedicalHistory" class="form-control" rows="3"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeAddDonorModal()">Cancel</button>
            <button class="btn btn-primary" type="button" onclick="submitAddDonorForm()">Add Donor</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const api = `<?php echo APP_URL; ?>/api.php`;
    try {
        const s = await (await fetch(`${api}?route=donors/stats`)).json();
        if (s.ok) {
            document.getElementById('donor-total').textContent = s.stats.total;
            document.getElementById('donor-eligible').textContent = s.stats.eligible;
            document.getElementById('donor-ineligible').textContent = s.stats.ineligible;
            document.getElementById('donor-donations').textContent = s.stats.total_donations;
        }
        const l = await (await fetch(`${api}?route=donors/list`)).json();
        const tb = document.getElementById('donorsTbody');
        tb.innerHTML = '';
        (l.items || []).forEach(d => {
            const tr = document.createElement('tr');
            const badge = d.eligibility_status === 'eligible' ? 'badge-success' : 'badge-warning';
            tr.innerHTML = `<td>${d.first_name} ${d.last_name}</td><td><span class="blood-group o-positive">${d.blood_group}</span></td>
                <td>${d.age || '-'}</td><td>${d.phone || '-'}</td>
                <td>${d.last_donation_date || '-'}</td><td>${d.total_donations}</td>
                <td><span class="badge ${badge}">${d.eligibility_status}</span></td>
                <td>${d.city || ''}</td>`;
            tb.appendChild(tr);
        });
        if (!(l.items || []).length) tb.innerHTML = '<tr><td colspan="8" class="text-muted text-center">No donors yet.</td></tr>';
    } catch (e) { console.error(e); Toast.error('Failed to load donors.'); }
});
</script>

<script>
function submitAddDonorForm() {
    const formData = {
        user_id: document.getElementById('donorUserId').value,
        blood_group: document.getElementById('donorBloodGroup').value,
        age: parseInt(document.getElementById('donorAge').value),
        weight: parseFloat(document.getElementById('donorWeight').value),
        medical_history: document.getElementById('donorMedicalHistory').value
    };
    
    if (!formData.user_id || !formData.blood_group || !formData.age || !formData.weight) {
        Toast.error('Please fill all required fields');
        return;
    }
    
    DonorManager.add(formData).then(result => {
        if (result) {
            document.getElementById('addDonorForm').reset();
        }
    });
}
</script>

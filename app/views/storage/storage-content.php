<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Storage Management</h1>
        <p class="page-subtitle">Monitor and manage blood storage</p>
    </div>
    <button class="btn btn-primary" onclick="openAddStorageModal()">
        <i class="fas fa-plus"></i> Add Storage Area
    </button>
</div>

<!-- Storage Stats -->
<div class="dashboard-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="st-fridges">0</div>
                <div class="stat-card-label">Total Fridges</div>
            </div>
            <div class="stat-card-icon primary">
                <i class="fas fa-snowflake"></i>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="st-capacity">0</div>
                <div class="stat-card-label">Total Capacity</div>
            </div>
            <div class="stat-card-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="st-stored">0</div>
                <div class="stat-card-label">Currently Stored</div>
            </div>
            <div class="stat-card-icon info">
                <i class="fas fa-chart-pie"></i>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="st-temp">-</div>
                <div class="stat-card-label">Avg Temperature</div>
            </div>
            <div class="stat-card-icon success">
                <i class="fas fa-thermometer-half"></i>
            </div>
        </div>
    </div>
</div>

<!-- Storage Areas -->
<div id="storageAreasGrid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div style="text-align: center; color: var(--text-light); grid-column: 1 / -1;">Loading storage areas...</div>
</div>

<!-- Temperature History Chart -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Temperature History</h3>
    </div>
    <div class="card-body">
        <canvas id="temperatureChart"></canvas>
    </div>
</div>

<!-- Add Storage Modal -->
<div class="modal" id="addStorageModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Storage Area</h2>
            <button class="modal-close" onclick="closeAddStorageModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="addStorageForm">
                <div class="form-group">
                    <label class="form-label">Blood Bank ID *</label>
                    <input type="number" id="storageBankId" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Storage Name *</label>
                    <input type="text" id="storageName" class="form-control" placeholder="e.g., Fridge A" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Capacity (units) *</label>
                    <input type="number" id="storageCapacity" class="form-control" min="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Current Temperature (°C)</label>
                    <input type="number" id="storageTemp" class="form-control" step="0.1" placeholder="e.g., 4.0">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeAddStorageModal()">Cancel</button>
            <button class="btn btn-primary" type="button" onclick="submitAddStorageForm()">Add Storage</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const api = `<?php echo APP_URL; ?>/api.php`;
    try {
        const s = await (await fetch(`${api}?route=storage/stats`)).json();
        if (s.ok) {
            document.getElementById('st-fridges').textContent = s.stats.fridges;
            document.getElementById('st-capacity').textContent = s.stats.capacity;
            document.getElementById('st-stored').textContent = s.stats.stored;
            document.getElementById('st-temp').textContent = (s.stats.avg_temperature ?? '-') + (s.stats.avg_temperature ? '°C' : '');
        }
        const l = await (await fetch(`${api}?route=storage/list`)).json();
        const grid = document.getElementById('storageAreasGrid');
        if (l.ok && (l.items || []).length) {
            grid.innerHTML = '';
            l.items.forEach(sa => {
                const pct = sa.capacity > 0 ? Math.round((sa.current_occupancy / sa.capacity) * 100) : 0;
                const alert = sa.current_temperature > 6 || sa.current_temperature < 2;
                const card = document.createElement('div');
                card.className = 'card';
                card.innerHTML = `<div class="card-header"><h3 class="card-title">${sa.name}</h3>
                    <span class="badge ${alert ? 'badge-warning' : 'badge-success'}">${alert ? 'Alert' : 'Normal'}</span></div>
                    <div class="card-body"><p><strong>Bank:</strong> ${sa.blood_bank_name}</p>
                    <p><strong>Occupancy:</strong> ${sa.current_occupancy} / ${sa.capacity} (${pct}%)</p>
                    <p><strong>Temp:</strong> ${sa.current_temperature ?? '-'}°C</p></div>`;
                grid.appendChild(card);
            });
        }
    } catch (e) { console.error(e); }
});

// Temperature Chart - will be populated with real data
let tempChart = null;

function initTemperatureChart() {
    const tempCanvas = document.getElementById('temperatureChart');
    if (!tempCanvas) return;
    
    const tempCtx = tempCanvas.getContext('2d');
    if (tempChart) tempChart.destroy();
    
    tempChart = new Chart(tempCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: []
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    min: 0,
                    max: 10,
                    title: {
                        display: true,
                        text: 'Temperature (°C)'
                    }
                }
            }
        }
    });
}

initTemperatureChart();
</script>

<script>
function submitAddStorageForm() {
    const formData = {
        blood_bank_id: document.getElementById('storageBankId').value,
        name: document.getElementById('storageName').value,
        capacity: parseInt(document.getElementById('storageCapacity').value),
        temperature: parseFloat(document.getElementById('storageTemp').value) || null
    };
    
    if (!formData.blood_bank_id || !formData.name || !formData.capacity) {
        Toast.error('Please fill all required fields');
        return;
    }
    
    StorageManager.add(formData).then(result => {
        if (result) {
            document.getElementById('addStorageForm').reset();
        }
    });
}
</script>

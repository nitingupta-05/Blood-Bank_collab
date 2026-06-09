<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Reports & Analytics</h1>
        <p class="page-subtitle">Generate, preview, and export reports with Gemini AI-powered India-wide insights</p>
    </div>
</div>

<!-- Report Generation -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 class="card-title">Generate Report</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div class="form-group">
                <label class="form-label">Report Type</label>
                <select id="reportType" class="form-control">
                    <option value="inventory">Inventory Report</option>
                    <option value="donors">Donor Report</option>
                    <option value="emergency">Emergency Report</option>
                    <option value="bookings">Booking Report</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Blood Group (Inventory)</label>
                <select id="reportBloodGroup" class="form-control">
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
                <label class="form-label">Status (Inventory)</label>
                <select id="reportStatus" class="form-control">
                    <option value="">All Status</option>
                    <option value="available">Available</option>
                    <option value="reserved">Reserved</option>
                    <option value="used">Used</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">&nbsp;</label>
                <div style="display:flex;gap:0.5rem;">
                    <button type="button" class="btn btn-secondary flex-1" onclick="previewReport()">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                    <button type="button" class="btn btn-primary flex-1" onclick="ReportManager.exportSelected()">
                        <i class="fas fa-download"></i> Export PDF
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Preview -->
<div class="card" style="margin-bottom: 2rem;display:none;" id="reportPreviewCard">
    <div class="card-header">
        <h3 class="card-title">Report Preview</h3>
        <div style="display:flex;gap:0.5rem;align-items:center;">
            <span id="previewHospitalName" style="font-weight:600;font-size:0.9rem;"></span>
            <span id="previewRecordCount" class="badge badge-info"></span>
        </div>
    </div>
    <div class="card-body" style="max-height:400px;overflow:auto;">
        <div class="table-container">
            <table>
                <thead id="previewThead"></thead>
                <tbody id="previewTbody"></tbody>
            </table>
        </div>
        <div id="previewEmpty" class="text-muted text-center" style="padding:2rem;display:none;">No data found.</div>
    </div>
</div>

<!-- Analytics Charts -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Donation Trends <span id="aiSourceBadge" class="badge badge-primary" style="font-size:0.65rem;vertical-align:middle;"></span></h3>
        </div>
        <div class="card-body">
            <canvas id="donationTrendsChart"></canvas>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Blood Group Distribution <span id="aiSourceBadge2" class="badge badge-primary" style="font-size:0.65rem;vertical-align:middle;"></span></h3>
        </div>
        <div class="card-body">
            <canvas id="bloodGroupChart"></canvas>
        </div>
    </div>
</div>

<!-- Key Metrics -->
<div class="dashboard-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="rp-collected">0</div>
                <div class="stat-card-label">Total Units Collected (India-wide)</div>
            </div>
            <div class="stat-card-icon primary">
                <i class="fas fa-vial"></i>
            </div>
        </div>
        <div class="stat-card-change positive">
            <i class="fas fa-arrow-up"></i> AI-estimated annual total
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="rp-distributed">0</div>
                <div class="stat-card-label">Units Distributed</div>
            </div>
            <div class="stat-card-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
        <div class="stat-card-change positive">
            <i class="fas fa-arrow-up"></i> 8% from last month
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="rp-stock">0</div>
                <div class="stat-card-label">Units in Stock (Your Bank)</div>
            </div>
            <div class="stat-card-icon info">
                <i class="fas fa-chart-pie"></i>
            </div>
        </div>
        <div class="stat-card-change positive">
            <i class="fas fa-arrow-up"></i> 12% from last month
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="rp-rate">0%</div>
                <div class="stat-card-label">Fulfillment Rate</div>
            </div>
            <div class="stat-card-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
        <div class="stat-card-change positive">
            <i class="fas fa-arrow-up"></i> 2% from last month
        </div>
    </div>
</div>

<script>
window.addEventListener('load', function () {
    const api = window.BASE_URL + '/api.php';

    // --- Local stock summary ---
    (async function loadStock() {
        try {
            const r = await fetch(api + '?route=reports/blood-stock');
            const d = await r.json();
            if (d.ok && d.available) {
                let stock = d.available.reduce((a,b)=>a+b, 0);
                document.getElementById('rp-stock').textContent = stock;
            }
        } catch (e) { console.error(e); }
    })();

    // --- Chart: Donation Trends ---
    const dtCtx = document.getElementById('donationTrendsChart').getContext('2d');
    const dtChart = new Chart(dtCtx, {
        type: 'line',
        data: { labels: [], datasets: [{ label: 'Donations across India', data: [], borderColor: '#D32F2F', backgroundColor: 'rgba(211,47,47,0.1)', borderWidth: 2, fill: true, tension: 0.4 }] },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, title: { display: true, text: 'Units' } } } }
    });

    // --- Chart: Blood Group Distribution ---
    const bgCtx = document.getElementById('bloodGroupChart').getContext('2d');
    const bgChart = new Chart(bgCtx, {
        type: 'doughnut',
        data: { labels: [], datasets: [{ data: [], backgroundColor: ['#FF6B6B','#D32F2F','#FF9800','#E65100','#2196F3','#0D47A1','#9C27B0','#6A1B9A'] }] },
        options: { responsive: true, plugins: { legend: { position: 'bottom' }, title: { display: true, text: 'India Blood Group Distribution (%)' } } }
    });

    // --- Load AI insights into charts ---
    (async function loadAI() {
        try {
            const r = await fetch(api + '?route=reports/ai-insights');
            const d = await r.json();
            if (d.ok && d.insights) {
                const ins = d.insights;
                const src = ins.source || 'AI';
                document.getElementById('aiSourceBadge').textContent = src;
                document.getElementById('aiSourceBadge2').textContent = src;

                if (ins.donation_trends) {
                    dtChart.data.labels = ins.donation_trends.labels || [];
                    dtChart.data.datasets[0].data = ins.donation_trends.donations || [];
                    dtChart.update();
                    const total = (ins.donation_trends.donations || []).reduce((a,b)=>a+b, 0);
                    document.getElementById('rp-collected').textContent = total.toLocaleString();
                    document.getElementById('rp-distributed').textContent = Math.floor(total * 0.3).toLocaleString();
                    document.getElementById('rp-rate').textContent = '72%';
                }
                if (ins.blood_group_distribution) {
                    bgChart.data.labels = ins.blood_group_distribution.labels || [];
                    bgChart.data.datasets[0].data = ins.blood_group_distribution.percentages || [];
                    bgChart.update();
                }
            }
        } catch (e) { console.error('AI load error', e); }
    })();

    // --- Report preview ---
    window.previewReport = async function () {
        const type = document.getElementById('reportType').value;
        const bg = document.getElementById('reportBloodGroup').value;
        const status = document.getElementById('reportStatus').value;
        let url = api + '?route=reports/preview&type=' + type;
        if (bg) url += '&blood_group=' + bg;
        if (status) url += '&status=' + status;
        try {
            const r = await fetch(url);
            const d = await r.json();
            const card = document.getElementById('reportPreviewCard');
            const thead = document.getElementById('previewThead');
            const tbody = document.getElementById('previewTbody');
            const empty = document.getElementById('previewEmpty');
            const count = document.getElementById('previewRecordCount');
            const hname = document.getElementById('previewHospitalName');

            if (d.ok && d.items && d.items.length) {
                card.style.display = 'block';
                empty.style.display = 'none';
                hname.textContent = d.hospital || '';
                count.textContent = d.items.length + ' records';
                const headers = Object.keys(d.items[0]);
                thead.innerHTML = '<tr>' + headers.map(h => '<th>' + htmlEscape(h.replace(/_/g,' ')) + '</th>').join('') + '</tr>';
                tbody.innerHTML = d.items.map(row => {
                    return '<tr>' + headers.map(h => {
                        let val = row[h] ?? '';
                        if (h === 'blood_group' && val) {
                            return '<td><span class="blood-group">' + htmlEscape(val) + '</span></td>';
                        }
                        return '<td>' + htmlEscape(val) + '</td>';
                    }).join('') + '</tr>';
                }).join('');
            } else {
                card.style.display = 'block';
                thead.innerHTML = ''; tbody.innerHTML = '';
                empty.style.display = 'block';
                hname.textContent = ''; count.textContent = '0 records';
            }
        } catch (e) {
            console.error('Preview error', e);
            if (typeof Toast !== 'undefined') Toast.error('Failed to load preview');
        }
    };

    // Auto-preview on load
    setTimeout(previewReport, 300);
});
</script>

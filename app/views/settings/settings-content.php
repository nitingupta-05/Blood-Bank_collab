<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Manage your hospital settings</p>
    </div>
</div>

<!-- Hospital Details -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 class="card-title">Hospital Details</h3>
    </div>
    <div class="card-body">
        <form id="hospitalDetailsForm">
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" id="settingsEmail" class="form-control" readonly>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">First Name</label>
                    <input type="text" id="settingsFirstName" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text" id="settingsLastName" class="form-control" readonly>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Phone *</label>
                <input type="tel" id="settingsPhone" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Address *</label>
                <input type="text" id="settingsAddress" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">City *</label>
                <input type="text" id="settingsCity" class="form-control" required>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="button" class="btn btn-primary" onclick="SettingsManager.updateHospitalDetails({phone: document.getElementById('settingsPhone').value, address: document.getElementById('settingsAddress').value, city: document.getElementById('settingsCity').value})">
                    <i class="fas fa-save"></i> Save Details
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Notification Settings -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 class="card-title">Notification Preferences</h3>
    </div>
    <div class="card-body">
        <form id="notificationSettingsForm">
            <div class="form-group">
                <label class="form-checkbox">
                    <input type="checkbox" id="notifEmail" class="form-checkbox-input">
                    <span class="form-checkbox-label">Email Notifications</span>
                </label>
                <p style="font-size: 0.85rem; color: var(--text-light); margin-top: 0.5rem;">Receive important updates via email</p>
            </div>
            
            <div class="form-group">
                <label class="form-checkbox">
                    <input type="checkbox" id="notifSms" class="form-checkbox-input">
                    <span class="form-checkbox-label">SMS Notifications</span>
                </label>
                <p style="font-size: 0.85rem; color: var(--text-light); margin-top: 0.5rem;">Receive urgent alerts via SMS</p>
            </div>
            
            <div class="form-group">
                <label class="form-checkbox">
                    <input type="checkbox" id="notifInApp" class="form-checkbox-input" checked>
                    <span class="form-checkbox-label">In-App Notifications</span>
                </label>
                <p style="font-size: 0.85rem; color: var(--text-light); margin-top: 0.5rem;">Receive notifications within the application</p>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="button" class="btn btn-primary" onclick="SettingsManager.updateNotificationSettings({email_notifications: document.getElementById('notifEmail').checked ? 1 : 0, sms_notifications: document.getElementById('notifSms').checked ? 1 : 0, in_app_notifications: document.getElementById('notifInApp').checked ? 1 : 0})">
                    <i class="fas fa-save"></i> Save Preferences
                </button>
            </div>
        </form>
    </div>
</div>

<!-- System Statistics -->
<div class="dashboard-grid">
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="sysStatUnits">0</div>
                <div class="stat-card-label">Total Blood Units</div>
            </div>
            <div class="stat-card-icon primary">
                <i class="fas fa-vial"></i>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="sysStatDonors">0</div>
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
                <div class="stat-card-value" id="sysStatHospitals">0</div>
                <div class="stat-card-label">Hospitals</div>
            </div>
            <div class="stat-card-icon info">
                <i class="fas fa-hospital"></i>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value" id="sysStatEmergencies">0</div>
                <div class="stat-card-label">Active Emergencies</div>
            </div>
            <div class="stat-card-icon warning">
                <i class="fas fa-exclamation-circle"></i>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    // Load hospital details
    const hospital = await SettingsManager.getHospitalDetails();
    if (hospital) {
        document.getElementById('settingsEmail').value = hospital.email || '';
        document.getElementById('settingsFirstName').value = hospital.first_name || '';
        document.getElementById('settingsLastName').value = hospital.last_name || '';
        document.getElementById('settingsPhone').value = hospital.phone || '';
        document.getElementById('settingsAddress').value = hospital.address || '';
        document.getElementById('settingsCity').value = hospital.city || '';
    }

    // Load notification settings
    const notifSettings = await SettingsManager.getNotificationSettings();
    if (notifSettings) {
        document.getElementById('notifEmail').checked = notifSettings.email_notifications || false;
        document.getElementById('notifSms').checked = notifSettings.sms_notifications || false;
        document.getElementById('notifInApp').checked = notifSettings.in_app_notifications !== false;
    }

    // Load system stats
    const stats = await SettingsManager.getSystemStats();
    if (stats) {
        document.getElementById('sysStatUnits').textContent = stats.total_units;
        document.getElementById('sysStatDonors').textContent = stats.total_donors;
        document.getElementById('sysStatHospitals').textContent = stats.total_hospitals;
        document.getElementById('sysStatEmergencies').textContent = stats.active_emergencies;
    }
});
</script>

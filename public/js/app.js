/* eslint-env browser */
/* global BloodGroup, Toast, Modal, Inventory, Donors, StorageManager */
class Toast {
    static show(message, type = 'info', duration = 3000) {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <i class="fas fa-${this.getIcon(type)}"></i>
            <span>${message}</span>
        `;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), duration);
    }
    static getIcon(type) {
        return {'success':'check-circle','error':'exclamation-circle','warning':'exclamation-triangle','info':'info-circle'}[type]||'info-circle';
    }
    static success(m) { this.show(m, 'success'); }
    static error(m) { this.show(m, 'error'); }
    static warning(m) { this.show(m, 'warning'); }
    static info(m) { this.show(m, 'info'); }
}

class Modal {
    static open(id) { const m = document.getElementById(id); if (m) m.classList.add('active'); }
    static close(id) { const m = document.getElementById(id); if (m) m.classList.remove('active'); }
    static closeAll() { document.querySelectorAll('.modal.active').forEach(m => m.classList.remove('active')); }
}

class Counter {
    static animate(el, target, duration = 1000) {
        if (!el) return;
        const inc = target / (duration / 16);
        let cur = 0;
        const t = setInterval(() => {
            cur += inc;
            if (cur >= target) { el.textContent = target; clearInterval(t); }
            else { el.textContent = Math.floor(cur); }
        }, 16);
    }
}

class FormValidator {
    static validateEmail(e) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e); }
    static validatePhone(p) { return /^[0-9+\-\s()]{10,}$/.test(p); }
    static validatePassword(p) { return p.length >= 8; }
    static validateForm(id) {
        const form = document.getElementById(id);
        if (!form) return false;
        let valid = true;
        form.querySelectorAll('[required]').forEach(i => {
            if (!i.value.trim()) { this.showError(i, 'Required'); valid = false; }
            else this.clearError(i);
        });
        return valid;
    }
    static showError(i, m) { i.classList.add('error'); const d = document.createElement('div'); d.className='form-error'; d.textContent=m; i.parentElement.appendChild(d); }
    static clearError(i) { i.classList.remove('error'); const d = i.parentElement.querySelector('.form-error'); if (d) d.remove(); }
}

class API {
    static baseUrl() {
        return window.BASE_URL || (document.body.getAttribute('data-base-url') || '') ;
    }
    static url(route, params) {
        const u = new URL(this.baseUrl() + '/api.php', window.location.origin);
        u.searchParams.set('route', route);
        if (params) {
            for (const [k, v] of Object.entries(params)) {
                if (v !== undefined && v !== null && v !== '') u.searchParams.set(k, v);
            }
        }
        return u.toString();
    }
    static csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }
    static async request(route, method = 'GET', data = null, params = null) {
        try {
            const options = { method, headers: { 'Accept': 'application/json' } };
            if (data !== null && data !== undefined) {
                options.body = JSON.stringify(data);
                options.headers['Content-Type'] = 'application/json';
            }
            if (['POST', 'PUT', 'DELETE'].includes(method)) {
                options.headers['X-CSRF-TOKEN'] = this.csrfToken();
            }
            const response = await fetch(this.url(route, params), options);
            const json = await response.json().catch(() => ({ ok: false, error: 'Bad JSON response' }));
            if (!response.ok && !json.ok) {
                Toast.error(json.error || `HTTP ${response.status}`);
                throw new Error(json.error || `HTTP ${response.status}`);
            }
            return json;
        } catch (error) {
            console.error('API Error:', error);
            if (!String(error.message || '').match(/^HTTP/)) Toast.error('An error occurred. Please try again.');
            throw error;
        }
    }
    static get(route, params)        { return this.request(route, 'GET',    null, params); }
    static post(route, data, params) { return this.request(route, 'POST',   data, params); }
    static put(route, data, params)  { return this.request(route, 'PUT',    data, params); }
    static delete(route, params)     { return this.request(route, 'DELETE', null, params); }
}

class BloodGroup {
    static getColor(g) { return {'O+':'#FF6B6B','O-':'#D32F2F','A+':'#FF9800','A-':'#E65100','B+':'#2196F3','B-':'#0D47A1','AB+':'#9C27B0','AB-':'#6A1B9A'}[g]||'#999'; }
    static getCompatible(g) { return {'O+':['O+','A+','B+','AB+'],'O-':['O+','O-','A+','A-','B+','B-','AB+','AB-'],'A+':['A+','AB+'],'A-':['A+','A-','AB+','AB-'],'B+':['B+','AB+'],'B-':['B+','B-','AB+','AB-'],'AB+':['AB+'],'AB-':['AB+','AB-']}[g]||[]; }
    static canDonate(d, r) { return this.getCompatible(r).includes(d); }
}

class LocationUtil {
    static calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dLon/2)**2;
        return (R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a))).toFixed(2);
    }
    static getCurrentLocation() {
        return new Promise((resolve, reject) => {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(p => resolve({latitude: p.coords.latitude, longitude: p.coords.longitude}), reject);
            } else reject(new Error('Geolocation not supported'));
        });
    }
}

class DateUtil {
    static formatDate(d) { return new Date(d).toLocaleDateString('en-US', {year:'numeric',month:'short',day:'numeric'}); }
    static formatDateTime(d) { return new Date(d).toLocaleDateString('en-US', {year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'}); }
    static getTimeAgo(d) {
        const s = Math.floor((new Date() - new Date(d)) / 1000);
        if (s < 60) return 'just now';
        if (s < 3600) return Math.floor(s / 60) + 'm ago';
        if (s < 86400) return Math.floor(s / 3600) + 'h ago';
        if (s < 604800) return Math.floor(s / 86400) + 'd ago';
        return this.formatDate(d);
    }
    static isExpiring(d, days = 7) { const e = new Date(d); const w = new Date(); w.setDate(w.getDate()+days); return e <= w && e > new Date(); }
    static isExpired(d) { return new Date(d) < new Date(); }
}

class Storage {
    static set(k, v) { localStorage.setItem(k, JSON.stringify(v)); }
    static get(k) { const i = localStorage.getItem(k); return i ? JSON.parse(i) : null; }
    static remove(k) { localStorage.removeItem(k); }
    static clear() { localStorage.clear(); }
}

/* ============================================
 *  MODULE-LEVEL INIT
 * ============================================ */
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.modal').forEach(m => {
        m.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('active'); });
    });
    document.querySelectorAll('.alert-close').forEach(b => {
        b.addEventListener('click', function() { this.parentElement.style.display = 'none'; });
    });
    const currentRoute = new URLSearchParams(window.location.search).get('route');
    if (currentRoute) {
        document.querySelectorAll('.sidebar-link').forEach(l => {
            l.classList.remove('active');
            if (l.href.includes(`route=${currentRoute}`)) l.classList.add('active');
        });
    }

    /* Global search */
    const searchInput = document.getElementById('globalSearchInput');
    const searchResults = document.getElementById('globalSearchResults');
    let searchTimer = null;
    if (searchInput && searchResults) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            const q = searchInput.value.trim();
            if (q.length < 2) { searchResults.classList.remove('active'); searchResults.innerHTML = ''; return; }
            searchTimer = setTimeout(async () => {
                try {
                    const data = await API.get('search/global', { q });
                    const items = data.items || [];
                    searchResults.innerHTML = items.length ? items.map(i => `
                        <a class="global-search-item" href="${i.url}">
                            <div class="global-search-type">${i.type}</div>
                            <div style="font-weight:700;">${i.title}</div>
                            <div class="text-muted" style="font-size:0.85rem;">${i.subtitle || ''}</div>
                        </a>
                    `).join('') : '<div class="global-search-item text-muted">No matching records.</div>';
                    searchResults.classList.add('active');
                } catch (e) { /* swallow */ }
            }, 250);
        });
    }

    /* Notifications dropdown */
    const nt = document.getElementById('notificationTrigger');
    const np = document.getElementById('notificationPanel');
    const nb = document.getElementById('notificationBadge');
    async function loadNotifications() {
        if (!np || !nb) return;
        try {
            const [c, l] = await Promise.all([
                API.get('notifications/count'),
                API.get('notifications/list', { limit: 8 })
            ]);
            nb.textContent = c.ok ? c.count : 0;
            const notifs = l.notifications || [];
            np.innerHTML = notifs.length ? notifs.map(n => `
                <div class="notification-item">
                    <div style="font-weight:700;">${String(n.type).replace('_', ' ')}</div>
                    <div style="font-size:0.9rem;">${n.message}</div>
                    <div class="text-muted" style="font-size:0.75rem;">${n.channel} | ${n.delivery_status} | ${n.created_at}</div>
                </div>
            `).join('') : '<div class="notification-item text-muted">No notifications yet.</div>';
        } catch (e) { /* swallow */ }
    }
    if (nt && np) {
        nt.addEventListener('click', (e) => { e.stopPropagation(); np.classList.toggle('active'); loadNotifications(); });
        loadNotifications();
        setInterval(loadNotifications, 30000);
    }

    /* Dark mode toggle */
    const dt = document.getElementById('darkModeToggle');
    if (localStorage.getItem('bloodBankTheme') === 'dark') document.body.classList.add('dark-mode');
    if (dt) {
        dt.addEventListener('click', (e) => {
            e.stopPropagation();
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('bloodBankTheme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
        });
    }

    /* Profile dropdown */
    const pt = document.getElementById('profileTrigger');
    const pp = document.getElementById('profilePanel');
    async function loadProfile() {
        if (!pp) return;
        try {
            const data = await API.get('profile/current');
            if (!data.ok) return;
            const u = data.user || {};
            const b = data.blood_bank || {};
            pp.innerHTML = `
                <div style="font-weight:800;margin-bottom:0.5rem;font-size:1.1rem;">${b.name || `${u.first_name||''} ${u.last_name||''}`}</div>
                <div class="profile-row"><span>Role</span><strong>${(u.role||'').replace('_',' ')}</strong></div>
                <div class="profile-row"><span>Email</span><strong>${u.email||'-'}</strong></div>
                <div class="profile-row"><span>Phone</span><strong>${b.phone||u.phone||'-'}</strong></div>
                <div class="profile-row"><span>City</span><strong>${b.city||u.city||'-'}</strong></div>
                <div class="profile-row"><span>Address</span><strong>${b.address||u.address||'-'}</strong></div>
                ${b.contact_person ? `<div class="profile-row"><span>Contact</span><strong>${b.contact_person}</strong></div>` : ''}
                ${b.capacity ? `<div class="profile-row"><span>Capacity</span><strong>${b.capacity} units</strong></div>` : ''}
            `;
        } catch (e) { /* swallow */ }
    }
    if (pt && pp) {
        pt.addEventListener('click', (e) => { e.stopPropagation(); loadProfile(); pp.classList.toggle('active'); });
    }

    document.addEventListener('click', () => {
        searchResults?.classList.remove('active');
        np?.classList.remove('active');
        pp?.classList.remove('active');
    });

    /* Modal submit listeners (the inventory and donors pages
     * submit via plain form submit; the dashboard uses the legacy
     * submitAddBloodForm() helper).  Wire them to BloodUnitManager
     * / DonorManager / StorageManager with a small client-side
     * intercept so the AJAX call flows through API.  This keeps
     * progressive enhancement: non-JS browsers still submit forms. */
    const addBloodForm = document.getElementById('addBloodForm');
    if (addBloodForm) {
        addBloodForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const res = await BloodUnitManager.add({
                blood_group: document.getElementById('bloodGroup')?.value || '',
                collection_date: document.getElementById('collectionDate')?.value || '',
                expiry_date: document.getElementById('expiryDate')?.value || '',
                storage_location: document.getElementById('storageLocation')?.value || '',
                donor_id: document.getElementById('donorId')?.value || null,
            });
            if (res && window.Inventory?.load) window.Inventory.load();
        });
    }
    const addDonorForm = document.getElementById('addDonorForm');
    if (addDonorForm) {
        addDonorForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const res = await DonorManager.add({
                user_id: document.getElementById('donorUserId')?.value || '',
                blood_group: document.getElementById('donorBloodGroup')?.value || '',
                age: parseInt(document.getElementById('donorAge')?.value || 0),
                weight: parseFloat(document.getElementById('donorWeight')?.value || 0),
                medical_history: document.getElementById('donorMedicalHistory')?.value || ''
            });
            if (res && window.Donors?.load) window.Donors.load();
        });
    }
    const addStorageForm = document.getElementById('addStorageForm');
    if (addStorageForm) {
        addStorageForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            await StorageManager.add({
                name: document.getElementById('storageName')?.value || '',
                capacity: parseInt(document.getElementById('storageCapacity')?.value || 0),
                temperature: parseFloat(document.getElementById('storageTemp')?.value || 0)
            });
        });
    }
});

/* ============================================
 *  DOMAIN HELPERS
 * ============================================ */
class BloodUnitManager {
    static async add(formData) {
        try {
            const result = await API.post('blood-units/add', formData);
            if (result.ok) {
                Toast.success('Blood unit added successfully');
                Modal.close('addBloodModal');
                if (window.Inventory?.load) window.Inventory.load();
                return result;
            }
            return null;
        } catch (e) { return null; }
    }
    static async list(filters = {}) {
        try {
            const result = await API.get('blood-units/list', filters);
            return result.ok ? result.items : [];
        } catch (e) { return []; }
    }
    static async updateStatus(id, status) {
        try {
            const result = await API.post('blood-units/update-status', { id, status });
            if (result.ok) { Toast.success('Status updated'); return true; }
            return false;
        } catch (e) { return false; }
    }
    static async delete(id) {
        try {
            const result = await API.post('blood-units/delete', { id });
            if (result.ok) { Toast.success('Deleted'); return true; }
            return false;
        } catch (e) { return false; }
    }
}

class DonorManager {
    static async add(formData) {
        try {
            const result = await API.post('donors/add', formData);
            if (result.ok) {
                Toast.success('Donor added successfully');
                Modal.close('addDonorModal');
                if (window.Donors?.load) window.Donors.load();
                return result;
            }
            return null;
        } catch (e) { return null; }
    }
    static async list() {
        try { const r = await API.get('donors/list'); return r.ok ? r.items : []; }
        catch (e) { return []; }
    }
    static async getStats() {
        try { const r = await API.get('donors/stats'); return r.ok ? r.stats : null; }
        catch (e) { return null; }
    }
}

class StorageManager {
    static async add(formData) {
        try {
            const result = await API.post('storage/add', formData);
            if (result.ok) {
                Toast.success('Storage area added');
                Modal.close('addStorageModal');
                if (window.StoragePage?.load) window.StoragePage.load();
                return result;
            }
            return null;
        } catch (e) { return null; }
    }
    static async list() {
        try { const r = await API.get('storage/list'); return r.ok ? r.items : []; }
        catch (e) { return []; }
    }
    static async logTemperature(storageId, temperature) {
        try { const r = await API.post('storage/log-temperature', { storage_id: storageId, temperature }); return !!r.ok; }
        catch (e) { return false; }
    }
}

class ReportManager {
    static exportSelected() {
        const type   = document.getElementById('reportType')?.value || 'inventory';
        const bg     = document.getElementById('reportBloodGroup')?.value || '';
        const status = document.getElementById('reportStatus')?.value || '';
        window.location.href = API.url('reports/export-csv', { type, blood_group: bg, status });
        Toast.success('Downloading…');
    }
    static async getBloodStockSummary() {
        try { const r = await API.get('reports/blood-stock'); return r.ok ? r.data : []; }
        catch (e) { return []; }
    }
}

class SettingsManager {
    static async getHospitalDetails() {
        try { const r = await API.get('settings/hospital'); return r.ok ? r.hospital : null; }
        catch (e) { return null; }
    }
    static async updateHospitalDetails(formData) {
        try {
            const r = await API.post('settings/hospital', formData);
            if (r.ok) { Toast.success('Updated'); return true; }
            return false;
        } catch (e) { return false; }
    }
    static async getNotificationSettings() {
        try { const r = await API.get('settings/notifications'); return r.ok ? r.settings : {}; }
        catch (e) { return {}; }
    }
    static async updateNotificationSettings(formData) {
        try {
            const r = await API.post('settings/notifications', formData);
            if (r.ok) { Toast.success('Settings updated'); return true; }
            return false;
        } catch (e) { return false; }
    }
    static async getSystemStats() {
        try { const r = await API.get('settings/system-stats'); return r.ok ? r.stats : null; }
        catch (e) { return null; }
    }
}

class NotificationManager {
    static async getNotifications(limit = 50) {
        try { const r = await API.get('notifications/list', { limit }); return r.ok ? r.notifications : []; }
        catch (e) { return []; }
    }
    static async markAsRead(id) {
        try { const r = await API.post('notifications/mark-read', { notification_id: id }); return !!r.ok; }
        catch (e) { return false; }
    }
    static async send(userId, type, message, channel = 'in_app') {
        try { const r = await API.post('notifications/send', { user_id: userId, type, message, channel }); return r.ok ? r.id : null; }
        catch (e) { return null; }
    }
}

/* Legacy global open/close helpers (used by the dashboard's
 * on-page onclick handlers). */
function openAddBloodModal()   { Modal.open('addBloodModal'); }
function closeAddBloodModal()  { Modal.close('addBloodModal'); }
function openAddDonorModal()   { Modal.open('addDonorModal'); }
function closeAddDonorModal()  { Modal.close('addDonorModal'); }
function openAddStorageModal() { Modal.open('addStorageModal'); }
function closeAddStorageModal(){ Modal.close('addStorageModal'); }

/* Expose to global scope for inline view scripts. */
window.Toast             = Toast;
window.Modal             = Modal;
window.Counter           = Counter;
window.FormValidator     = FormValidator;
window.API               = API;
window.BloodGroup        = BloodGroup;
window.LocationUtil      = LocationUtil;
window.DateUtil          = DateUtil;
window.Storage           = Storage;
window.BloodUnitManager  = BloodUnitManager;
window.DonorManager      = DonorManager;
window.StorageManager    = StorageManager;
window.ReportManager     = ReportManager;
window.SettingsManager   = SettingsManager;
window.NotificationManager = NotificationManager;

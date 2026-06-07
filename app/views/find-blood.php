<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Blood - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar">
    <div class="navbar-brand">
        <i class="fas fa-droplet"></i>
        <span><?php echo APP_NAME; ?></span>
    </div>
    <div class="navbar-menu">
        <a class="navbar-item" href="?route=home"><i class="fas fa-house"></i> <span>Home</span></a>
        <a class="navbar-item" href="?route=find-blood"><i class="fas fa-search"></i> <span>Find Blood</span></a>
        <a class="navbar-item" href="?route=register"><i class="fas fa-user-plus"></i> <span>Become Donor</span></a>
        <a class="navbar-item" href="?route=emergency-requests"><i class="fas fa-exclamation-circle"></i> <span>Emergency</span></a>
        <a class="navbar-item" href="?route=login"><i class="fas fa-sign-in-alt"></i> <span>Login</span></a>
    </div>
</nav>

<main class="main-content" style="margin-left:0; padding: 2rem 1rem;">
    <div class="card card-glass fade-in">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-search"></i> Blood Availability Search</h3>
            <div class="text-muted" style="font-size:0.9rem;">Live stock + matching donors</div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Blood Group</label>
                    <select id="fbBloodGroup" class="form-control" required>
                        <option value="">Select</option>
                        <?php foreach (BLOOD_GROUPS as $bg): ?>
                            <option value="<?php echo $bg; ?>"><?php echo $bg; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">City</label>
                    <input id="fbCity" class="form-control" placeholder="e.g., Mumbai" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Units Required (optional)</label>
                    <input id="fbUnitsRequired" type="number" min="1" class="form-control" placeholder="e.g., 2">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2 flex-wrap">
                <button class="btn btn-primary" onclick="BloodFinder.search()">
                    <i class="fas fa-search"></i> Search Availability
                </button>
                <button class="btn btn-secondary" onclick="BloodFinder.useMyLocation()">
                    <i class="fas fa-location-crosshairs"></i> Use My Location (optional)
                </button>
            </div>

            <div id="bloodSearchStatus" class="mt-3 text-muted" style="display:none;"></div>
        </div>
    </div>

    <div class="mt-4">
        <div class="card fade-in">
            <div class="card-header">
                <h3 class="card-title">Availability Results</h3>
                <div class="text-muted" style="font-size:0.9rem;">Sorted by nearest when location is enabled</div>
            </div>
            <div class="card-body">
                <div id="bloodSearchResults" style="display:flex; flex-direction:column; gap:1rem;"></div>
                <div id="bloodSearchEmpty" class="text-muted mt-2" style="display:none;">No matching banks found.</div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <div class="card fade-in">
            <div class="card-header">
                <h3 class="card-title">Suggested Matching Donors</h3>
            </div>
            <div class="card-body">
                <div id="donorSuggestions" style="display:flex; flex-direction:column; gap:1rem;"></div>
                <div id="donorEmpty" class="text-muted mt-2" style="display:none;">No eligible donors found for this blood group in the selected city.</div>
            </div>
        </div>
    </div>
</main>

<div id="toastContainer"></div>

<script src="<?php echo APP_URL; ?>/js/app.js"></script>
<script>
    const BloodFinder = {
        lat: null,
        lon: null,

        useMyLocation() {
            if (!navigator.geolocation) {
                Toast.info('Geolocation not supported by this browser.');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                pos => {
                    this.lat = pos.coords.latitude;
                    this.lon = pos.coords.longitude;
                    Toast.success('Location captured. Results will be ordered by distance.');
                },
                () => Toast.warning('Could not fetch your location. Showing city-only results.')
            );
        },

        search() {
            const bloodGroup = document.getElementById('fbBloodGroup').value;
            const city = document.getElementById('fbCity').value.trim();
            const unitsRequired = document.getElementById('fbUnitsRequired').value;

            if (!bloodGroup) return Toast.warning('Select a blood group.');
            if (!city) return Toast.warning('Enter a city.');

            const url = `<?php echo APP_URL; ?>/api.php?route=blood-search&blood_group=${encodeURIComponent(bloodGroup)}&city=${encodeURIComponent(city)}${unitsRequired ? '&units_required=' + encodeURIComponent(unitsRequired) : ''}${this.lat && this.lon ? '&latitude=' + this.lat + '&longitude=' + this.lon : ''}`;

            document.getElementById('bloodSearchStatus').style.display = 'block';
            document.getElementById('bloodSearchStatus').textContent = 'Searching...';

            fetch(url, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                document.getElementById('bloodSearchStatus').textContent = '';
                const results = document.getElementById('bloodSearchResults');
                const empty = document.getElementById('bloodSearchEmpty');
                results.innerHTML = '';

                const donors = document.getElementById('donorSuggestions');
                const donorEmpty = document.getElementById('donorEmpty');
                donors.innerHTML = '';

                const banks = data.banks || [];
                if (banks.length === 0) empty.style.display = 'block';
                else empty.style.display = 'none';

                banks.forEach(b => {
                    const availability = b.available_units >= 10 ? 'Available' : (b.available_units >= 1 ? 'Low Stock' : 'Out of Stock');
                    const badgeClass = b.available_units >= 10 ? 'badge-success' : (b.available_units >= 1 ? 'badge-warning' : 'badge-danger');
                    const distanceText = b.distance_km != null ? ` • ${b.distance_km} km away` : '';

                    const card = document.createElement('div');
                    card.className = 'card';
                    card.style.padding = '1rem';
                    card.innerHTML = `
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem;">
                            <div>
                                <div style="font-weight:700; color: var(--text-dark);">${b.name}</div>
                                <div class="text-muted" style="font-size:0.9rem;">${b.city} • ${b.address || ''}${distanceText}</div>
                                <div class="mt-2" style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap;">
                                    <div class="blood-group o-negative" style="width:42px; height:42px; background: linear-gradient(135deg, ${BloodGroup.getColor(bloodGroup)} 0%, rgba(0,0,0,0.1) 100%);">${bloodGroup}</div>
                                    <div>
                                        <div style="font-weight:700;">${b.available_units} Units</div>
                                        <div class="text-muted" style="font-size:0.85rem;">${availability}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="badge badge-${badgeClass.replace('badge-','')}">
                                <i class="fas fa-tint"></i> ${availability}
                            </div>
                        </div>
                        <div class="mt-3" style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                            <a class="btn btn-success btn-sm" href="tel:${b.phone || ''}">
                                <i class="fas fa-phone"></i> Contact
                            </a>
                        </div>
                    `;
                    results.appendChild(card);
                });

                const donorList = data.donors || [];
                if (donorList.length === 0) donorEmpty.style.display = 'block';
                else donorEmpty.style.display = 'none';

                donorList.forEach(d => {
                    const distanceText = d.distance_km != null ? ` • ${d.distance_km} km away` : '';
                    const item = document.createElement('div');
                    item.className = 'card';
                    item.style.padding = '1rem';
                    item.innerHTML = `
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:1rem;">
                            <div>
                                <div style="font-weight:700; color: var(--text-dark);">${d.first_name} ${d.last_name}</div>
                                <div class="text-muted" style="font-size:0.9rem;">${d.city}${distanceText}</div>
                            </div>
                            <div style="display:flex; gap:0.75rem; align-items:center;">
                                <div style="width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:900; background:${BloodGroup.getColor(d.blood_group)}; color:#fff;">
                                    ${d.blood_group}
                                </div>
                                <a class="btn btn-secondary btn-sm" href="tel:${d.phone}">
                                    <i class="fas fa-phone"></i> Call
                                </a>
                            </div>
                        </div>
                    `;
                    donors.appendChild(item);
                });
            })
            .catch(() => {
                document.getElementById('bloodSearchStatus').textContent = '';
                Toast.error('Search failed. Please try again.');
            });
        }
    };

    // Expose to window for inline handlers
    window.BloodFinder = BloodFinder;
    const p = new URLSearchParams(location.search);
    if (p.get('blood_group')) document.getElementById('fbBloodGroup').value = p.get('blood_group');
    if (p.get('city')) document.getElementById('fbCity').value = p.get('city');
    if (p.get('blood_group') && p.get('city')) BloodFinder.search();
</script>
</body>
</html>


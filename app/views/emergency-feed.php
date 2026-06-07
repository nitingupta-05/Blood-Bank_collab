<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Requests - <?php echo APP_NAME; ?></title>
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
    <div class="card fade-in">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-exclamation-circle"></i> Live Emergency Feed</h3>
            <div class="text-muted" style="font-size:0.9rem;">Public view of active requests</div>
        </div>
        <div class="card-body">
            <div id="emergencyFeed" style="display:flex; flex-direction:column; gap:1.25rem;"></div>
            <div id="emergencyEmpty" class="text-muted mt-2" style="display:none;">No active emergencies right now.</div>
        </div>
    </div>
</main>

<div id="toastContainer"></div>

<script src="<?php echo APP_URL; ?>/js/app.js"></script>
<script>
    function renderEmergency(items) {
        const feed = document.getElementById('emergencyFeed');
        const empty = document.getElementById('emergencyEmpty');
        feed.innerHTML = '';
        if (!items || items.length === 0) {
            empty.style.display = 'block';
            return;
        }
        empty.style.display = 'none';

        const urgencyBadge = (u) => {
            if (u === 'critical') return { cls: 'badge-danger', text: 'CRITICAL' };
            if (u === 'high') return { cls: 'badge-warning', text: 'HIGH' };
            return { cls: 'badge-info', text: 'MEDIUM' };
        };

        items.forEach(er => {
            const b = urgencyBadge(er.urgency_level);
            const created = er.created_at ? new Date(er.created_at) : null;
            const timeText = created ? DateUtil.getTimeAgo(created.toISOString()) : '';
            const card = document.createElement('div');
            card.className = 'card';
            card.style.padding = '1rem';

            card.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem;">
                    <div>
                        <div style="font-weight:700; color: var(--text-dark);">
                            ${er.first_name || ''} ${er.last_name || ''} - ${b.text}
                        </div>
                        <div class="text-muted" style="font-size:0.9rem;">
                            Posted ${timeText || 'just now'} • Hospital city: ${er.city || ''}
                        </div>
                    </div>
                    <span class="badge ${b.cls}">
                        <i class="fas fa-exclamation"></i> ${b.text}
                    </span>
                </div>

                <div class="mt-3" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
                    <div>
                        <div class="text-muted" style="font-size:0.85rem; margin-bottom:0.25rem;">Blood Needed</div>
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div style="width:44px; height:44px; border-radius:10px; background: rgba(211,47,47,0.08); display:flex; align-items:center; justify-content:center; font-weight:800;">
                                ${er.blood_group}
                            </div>
                            <div><div style="font-weight:700;">${er.blood_group}</div></div>
                        </div>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.85rem; margin-bottom:0.25rem;">Quantity</div>
                        <div style="font-weight:800; font-size:1.25rem;">${er.quantity} Units</div>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.85rem; margin-bottom:0.25rem;">Location</div>
                        <div style="font-weight:700;">${er.location || ''}</div>
                    </div>
                </div>

                <div class="mt-3" style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                    <a class="btn btn-secondary btn-sm" href="tel:${er.phone || ''}">
                        <i class="fas fa-phone"></i> Call Hospital
                    </a>
                    <button class="btn btn-primary btn-sm" onclick="Toast.info('Emergency response workflow is admin-side in this MVP.');">
                        <i class="fas fa-bell"></i> Notify Donors
                    </button>
                </div>
            `;
            feed.appendChild(card);
        });
    }

    function loadFeed() {
        const url = `<?php echo APP_URL; ?>/api.php?route=emergency/feed`;
        fetch(url, { method: 'GET', headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                renderEmergency(data.items || []);
            })
            .catch(() => Toast.error('Failed to load emergency feed.'));
    }

    loadFeed();
    // Basic live refresh
    setInterval(loadFeed, 60000);
</script>
</body>
</html>


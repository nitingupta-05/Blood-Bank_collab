<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>/css/style.css" rel="stylesheet">
    <style>
        body { margin: 0; }
        .public-main { margin-left: 0; margin-top: 0; padding: 0; min-height: auto; }
        .hero-banner {
            background: linear-gradient(135deg, #D32F2F 0%, #B71C1C 50%, #8B0000 100%);
            color: #fff; padding: 5rem 1.5rem 4rem; text-align: center; position: relative; overflow: hidden;
        }
        .hero-banner::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.08) 0%, transparent 50%);
            animation: pulse 4s ease-in-out infinite;
        }
        .hero-banner .container { position: relative; z-index: 1; }
        .live-stat { background: rgba(255,255,255,0.15); backdrop-filter: blur(8px); border-radius: 12px; padding: 1.25rem; text-align: center; }
        .live-stat .num { font-size: 2rem; font-weight: 800; }
        .blood-card { border-radius: 12px; padding: 1rem; text-align: center; color: #fff; font-weight: 700; transition: transform 0.2s; }
        .blood-card:hover { transform: scale(1.05); }
        .emergency-mini { border-left: 4px solid var(--danger); padding: 1rem; background: #fff; border-radius: 8px; margin-bottom: 0.75rem; box-shadow: var(--shadow-sm); }
        .public-nav { position: sticky; top: 0; z-index: 100; }
        .public-nav .nav-link { color: rgba(255,255,255,0.9) !important; }
    </style>
</head>
<body>
<nav class="navbar public-nav">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="?route=home"><i class="fas fa-droplet"></i> RedPulse</a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto gap-1">
                <li class="nav-item"><a class="nav-link" href="?route=home">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="?route=find-blood">Find Blood</a></li>
                <li class="nav-item"><a class="nav-link" href="?route=register">Become Donor</a></li>
                <li class="nav-item"><a class="nav-link" href="?route=register-blood-bank">Register Blood Bank</a></li>
                <li class="nav-item"><a class="nav-link" href="?route=book-blood">Book Blood</a></li>
                <li class="nav-item"><a class="nav-link" href="?route=emergency-requests">Emergency</a></li>
                <li class="nav-item"><a class="nav-link" href="?route=login"><i class="fas fa-sign-in-alt"></i> Login</a></li>
            </ul>
        </div>
    </div>
</nav>

<section class="hero-banner">
    <div class="container">
        <h1 class="fade-in"><i class="fas fa-heart-pulse"></i> Smart Blood Bank Command Center</h1>
        <p class="mb-4">Live inventory • Emergency donor network • Hospital workflows</p>
        <div class="d-flex gap-2 justify-content-center flex-wrap mb-5">
            <a href="?route=find-blood" class="btn btn-light btn-lg"><i class="fas fa-search"></i> Find Blood</a>
            <a href="?route=register" class="btn btn-outline-light btn-lg"><i class="fas fa-user-plus"></i> Become Donor</a>
            <a href="?route=register-blood-bank" class="btn btn-outline-light btn-lg"><i class="fas fa-hospital"></i> Register Blood Bank</a>
            <a href="?route=emergency-request" class="btn btn-danger btn-lg"><i class="fas fa-exclamation-circle"></i> Emergency</a>
        </div>
        <div class="row g-3 justify-content-center">
            <div class="col-6 col-md-3"><div class="live-stat"><div class="num" id="hs-donors">0</div><div>Total Donors</div></div></div>
            <div class="col-6 col-md-3"><div class="live-stat"><div class="num" id="hs-units">0</div><div>Blood Units</div></div></div>
            <div class="col-6 col-md-3"><div class="live-stat"><div class="num" id="hs-banks">0</div><div>Blood Banks</div></div></div>
            <div class="col-6 col-md-3"><div class="live-stat"><div class="num" id="hs-emerg">0</div><div>Emergencies Today</div></div></div>
        </div>
    </div>
</section>

<main class="public-main" style="padding: 3rem 1.5rem; background: var(--bg-light);">
    <div class="container">
        <!-- Quick Search -->
        <div class="card card-glass mb-5 fade-in">
            <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-search"></i> Quick Blood Search</h3></div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Blood Group</label>
                        <select id="homeBg" class="form-control">
                            <option value="">Select</option>
                            <?php foreach (BLOOD_GROUPS as $g): ?><option value="<?php echo $g; ?>"><?php echo $g; ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">City</label>
                        <input id="homeCity" class="form-control" placeholder="e.g. Mumbai" value="Mumbai">
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-primary w-100" onclick="location.href='?route=find-blood&blood_group='+encodeURIComponent(document.getElementById('homeBg').value)+'&city='+encodeURIComponent(document.getElementById('homeCity').value)">
                            <i class="fas fa-search"></i> Search Now
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <!-- Blood Groups -->
            <div class="col-lg-7">
                <h3 class="mb-3" style="color: var(--primary);">Blood Availability</h3>
                <div class="row g-2" id="bloodGroupCards"></div>
            </div>
            <!-- Emergency Feed -->
            <div class="col-lg-5">
                <h3 class="mb-3" style="color: var(--danger);"><i class="fas fa-exclamation-circle pulse"></i> Live Emergencies</h3>
                <div id="homeEmergencyFeed"></div>
                <a href="?route=emergency-requests" class="btn btn-outline btn-sm mt-2">View all →</a>
            </div>
        </div>

        <!-- How it works -->
        <h3 class="text-center mb-4" style="color: var(--primary);">How It Works</h3>
        <div class="row g-3 mb-5 text-center">
            <?php
            $steps = [
                ['fa-user-plus', 'Register Donor', 'Sign up and check eligibility'],
                ['fa-vial', 'Donate Blood', 'Blood collected and tested'],
                ['fa-snowflake', 'Stored Safely', 'Temperature-controlled storage'],
                ['fa-hospital', 'Patient Request', 'Hospitals search & book'],
                ['fa-bell', 'Emergency Match', 'Urgent alerts to nearby donors'],
            ];
            foreach ($steps as $s): ?>
            <div class="col-md col-6">
                <div class="card p-3 h-100"><i class="fas <?php echo $s[0]; ?> fa-2x mb-2" style="color:var(--primary)"></i>
                <strong><?php echo $s[1]; ?></strong><p class="text-muted small mb-0"><?php echo $s[2]; ?></p></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Testimonials -->
        <h3 class="text-center mb-4" style="color: var(--primary);">Trusted By Healthcare</h3>
        <div class="row g-3">
            <div class="col-md-4"><div class="card p-3"><p class="mb-2">"Found O- units in minutes during surgery."</p><small class="text-muted">— City Hospital</small></div></div>
            <div class="col-md-4"><div class="card p-3"><p class="mb-2">"Donor network responded to our emergency within hours."</p><small class="text-muted">— Dr. Adebayo</small></div></div>
            <div class="col-md-4"><div class="card p-3"><p class="mb-2">"Inventory tracking reduced wastage significantly."</p><small class="text-muted">— Central Blood Bank</small></div></div>
        </div>
    </div>
</main>

<footer style="background:#212121;color:#fff;padding:2.5rem 1.5rem;margin-top:2rem;">
    <div class="container text-center">
        <p class="mb-1"><i class="fas fa-phone"></i> Emergency Hotline: <strong>199</strong></p>
        <p class="text-muted small mb-0">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?></p>
    </div>
</footer>

<div id="toastContainer"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo APP_URL; ?>/js/app.js"></script>
<script>
const API = `<?php echo APP_URL; ?>/api.php`;
const BG_COLORS = { 'O+':'#FF6B6B','O-':'#D32F2F','A+':'#FF9800','A-':'#E65100','B+':'#2196F3','B-':'#0D47A1','AB+':'#9C27B0','AB-':'#6A1B9A' };

async function loadHome() {
    try {
        const st = await (await fetch(`${API}?route=public/stats`)).json();
        if (st.ok) {
            ['donors','units','banks','emerg'].forEach((k,i) => {
                const el = document.getElementById(['hs-donors','hs-units','hs-banks','hs-emerg'][i]);
                if (el) Counter.animate(el, st.stats[k] || 0, 800);
            });
        }
        const stock = await (await fetch(`${API}?route=public/blood-stock`)).json();
        const grid = document.getElementById('bloodGroupCards');
        const labels = <?php echo json_encode(BLOOD_GROUPS); ?>;
        const avail = stock && stock.ok ? stock.available : labels.map(() => 0);
        labels.forEach((g, i) => {
            const n = avail[i] || 0;
            const status = n >= 10 ? 'Available' : (n >= 1 ? 'Low' : 'Out');
            const div = document.createElement('div');
            div.className = 'col-6 col-md-3';
            div.innerHTML = `<div class="blood-card" style="background:${BG_COLORS[g]||'#999'}">
                <div style="font-size:1.25rem">${g}</div><div>${n} units</div><small>${status}</small></div>`;
            grid.appendChild(div);
        });
        const em = await (await fetch(`${API}?route=emergency/feed`)).json();
        const feed = document.getElementById('homeEmergencyFeed');
        feed.innerHTML = '';
        (em.items || []).slice(0, 3).forEach(er => {
            feed.innerHTML += `<div class="emergency-mini">
                <strong>${er.blood_group}</strong> — ${er.quantity} units needed
                <div class="text-muted small">${er.location || er.city || ''} • ${er.urgency_level}</div>
            </div>`;
        });
        if (!(em.items||[]).length) feed.innerHTML = '<p class="text-muted">No active emergencies.</p>';
    } catch (e) { console.error(e); }
}
loadHome();
setInterval(loadHome, 30000);
</script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Request - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar"><div class="navbar-brand"><i class="fas fa-exclamation-circle"></i><span>Emergency Request</span></div>
<div class="navbar-menu"><a class="navbar-item" href="?route=home">Home</a><a class="navbar-item" href="?route=login">Login</a></div></nav>
<main style="margin-left:0;padding:2rem 1rem;max-width:600px;margin:0 auto;">
<div class="card" style="border-left:4px solid var(--danger);">
    <div class="card-header"><h3 class="card-title text-danger"><i class="fas fa-exclamation-circle pulse-glow"></i> Post Emergency</h3></div>
    <div class="card-body">
        <form id="emForm">
            <div class="form-group"><label class="form-label">Patient Name *</label>
                <input name="patient_name" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Contact Phone *</label>
                <input name="contact_phone" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Blood Group *</label>
                <select name="blood_group" class="form-control" required>
                    <?php foreach (BLOOD_GROUPS as $g): ?><option><?php echo $g; ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-group"><label class="form-label">Quantity *</label>
                <input type="number" name="quantity" class="form-control" min="1" value="2" required></div>
            <div class="form-group"><label class="form-label">Urgency *</label>
                <select name="urgency_level" class="form-control">
                    <option value="critical">Critical - 24 hr</option><option value="moderate" selected>Moderate - 36 hr</option><option value="low">Low - 48 hr</option>
                </select></div>
            <div class="form-group"><label class="form-label">City / Location *</label>
                <input name="location" class="form-control" placeholder="Mumbai" required></div>
            <div class="form-group"><label class="form-label">Patient Details</label>
                <textarea name="patient_details" class="form-control" rows="2"></textarea></div>
            <button class="btn btn-danger w-100"><i class="fas fa-bell"></i> Post Emergency</button>
        </form>
        <p class="text-muted small mt-3">Hospitals should <a href="?route=login">login</a> for full workflow.</p>
    </div>
</div>
</main>
<div id="toastContainer"></div>
<script src="<?php echo APP_URL; ?>/js/app.js"></script>
<script>
document.getElementById('emForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const body = Object.fromEntries(fd.entries());
    body.quantity = parseInt(body.quantity, 10);
    const r = await fetch(`<?php echo APP_URL; ?>/api.php?route=public/emergency`, {
        method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(body)
    });
    const d = await r.json();
    if (d.ok) { Toast.success('Emergency posted! Donors notified: ' + (d.notified||0)); location.href='?route=emergency-requests'; }
    else Toast.error(d.error || 'Login as hospital to post.');
});
</script>
</body>
</html>

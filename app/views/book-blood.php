<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Blood - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar"><div class="navbar-brand"><i class="fas fa-droplet"></i><span>Book Blood</span></div>
<div class="navbar-menu">
    <a class="navbar-item" href="?route=home">Home</a>
    <a class="navbar-item" href="?route=login">Login (Hospital)</a>
</div></nav>
<main style="margin-left:0;padding:2rem 1rem;max-width:700px;margin:0 auto;">
<div class="card card-glass">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-calendar-check"></i> Request Blood Booking</h3></div>
    <div class="card-body">
        <p class="text-muted">Hospitals: login first, or submit as guest (pending approval).</p>
        <form id="bookForm">
            <div class="form-group"><label class="form-label">Blood Group *</label>
                <select name="blood_group" class="form-control" required>
                    <option value="">Select</option>
                    <?php foreach (BLOOD_GROUPS as $g): ?><option><?php echo $g; ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-group"><label class="form-label">Units *</label>
                <input type="number" name="quantity" class="form-control" min="1" required></div>
            <div class="form-group"><label class="form-label">Required Date *</label>
                <input type="date" name="required_date" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Patient Name</label>
                <input name="patient_name" class="form-control"></div>
            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-paper-plane"></i> Submit Booking</button>
        </form>
    </div>
</div>
</main>
<div id="toastContainer"></div>
<script src="<?php echo APP_URL; ?>/js/app.js"></script>
<script>
document.getElementById('bookForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const body = Object.fromEntries(fd.entries());
    body.quantity = parseInt(body.quantity, 10);
    const r = await fetch(`<?php echo APP_URL; ?>/api.php?route=public/booking`, {
        method: 'POST',
        headers: {
            'Content-Type':'application/json',
            'X-CSRF-TOKEN': '<?php echo generate_csrf_token(); ?>'
        },
        body: JSON.stringify(body)
    });
    const data = await r.json();
    if (data.ok) { Toast.success('Booking submitted! Reference #' + data.id); e.target.reset(); }
    else Toast.error(data.error || 'Login as hospital or contact admin.');
});
</script>
</body>
</html>

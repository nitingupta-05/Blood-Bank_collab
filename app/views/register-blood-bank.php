<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Blood Bank - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar">
    <div class="navbar-brand"><i class="fas fa-hospital"></i><span>Register Blood Bank</span></div>
    <div class="navbar-menu">
        <a class="navbar-item" href="?route=home">Home</a>
        <a class="navbar-item" href="?route=login">Login</a>
    </div>
</nav>

<main class="public-main" style="margin-left:0; padding:2rem 1rem; max-width:860px; margin:0 auto;">
    <div class="card card-glass">
        <div class="card-header">
            <div>
                <h3 class="card-title"><i class="fas fa-building-circle-check"></i> Blood Bank Portal Registration</h3>
                <p class="text-muted mb-0">Create a connected blood bank profile and admin login.</p>
            </div>
        </div>
        <div class="card-body">
            <form id="bloodBankRegisterForm">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Blood Bank Name *</label>
                        <input name="name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contact Person *</label>
                        <input name="contact_person" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email/Login *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone *</label>
                        <input name="phone" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">City *</label>
                        <input name="city" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Storage Capacity</label>
                        <input type="number" min="0" name="capacity" class="form-control" placeholder="Total units">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address *</label>
                        <textarea name="address" class="form-control" required></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" minlength="8" required>
                    </div>
                </div>
                <button class="btn btn-primary mt-4" type="submit"><i class="fas fa-paper-plane"></i> Register Blood Bank</button>
            </form>
        </div>
    </div>
</main>

<div id="toastContainer"></div>
<script src="<?php echo APP_URL; ?>/js/app.js"></script>
<script>
document.getElementById('bloodBankRegisterForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const body = Object.fromEntries(new FormData(event.target).entries());
    body.capacity = parseInt(body.capacity || '0', 10);

    const response = await fetch(`<?php echo APP_URL; ?>/api.php?route=blood-banks/register`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(body)
    });
    const data = await response.json();
    if (data.ok) {
        Toast.success('Blood bank registered. You can login now.');
        event.target.reset();
        setTimeout(() => { window.location = '?route=login'; }, 900);
    } else {
        Toast.error(data.error || 'Registration failed.');
    }
});
</script>
</body>
</html>

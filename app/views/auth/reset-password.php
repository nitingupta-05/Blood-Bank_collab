<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo APP_NAME; ?></title>
    <link href="<?php echo APP_URL; ?>/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg-light);">
<div class="card card-glass" style="max-width:420px;width:90%;padding:2rem;">
    <h2 style="color:var(--primary);margin-bottom:1rem;"><i class="fas fa-key"></i> Set New Password</h2>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <form method="POST" action="?route=reset-password">
        <input type="hidden" name="_csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
        <div class="form-group">
            <label class="form-label">New Password (min 8 characters)</label>
            <input type="password" name="password" class="form-control" required minlength="8">
        </div>
        <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" required minlength="8">
        </div>
        <button class="btn btn-primary w-100">Reset Password</button>
    </form>
    <p class="mt-3"><a href="?route=login">← Back to login</a></p>
</div>
</body>
</html>
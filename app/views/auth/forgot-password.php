<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
    <link href="<?php echo APP_URL; ?>/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg-light);">
<div class="card card-glass" style="max-width:420px;width:90%;padding:2rem;">
    <h2 style="color:var(--primary);margin-bottom:1rem;"><i class="fas fa-key"></i> Reset Password</h2>
    <p class="text-muted">Enter your email to receive a password reset link.</p>
    <form method="POST" action="?route=forgot-password">
        <?php echo csrf_token_field(); ?>
        <div class="form-group"><label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required></div>
        <button class="btn btn-primary w-100">Send Reset Link</button>
    </form>
    <p class="mt-3"><a href="?route=login">← Back to login</a></p>
</div>
</body>
</html>

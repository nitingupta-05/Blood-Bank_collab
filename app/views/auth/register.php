<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>
<div class="container" style="max-width: 980px; padding: 2rem 0;">
    <div class="card card-glass" style="padding: 1.5rem;">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
            <div>
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <i class="fas fa-droplet" style="font-size: 2rem; color: var(--primary);"></i>
                    <div>
                        <h2 style="margin:0;">Become a Donor</h2>
                        <div class="text-muted" style="font-size:0.95rem;">Quick, secure donor registration</div>
                    </div>
                </div>
            </div>
            <a class="btn btn-secondary" href="?route=login"><i class="fas fa-sign-in-alt"></i> Login</a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form id="donorRegisterForm" method="POST" action="?route=register">
            <?php echo csrf_token_field(); ?>
            <!-- Steps -->
            <div class="d-flex gap-2 flex-wrap mb-4">
                <button type="button" class="btn btn-sm btn-primary step-btn" data-step="1">1. Personal</button>
                <button type="button" class="btn btn-sm btn-secondary step-btn" data-step="2">2. Medical</button>
                <button type="button" class="btn btn-sm btn-secondary step-btn" data-step="3">3. Contact</button>
                <button type="button" class="btn btn-sm btn-secondary step-btn" data-step="4">4. Availability</button>
            </div>

            <!-- Step 1 -->
            <div class="step-panel" data-step="1">
                <h4 style="margin-bottom: 1.25rem;">Personal Details</h4>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full name *</label>
                        <input class="form-control" name="full_name" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Age *</label>
                        <input type="number" class="form-control" name="age" min="0" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Gender *</label>
                        <select class="form-control" name="gender" required>
                            <option value="">Select</option>
                            <option>Male</option>
                            <option>Female</option>
                            <option>Other</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="step-panel hidden" data-step="2">
                <h4 style="margin-bottom: 1.25rem;">Medical Details</h4>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Blood group *</label>
                        <select class="form-control" name="blood_group" required>
                            <option value="">Select</option>
                            <?php foreach (BLOOD_GROUPS as $bg): ?>
                                <option value="<?php echo $bg; ?>"><?php echo $bg; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Weight (kg) *</label>
                        <input type="number" step="0.1" class="form-control" name="weight" min="0" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Last donation date</label>
                        <input type="date" class="form-control" name="last_donation_date">
                    </div>
                </div>
                <div class="mt-3 text-muted" style="font-size:0.9rem;">
                    Eligibility is checked automatically during registration.
                </div>
            </div>

            <!-- Step 3 -->
            <div class="step-panel hidden" data-step="3">
                <h4 style="margin-bottom: 1.25rem;">Contact Information</h4>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Phone *</label>
                        <input class="form-control" name="phone" placeholder="+234..." required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">City *</label>
                        <input class="form-control" name="city" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Address *</label>
                        <input class="form-control" name="address" required>
                    </div>
                </div>
            </div>

            <!-- Step 4 -->
            <div class="step-panel hidden" data-step="4">
                <h4 style="margin-bottom: 1.25rem;">Availability & Account</h4>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Preferred blood bank *</label>
                        <select class="form-control" name="preferred_blood_bank_id" id="preferredBloodBank" required>
                            <option value="">Loading blood banks...</option>
                        </select>
                        <div class="text-muted" style="font-size:0.85rem;">Select where you prefer to donate.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password *</label>
                        <input type="password" class="form-control" name="password" required>
                        <div class="text-muted" style="font-size:0.85rem;">Minimum 8 characters with uppercase, lowercase, and a number.</div>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-user-plus"></i> Register Donor
                        </button>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="button" class="btn btn-secondary step-prev" disabled>Back</button>
                <button type="button" class="btn btn-primary step-next">Next</button>
                <div class="ms-auto text-muted" id="stepLabel" style="align-self:center;"></div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo APP_URL; ?>/js/app.js"></script>
<script>
    // Tiny stepper (no heavy dependencies).
    const form = document.getElementById('donorRegisterForm');
    const panels = Array.from(form.querySelectorAll('.step-panel'));
    const prevBtn = form.querySelector('.step-prev');
    const nextBtn = form.querySelector('.step-next');
    const stepLabel = document.getElementById('stepLabel');
    const stepBtns = Array.from(form.querySelectorAll('.step-btn'));

    let step = 1;

    function setStep(n) {
        step = n;
        panels.forEach(p => p.classList.toggle('hidden', Number(p.dataset.step) !== n));
        prevBtn.disabled = n === 1;
        nextBtn.style.display = n === 4 ? 'none' : 'inline-flex';
        stepLabel.textContent = `Step ${n} / 4`;
        stepBtns.forEach(b => b.classList.toggle('btn-primary', Number(b.dataset.step) === n));
        stepBtns.forEach(b => b.classList.toggle('btn-secondary', Number(b.dataset.step) !== n));
    }

    function validateStep(n) {
        const panel = panels.find(p => Number(p.dataset.step) === n);
        const requiredInputs = panel.querySelectorAll('[required]');
        let ok = true;
        requiredInputs.forEach(el => {
            if (!el.value || !String(el.value).trim()) ok = false;
        });
        return ok;
    }

    prevBtn.addEventListener('click', () => setStep(step - 1));
    nextBtn.addEventListener('click', () => {
        if (!validateStep(step)) {
            Toast.warning('Please fill required fields in this step.');
            return;
        }
        setStep(Math.min(4, step + 1));
    });

    stepBtns.forEach(b => b.addEventListener('click', () => setStep(Number(b.dataset.step))));
    setStep(1);

    async function loadBloodBanks() {
        const select = document.getElementById('preferredBloodBank');
        try {
            const response = await fetch(`<?php echo APP_URL; ?>/api.php?route=public/blood-banks`);
            const data = await response.json();
            const banks = data.items || [];
            select.innerHTML = '<option value="">Select blood bank</option>' + banks.map(bank => (
                `<option value="${bank.id}">${bank.name} - ${bank.city}</option>`
            )).join('');
        } catch (error) {
            select.innerHTML = '<option value="">Unable to load blood banks</option>';
        }
    }
    loadBloodBanks();
</script>
</body>
</html>


<?php
require_once __DIR__ . '/includes/auth.php';

$u = current_session();
if ($u) {
    header('Location: ' . base_url() . ($u['role'] === 'admin' ? '/admin/dashboard.php' : '/teacher/dashboard.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Session expired. Please try again.';
    } else {
        $result = attempt_login(is_string($_POST['username'] ?? null) ? $_POST['username'] : '', is_string($_POST['password'] ?? null) ? $_POST['password'] : '');
        if ($result['ok']) {
            $u = current_session();
            header('Location: ' . base_url() . ($u['role'] === 'admin' ? '/admin/dashboard.php' : '/teacher/dashboard.php'));
            exit;
        }
        $error = $result['message'] ?? 'Login failed.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — College Grading System</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="login-wrap">
  <div class="login-card">

    <!-- Left decorative panel -->
    <div class="login-side">
      <div>
        <div class="d-flex align-items-center gap-2 mb-2">
          <div class="brand-icon">🎓</div>
          <div>
            <p class="fw-bold mb-0" style="font-size:1.05rem;color:#fff;">College Grading</p>
            <p class="mb-0" style="font-size:.8rem;color:#c7d2fe;">System</p>
          </div>
        </div>
        <h2>Streamlined Lecture &amp; Lab Grade Computation for Teachers and Admins</h2>
        <p>Manage classes, input scores, customize grading weights, and generate transcripts with automatic PASSED / FAILED evaluation based on the official grading scale.</p>
      </div>
      <ul class="mt-3">
        <li><i class="bi bi-check-circle-fill me-1" style="color:#818cf8;"></i> Excel-style grade sheets with auto-computation</li>
        <li><i class="bi bi-check-circle-fill me-1" style="color:#818cf8;"></i> Customizable weight distribution (must total 100%)</li>
        <li><i class="bi bi-check-circle-fill me-1" style="color:#818cf8;"></i> Export to Excel &amp; PDF</li>
        <li><i class="bi bi-check-circle-fill me-1" style="color:#818cf8;"></i> Full audit trail for administrators</li>
      </ul>
    </div>

    <!-- Login form -->
    <div class="login-form-side">
      <div class="mobile-brand">
        <div class="brand-icon">🎓</div>
        <p class="fw-bold mb-0" style="color:#1e293b;">College Grading System</p>
      </div>

      <h1>Welcome back</h1>
      <p class="sub mb-4">Sign in to continue to your dashboard</p>

      <form method="post" action="index.php">
        <?= csrf_field() ?>

        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.875rem;color:#374151;">Username</label>
          <div class="input-icon-wrap">
            <span class="icon"><i class="bi bi-person"></i></span>
            <input type="text" name="username" class="form-control"
                   placeholder="Enter your username" autofocus required
                   value="<?= h($_POST['username'] ?? '') ?>">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.875rem;color:#374151;">Password</label>
          <div class="input-icon-wrap">
            <span class="icon"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" class="form-control"
                   placeholder="Enter your password" required>
          </div>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-danger py-2 px-3" style="font-size:.875rem;"><?= h($error) ?></div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary w-100 fw-semibold mt-1">
          <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
        </button>
      </form>

      <?php if (APP_ENV !== 'production' && setting('APP_DEMO_MODE') === '1'): ?>
      <hr class="my-4" style="border-color:#f1f5f9;">
      <p class="text-muted mb-2" style="font-size:.75rem;">Quick demo access</p>
      <div class="btn-demo-row">
        <button type="button" class="btn-demo" onclick="fillDemo('admin')">🛡️ Admin demo</button>
        <button type="button" class="btn-demo" onclick="fillDemo('teacher')">🔑 Teacher demo</button>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php if (APP_ENV !== 'production' && setting('APP_DEMO_MODE') === '1'): ?>
<script>
function fillDemo(role) {
  const u = document.querySelector('input[name=username]');
  const p = document.querySelector('input[name=password]');
  if (role === 'admin') { u.value = 'admin'; p.value = 'admin123'; }
  else { u.value = 'teacher'; p.value = 'teacher123'; }
}
</script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

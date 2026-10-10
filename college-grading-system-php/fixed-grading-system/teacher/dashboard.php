<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/grading.php';
require_once __DIR__ . '/../includes/roster.php';
$u = require_role('teacher');

$page = $_GET['page'] ?? 'overview';
if (!in_array($page, ['overview', 'classes'], true)) $page = 'overview';

$assetBase = '../';
$pdo = get_db();

$navItems = [
    ['id' => 'overview', 'label' => 'Overview',   'icon' => '📊', 'href' => 'dashboard.php?page=overview'],
    ['id' => 'classes',  'label' => 'My Classes', 'icon' => '📚', 'href' => 'dashboard.php?page=classes'],
];
$activeId  = $page;
$pageTitle = $page === 'overview' ? 'Overview' : 'My Classes';
$roleLabel = 'Teacher';

require __DIR__ . '/../includes/layout_start.php';
?>
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<meta name="base-url"   content="<?= h(base_url()) ?>">

<?php
$stmt = $pdo->prepare('SELECT * FROM classes WHERE teacher_id = ? ORDER BY created_at DESC');
$stmt->execute([$u['id']]);
$myClasses = $stmt->fetchAll();

$classComputed = class_statistics($pdo, array_column($myClasses, 'id'));
$totalStudents = array_sum(array_column($classComputed, 'total'));
$passed = array_sum(array_column($classComputed, 'passed'));
$failed = array_sum(array_column($classComputed, 'failed'));
$passRate = ($passed + $failed) > 0 ? round(($passed / ($passed + $failed)) * 100) : 0;
?>

<?php if ($page === 'overview'): ?>

<div class="hero-banner">
  <h2>Welcome back, <?= h($u['name']) ?>! 👋</h2>
  <p>Here's a quick snapshot of your classes and student performance.</p>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="stat-icon bg-indigo">📚</div>
        <p class="stat-value"><?= count($myClasses) ?></p>
        <p class="stat-label">My Classes</p>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="stat-icon bg-blue">🎓</div>
        <p class="stat-value"><?= $totalStudents ?></p>
        <p class="stat-label">Total Students</p>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="stat-icon bg-emerald">📈</div>
        <p class="stat-value"><?= $passRate ?>%</p>
        <p class="stat-label">Pass Rate (completed grades)</p>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="stat-icon bg-red">⚠️</div>
        <p class="stat-value"><?= $failed ?></p>
        <p class="stat-label">At-Risk (Failing)</p>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <h5 class="section-title">My Classes</h5>
    <div class="row g-3">
      <?php foreach ($myClasses as $c): $cc = $classComputed[$c['id']]; $rate = $cc['passRate'] ?? 0; ?>
      <div class="col-sm-6 col-xl-4">
        <div class="class-card" style="cursor:default;">
          <p class="code"><?= h($c['code']) ?></p>
          <p class="subject"><?= h($c['subject']) ?> — <?= h($c['section']) ?></p>
          <div class="d-flex align-items-center gap-2 mt-2">
            <div class="grade-bar flex-grow-1" style="width:auto;">
              <div class="fill <?= $rate>=75?'fill-pass':'fill-fail' ?>" style="width:<?= $rate ?>%"></div>
            </div>
            <span class="fw-semibold" style="font-size:.75rem;color:#64748b;"><?= $rate ?>% passing</span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$myClasses): ?>
      <div class="col-12">
        <p class="text-muted" style="font-size:.875rem;">No classes yet.</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php else: /* ==================== MY CLASSES ==================== */ ?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <p class="text-muted mb-0" style="font-size:.875rem;">Manage your classes and student rosters.</p>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newClassModal">
    <i class="bi bi-plus-lg me-1"></i> New Class
  </button>
</div>

<div class="row g-3">
  <?php foreach ($myClasses as $c): $cc = $classComputed[$c['id']]; ?>
  <div class="col-sm-6 col-xl-4">
    <div class="class-card" data-class-url="class.php?id=<?= (int)$c['id'] ?>" role="link" tabindex="0">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <p class="code"><?= h($c['code']) ?></p>
          <p class="subject"><?= h($c['subject']) ?></p>
        </div>
        <span class="text-muted">›</span>
      </div>
      <div class="meta">
        <span>Section: <?= h($c['section']) ?></span>
        <span><?= h($c['semester']) ?></span>
        <span><?= h($c['school_year']) ?></span>
      </div>
      <div class="d-flex align-items-center justify-content-between mt-3">
        <span class="text-muted d-flex align-items-center gap-1" style="font-size:.875rem;">
          <i class="bi bi-people"></i> <?= $cc['total'] ?> students
        </span>
        <button class="btn btn-outline-danger btn-sm" style="font-size:.75rem;"
                data-action="delete-class" data-id="<?= (int)$c['id'] ?>" data-name="<?= h($c['code']) ?>">
          <i class="bi bi-trash"></i>
        </button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$myClasses): ?>
  <div class="col-12">
    <p class="text-center text-muted py-5" style="font-size:.875rem;">No classes yet. Click "New Class" to create your first one.</p>
  </div>
  <?php endif; ?>
</div>

<!-- New Class Modal -->
<div class="modal fade" id="newClassModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">Create New Class</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-2">
        <form onsubmit="return submitNewClass(event)">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.8rem;">Class Code</label>
            <input class="form-control form-control-sm" id="nc_code" placeholder="e.g. BSIT-201" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.8rem;">Subject</label>
            <input class="form-control form-control-sm" id="nc_subject" placeholder="e.g. Data Structures and Algorithms" required>
          </div>
          <div class="row g-2 mb-3">
            <div class="col">
              <label class="form-label fw-semibold" style="font-size:.8rem;">Section</label>
              <input class="form-control form-control-sm" id="nc_section" required placeholder="e.g. 2-A">
            </div>
            <div class="col">
              <label class="form-label fw-semibold" style="font-size:.8rem;">School Year</label>
              <input class="form-control form-control-sm" id="nc_sy" value="2025-2026" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.8rem;">Semester</label>
            <select class="form-select form-select-sm" id="nc_sem">
              <option>1st Semester</option>
              <option>2nd Semester</option>
              <option>Summer</option>
            </select>
          </div>
          <div id="ncError" class="alert alert-danger py-2 px-3 d-none" style="font-size:.8rem;"></div>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-plus-circle me-1"></i> Create Class
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  async function submitNewClass(e) {
    e.preventDefault();
    const payload = {
      code: document.getElementById('nc_code').value,
      subject: document.getElementById('nc_subject').value,
      section: document.getElementById('nc_section').value,
      school_year: document.getElementById('nc_sy').value,
      semester: document.getElementById('nc_sem').value,
    };
    const res = await apiPost('/api/class_create.php', payload);
    const err = document.getElementById('ncError');
    if (!res.ok) { err.textContent = res.message || 'Failed to create class.'; err.classList.remove('d-none'); return false; }
    location.href = 'class.php?id=' + res.class_id;
    return false;
  }
  async function deleteClass(id, code) {
    if (!confirm('Delete class "' + code + '"? This cannot be undone.')) return;
    const res = await apiPost('/api/class_delete.php', { id });
    if (res.ok) location.reload(); else showToast(res.message || 'Failed', true);
  }
</script>

<?php endif; ?>

<?php require __DIR__ . '/../includes/layout_end.php'; ?>

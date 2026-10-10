<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/grading.php';
require_role('admin');

$page    = $_GET['page'] ?? 'overview';
$allowed = ['overview', 'teachers', 'weights', 'reports', 'logs'];
if (!in_array($page, $allowed, true)) $page = 'overview';

$assetBase = '../';
$pdo = get_db();

$navItems = [
    ['id' => 'overview', 'label' => 'Overview',        'icon' => '📊', 'href' => 'dashboard.php?page=overview'],
    ['id' => 'teachers', 'label' => 'Manage Teachers',  'icon' => '👥', 'href' => 'dashboard.php?page=teachers'],
    ['id' => 'weights',  'label' => 'Default Weights',  'icon' => '⚖️', 'href' => 'dashboard.php?page=weights'],
    ['id' => 'reports',  'label' => 'Reports',          'icon' => '📈', 'href' => 'dashboard.php?page=reports'],
    ['id' => 'logs',     'label' => 'Audit Logs',       'icon' => '🕓', 'href' => 'dashboard.php?page=logs'],
];
$activeId  = $page;
$titles    = ['overview'=>'Overview','teachers'=>'Manage Teachers','weights'=>'Default Weights','reports'=>'Reports','logs'=>'Audit Logs'];
$pageTitle = $titles[$page];
$roleLabel = 'Administrator';

require __DIR__ . '/../includes/layout_start.php';
?>
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<meta name="base-url"   content="<?= h(base_url()) ?>">

<?php /* ==================== OVERVIEW ==================== */ if ($page === 'overview'): ?>
<?php
  $totalTeachers  = (int)$pdo->query('SELECT COUNT(*) c FROM teachers')->fetch()['c'];
  $activeTeachers = (int)$pdo->query("SELECT COUNT(*) c FROM teachers WHERE status='active'")->fetch()['c'];
  $totalClasses   = (int)$pdo->query('SELECT COUNT(*) c FROM classes')->fetch()['c'];
  $totalStudents  = (int)$pdo->query('SELECT COUNT(*) c FROM students')->fetch()['c'];
  $classes = $pdo->query('SELECT c.*, t.name AS teacher_name FROM classes c LEFT JOIN teachers t ON t.id=c.teacher_id ORDER BY c.created_at DESC')->fetchAll();
  $passed = 0; $failed = 0; $classComputed = [];
  foreach ($classes as $c) {
      $st = $pdo->prepare('SELECT * FROM students WHERE class_id = ?'); $st->execute([$c['id']]); $rows = $st->fetchAll();
      $p = 0;
      foreach ($rows as $s) { $g = compute_grade($s, $c); if ($g['passed']) { $passed++; $p++; } else { $failed++; } }
      $classComputed[$c['id']] = ['passed' => $p, 'total' => count($rows)];
  }
  $passRate = ($passed + $failed) > 0 ? round(($passed / ($passed + $failed)) * 100) : 0;
?>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="stat-icon bg-indigo">👥</div>
        <p class="stat-value"><?= $totalTeachers ?></p>
        <p class="stat-label">Total Teachers</p>
        <p class="stat-sub"><?= $activeTeachers ?> active</p>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="stat-icon bg-blue">📚</div>
        <p class="stat-value"><?= $totalClasses ?></p>
        <p class="stat-label">Total Classes</p>
        <p class="stat-sub">across all teachers</p>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="stat-icon bg-emerald">🎓</div>
        <p class="stat-value"><?= $totalStudents ?></p>
        <p class="stat-label">Total Students</p>
        <p class="stat-sub">enrolled records</p>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="stat-icon bg-amber">📈</div>
        <p class="stat-value"><?= $passRate ?>%</p>
        <p class="stat-label">Overall Pass Rate</p>
        <p class="stat-sub"><?= $passed ?> passed / <?= $failed ?> failed</p>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <h5 class="section-title">Classes Overview</h5>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" style="font-size:.875rem;">
        <thead class="table-light">
          <tr><th>Class</th><th>Teacher</th><th>Students</th><th>Pass Rate</th></tr>
        </thead>
        <tbody>
          <?php foreach ($classes as $c): $cc = $classComputed[$c['id']]; $rate = $cc['total'] ? round(($cc['passed']/$cc['total'])*100) : 0; ?>
          <tr>
            <td>
              <p class="fw-semibold text-dark mb-0" style="font-size:.875rem;"><?= h($c['code']) ?></p>
              <p class="text-muted mb-0" style="font-size:.75rem;"><?= h($c['subject']) ?></p>
            </td>
            <td><?= h($c['teacher_name'] ?? '—') ?></td>
            <td><?= $cc['total'] ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="grade-bar"><div class="fill <?= $rate>=75?'fill-pass':'fill-fail' ?>" style="width:<?= $rate ?>%"></div></div>
                <span class="fw-semibold" style="font-size:.78rem;color:#64748b;"><?= $rate ?>%</span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$classes): ?><tr><td colspan="4" class="text-center text-muted py-4">No classes yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php /* ==================== TEACHERS ==================== */ elseif ($page === 'teachers'): ?>
<?php $teachers = $pdo->query('SELECT * FROM teachers ORDER BY created_at DESC')->fetchAll(); ?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <p class="text-muted mb-0" style="font-size:.875rem;">Manage teacher accounts that can log in to the system.</p>
  <button class="btn btn-primary btn-sm" onclick="openTeacherModal()">
    <i class="bi bi-person-plus me-1"></i> Add Teacher
  </button>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" style="font-size:.875rem;min-width:680px;">
      <thead class="table-light">
        <tr><th>Name</th><th>Username</th><th>Department</th><th>Status</th><th class="text-end">Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($teachers as $t): ?>
        <tr id="teacher-row-<?= $t['id'] ?>">
          <td>
            <p class="fw-semibold text-dark mb-0"><?= h($t['name']) ?></p>
            <p class="text-muted mb-0" style="font-size:.75rem;"><?= h($t['email']) ?></p>
          </td>
          <td><?= h($t['username']) ?></td>
          <td><?= h($t['department']) ?></td>
          <td><span class="badge <?= $t['status']==='active' ? 'badge-active' : 'badge-disabled' ?>"><?= strtoupper($t['status']) ?></span></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary me-1" title="Toggle status" onclick="toggleTeacher(<?= $t['id'] ?>)">
              <i class="bi bi-power"></i>
            </button>
            <button class="btn btn-sm btn-outline-primary me-1" title="Edit" onclick='openTeacherModal(<?= json_encode($t) ?>)'>
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" title="Delete" onclick="deleteTeacher(<?= $t['id'] ?>, <?= json_encode($t['name']) ?>)">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$teachers): ?><tr><td colspan="5" class="text-center text-muted py-4">No teacher accounts yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Teacher Modal -->
<div class="modal fade" id="teacherModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold" id="teacherModalTitle">Add Teacher</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-2">
        <form id="teacherForm" onsubmit="return submitTeacherForm(event)">
          <input type="hidden" id="t_id" value="">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.8rem;">Full Name</label>
            <input class="form-control form-control-sm" id="t_name" required placeholder="e.g. Prof. Maria Santos">
          </div>
          <div class="row g-2 mb-3">
            <div class="col">
              <label class="form-label fw-semibold" style="font-size:.8rem;">Username</label>
              <input class="form-control form-control-sm" id="t_username" required placeholder="e.g. msantos">
            </div>
            <div class="col">
              <label class="form-label fw-semibold" style="font-size:.8rem;">Password</label>
              <input class="form-control form-control-sm" id="t_password" placeholder="(leave blank to keep)">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.8rem;">Email</label>
            <input class="form-control form-control-sm" id="t_email" type="email" placeholder="e.g. msantos@college.edu">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.8rem;">Department</label>
            <input class="form-control form-control-sm" id="t_department" placeholder="e.g. BSCS">
          </div>
          <div id="teacherFormError" class="alert alert-danger py-2 px-3 d-none" style="font-size:.8rem;"></div>
          <button type="submit" class="btn btn-primary w-100">Save Teacher</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  const teacherModal = new bootstrap.Modal(document.getElementById('teacherModal'));

  function openTeacherModal(t) {
    document.getElementById('teacherFormError').classList.add('d-none');
    if (t) {
      document.getElementById('teacherModalTitle').textContent = 'Edit Teacher';
      document.getElementById('t_id').value       = t.id;
      document.getElementById('t_name').value     = t.name;
      document.getElementById('t_username').value = t.username;
      document.getElementById('t_password').value = '';
      document.getElementById('t_email').value    = t.email;
      document.getElementById('t_department').value = t.department;
    } else {
      document.getElementById('teacherModalTitle').textContent = 'Add Teacher';
      document.getElementById('teacherForm').reset();
      document.getElementById('t_id').value = '';
    }
    teacherModal.show();
  }

  async function submitTeacherForm(e) {
    e.preventDefault();
    const payload = {
      id: document.getElementById('t_id').value,
      name: document.getElementById('t_name').value,
      username: document.getElementById('t_username').value,
      password: document.getElementById('t_password').value,
      email: document.getElementById('t_email').value,
      department: document.getElementById('t_department').value,
    };
    const res = await apiPost('/api/teacher_save.php', payload);
    const errEl = document.getElementById('teacherFormError');
    if (!res.ok) {
      errEl.textContent = res.message || 'Failed to save.';
      errEl.classList.remove('d-none');
      return false;
    }
    teacherModal.hide();
    location.reload();
    return false;
  }

  async function toggleTeacher(id) {
    const res = await apiPost('/api/teacher_toggle.php', { id });
    if (res.ok) location.reload(); else showToast(res.message || 'Failed', true);
  }

  async function deleteTeacher(id, name) {
    if (!confirm('Delete teacher "' + name + '"? This will also delete their classes.')) return;
    const res = await apiPost('/api/teacher_delete.php', { id });
    if (res.ok) location.reload(); else showToast(res.message || 'Failed', true);
  }
</script>

<?php /* ==================== DEFAULT WEIGHTS ==================== */ elseif ($page === 'weights'): ?>
<?php $dw = $pdo->query('SELECT * FROM default_weights WHERE id=1')->fetch(); ?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h5 class="section-title mb-1">Default Grading Weights</h5>
        <p class="text-muted mb-4" style="font-size:.875rem;">
          These weights apply to newly created classes. Teachers may still adjust per class. Total must equal exactly 100%.
        </p>

        <div id="dwScope" data-weights-scope>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="weight-box lecture">
                <p class="wtitle lecture-total-label">Lecture — <?= (float)$dw['class_standing'] + (float)$dw['major_exams'] ?>%</p>
                <div class="row g-2">
                  <div class="col-6">
                    <div class="weight-field">
                      <label>Class Standing</label>
                      <p class="hint">Quizzes / Oral / Attendance</p>
                      <div class="input-wrap">
                        <input type="number" class="weight-input" data-w="class_standing"
                               value="<?= h($dw['class_standing']) ?>" min="0" max="100" step="0.5">
                        <span class="pct-label">%</span>
                      </div>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="weight-field">
                      <label>Major Exams</label>
                      <p class="hint">Prelim + Final average</p>
                      <div class="input-wrap">
                        <input type="number" class="weight-input" data-w="major_exams"
                               value="<?= h($dw['major_exams']) ?>" min="0" max="100" step="0.5">
                        <span class="pct-label">%</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="weight-box lab">
                <p class="wtitle lab-total-label">Laboratory — <?= (float)$dw['performance_task'] + (float)$dw['activity_output'] ?>%</p>
                <div class="row g-2">
                  <div class="col-6">
                    <div class="weight-field">
                      <label>Performance Task</label>
                      <p class="hint">Skills demonstration</p>
                      <div class="input-wrap">
                        <input type="number" class="weight-input" data-w="performance_task"
                               value="<?= h($dw['performance_task']) ?>" min="0" max="100" step="0.5">
                        <span class="pct-label">%</span>
                      </div>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="weight-field">
                      <label>Activity Output</label>
                      <p class="hint">Lab activities / outputs</p>
                      <div class="input-wrap">
                        <input type="number" class="weight-input" data-w="activity_output"
                               value="<?= h($dw['activity_output']) ?>" min="0" max="100" step="0.5">
                        <span class="pct-label">%</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="weights-footer valid">
            <span class="wtxt weights-total-txt">Total: 100% — Balanced ✓</span>
            <button class="btn btn-primary btn-sm weights-save-btn" onclick="saveDefaultWeights()">
              <i class="bi bi-save me-1"></i> Save Default Weights
            </button>
          </div>
          <p id="dwMsg" class="mt-2 mb-0"></p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  async function saveDefaultWeights() {
    const scope = document.getElementById('dwScope');
    const payload = {};
    scope.querySelectorAll('.weight-input').forEach(i => payload[i.dataset.w] = i.value);
    const res = await apiPost('/api/default_weights_save.php', payload);
    const msg = document.getElementById('dwMsg');
    msg.className   = res.ok ? 'msg-ok' : 'msg-err';
    msg.textContent = res.ok ? '✓ Weights saved successfully.' : (res.message || 'Unable to save weights.');
  }
  recalcWeightsTotal('dwScope');
</script>

<?php /* ==================== REPORTS ==================== */ elseif ($page === 'reports'): ?>
<?php
  $classes = $pdo->query('SELECT c.*, t.name AS teacher_name FROM classes c LEFT JOIN teachers t ON t.id=c.teacher_id ORDER BY c.created_at DESC')->fetchAll();
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <p class="text-muted mb-0" style="font-size:.875rem;">Performance summary across all teachers and classes.</p>
  <a class="btn btn-success btn-sm" href="<?= h(base_url()) ?>/api/export_summary.php">
    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Summary (Excel/CSV)
  </a>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" style="font-size:.875rem;min-width:860px;">
      <thead class="table-light">
        <tr>
          <th>Class</th><th>Teacher</th><th>S.Y. / Semester</th>
          <th class="text-center">Students</th>
          <th class="text-center">Passed</th>
          <th class="text-center">Failed</th>
          <th>Pass Rate</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($classes as $c):
          $stmt = $pdo->prepare('SELECT * FROM students WHERE class_id=?'); $stmt->execute([$c['id']]); $rows = $stmt->fetchAll();
          $p=0; foreach ($rows as $s) { if (compute_grade($s,$c)['passed']) $p++; }
          $total = count($rows); $f = $total - $p; $rate = $total ? round(($p/$total)*100) : 0;
        ?>
        <tr>
          <td>
            <p class="fw-semibold text-dark mb-0" style="font-size:.875rem;"><?= h($c['code']) ?></p>
            <p class="text-muted mb-0" style="font-size:.75rem;"><?= h($c['subject']) ?> — <?= h($c['section']) ?></p>
          </td>
          <td><?= h($c['teacher_name'] ?? '—') ?></td>
          <td>
            <?= h($c['school_year']) ?>
            <div class="text-muted" style="font-size:.75rem;"><?= h($c['semester']) ?></div>
          </td>
          <td class="text-center"><?= $total ?></td>
          <td class="text-center fw-bold text-success"><?= $p ?></td>
          <td class="text-center fw-bold text-danger"><?= $f ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="grade-bar"><div class="fill <?= $rate>=75?'fill-pass':'fill-fail' ?>" style="width:<?= $rate ?>%"></div></div>
              <span class="fw-semibold" style="font-size:.78rem;color:#64748b;"><?= $rate ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$classes): ?><tr><td colspan="7" class="text-center text-muted py-4">No classes to report yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php /* ==================== AUDIT LOGS ==================== */ elseif ($page === 'logs'): ?>
<?php
  $q          = trim($_GET['q'] ?? '');
  $roleFilter = $_GET['role'] ?? 'all';
  $sql        = 'SELECT * FROM audit_logs WHERE 1=1';
  $params     = [];
  if ($q !== '') { $sql .= ' AND (action LIKE ? OR details LIKE ? OR actor LIKE ?)'; $like = "%$q%"; $params = [$like,$like,$like]; }
  if (in_array($roleFilter, ['admin','teacher'], true)) { $sql .= ' AND role = ?'; $params[] = $roleFilter; }
  $sql .= ' ORDER BY ts DESC LIMIT 500';
  $stmt = $pdo->prepare($sql); $stmt->execute($params); $logs = $stmt->fetchAll();
?>

<form method="get" class="filters-row mb-3">
  <input type="hidden" name="page" value="logs">
  <input type="text"  class="form-control form-control-sm" name="q" placeholder="Search logs…" value="<?= h($q) ?>" style="flex:1;min-width:180px;">
  <select class="form-select form-select-sm" name="role" onchange="this.form.submit()" style="width:auto;">
    <option value="all"     <?= $roleFilter==='all'    ?'selected':'' ?>>All Roles</option>
    <option value="admin"   <?= $roleFilter==='admin'  ?'selected':'' ?>>Admin</option>
    <option value="teacher" <?= $roleFilter==='teacher'?'selected':'' ?>>Teacher</option>
  </select>
  <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
</form>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" style="font-size:.83rem;min-width:700px;">
      <thead class="table-light">
        <tr><th>Timestamp</th><th>Actor</th><th>Action</th><th>Details</th></tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $l): ?>
        <tr>
          <td class="text-nowrap text-muted"><?= h(date('n/j/Y, g:i:s A', strtotime($l['ts']))) ?></td>
          <td>
            <span class="fw-semibold text-dark"><?= h($l['actor']) ?></span>
            <span class="badge ms-1 <?= $l['role']==='admin' ? 'badge-admin' : 'badge-teacher' ?>"><?= strtoupper(h($l['role'])) ?></span>
          </td>
          <td><code class="mono"><?= h($l['action']) ?></code></td>
          <td class="text-secondary"><?= h($l['details']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?><tr><td colspan="4" class="text-center text-muted py-4">No matching audit logs.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>

<?php require __DIR__ . '/../includes/layout_end.php'; ?>

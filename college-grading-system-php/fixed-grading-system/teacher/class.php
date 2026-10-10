<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/grading.php';
require_once __DIR__ . '/../includes/roster.php';
$u = require_role('teacher');

$pdo     = get_db();
$classId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM classes WHERE id = ? AND teacher_id = ?');
$stmt->execute([$classId, $u['id']]);
$cls = $stmt->fetch();

$assetBase = '../';
$navItems  = [
    ['id' => 'overview', 'label' => 'Overview',   'icon' => '📊', 'href' => 'dashboard.php?page=overview'],
    ['id' => 'classes',  'label' => 'My Classes', 'icon' => '📚', 'href' => 'dashboard.php?page=classes'],
];
$activeId  = 'classes';
$pageTitle = $cls ? $cls['code'] : 'Class';
$roleLabel = 'Teacher';

require __DIR__ . '/../includes/layout_start.php';
?>
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<meta name="base-url"   content="<?= h(base_url()) ?>">

<?php if (!$cls): ?>
<div class="text-center py-5 text-muted">
  <p>Class not found.</p>
  <a href="dashboard.php?page=classes" class="btn btn-outline-primary btn-sm">
    <i class="bi bi-arrow-left me-1"></i> Go back
  </a>
</div>

<?php else:
  $search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
  $status = in_array($_GET['status'] ?? '', ['passed', 'failed', 'incomplete'], true) ? $_GET['status'] : 'all';
  $rosterPage = max(1, (int)($_GET['roster_page'] ?? 1));
  $where = 's.class_id = ?';
  $params = [$cls['id']];
  if ($search !== '') {
      $where .= " AND (LOCATE(?, s.name) > 0 OR LOCATE(?, s.student_id) > 0)";
      $params[] = $search; $params[] = $search;
  }
  $gradeSql = final_grade_sql();
  if ($status === 'passed') $where .= " AND $gradeSql >= 75";
  if ($status === 'failed') $where .= " AND $gradeSql < 75";
  if ($status === 'incomplete') $where .= " AND $gradeSql IS NULL";
  $from = ' FROM students s JOIN classes c ON c.id = s.class_id WHERE ' . $where;
  $stmt = $pdo->prepare('SELECT COUNT(*)' . $from);
  $stmt->execute($params);
  $matching = (int)$stmt->fetchColumn();
  $pageCount = max(1, (int)ceil($matching / 50));
  $rosterPage = min($rosterPage, $pageCount);
  $offset = ($rosterPage - 1) * 50;
  $stmt = $pdo->prepare('SELECT s.*' . $from . " ORDER BY s.id ASC LIMIT 50 OFFSET $offset");
  $stmt->execute($params);
  $students = $stmt->fetchAll();
  $statistics = class_statistics($pdo, [$cls['id']])[$cls['id']];
  $tab = ($_GET['tab'] ?? 'grades') === 'weights' ? 'weights' : 'grades';
?>

<!-- Class header -->
<div class="class-head mb-3">
  <button class="back-btn" onclick="location.href='dashboard.php?page=classes'" title="Back">
    <i class="bi bi-arrow-left"></i>
  </button>
  <div>
    <h2><?= h($cls['code']) ?> — <?= h($cls['subject']) ?></h2>
    <p class="sub">Section <?= h($cls['section']) ?> • <?= h($cls['semester']) ?>, S.Y. <?= h($cls['school_year']) ?></p>
  </div>
</div>

<!-- Tabs -->
<div class="grade-tabs mb-3">
  <button class="tab-btn <?= $tab==='grades'?'active':'' ?>"
          onclick="location.href='?id=<?= $cls['id'] ?>&tab=grades'">
    <i class="bi bi-table me-1"></i> Grade Sheet
  </button>
  <button class="tab-btn <?= $tab==='weights'?'active':'' ?>"
          onclick="location.href='?id=<?= $cls['id'] ?>&tab=weights'">
    <i class="bi bi-sliders me-1"></i> Grading Weights
  </button>
  <div class="tab-actions">
    <a class="btn btn-outline-secondary btn-sm" href="<?= h(base_url()) ?>/api/export_class.php?id=<?= $cls['id'] ?>&format=csv">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel (CSV)
    </a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= h(base_url()) ?>/api/export_class.php?id=<?= $cls['id'] ?>&format=pdf">
      <i class="bi bi-file-earmark-pdf me-1"></i> PDF
    </a>
  </div>
</div>

<?php if ($tab === 'grades'): ?>

<!-- Class performance and roster tools -->
<section class="card border-0 shadow-sm mb-3" aria-label="Class performance">
  <div class="card-body">
    <h3 class="h6">Class performance</h3>
    <p class="text-muted small">Class averages and pass rates use completed grades only. Blank assessments are incomplete; recorded zero scores count.</p>
    <div class="row g-3" id="classStatistics" data-statistics="<?= h(json_encode($statistics)) ?>" aria-live="polite" aria-atomic="true">
      <div class="col-6 col-md-3"><span class="text-muted small">Average grade</span><p class="fw-bold mb-0" id="statAverage"><?= $statistics['average'] === null ? '—' : number_format($statistics['average'], 2) . '%' ?></p></div>
      <div class="col-6 col-md-3"><span class="text-muted small">Pass rate</span><p class="fw-bold mb-0" id="statPassRate"><?= $statistics['passRate'] === null ? '—' : number_format($statistics['passRate'], 1) . '%' ?></p></div>
      <div class="col-6 col-md-3"><span class="text-muted small">Passing</span><p class="fw-bold mb-0" id="statPassed"><?= $statistics['passed'] ?></p></div>
      <div class="col-6 col-md-3"><span class="text-muted small">Failing</span><p class="fw-bold mb-0" id="statFailed"><?= $statistics['failed'] ?></p></div>
      <div class="col-6 col-md-3"><span class="text-muted small">Incomplete</span><p class="fw-bold mb-0" id="statIncomplete"><?= $statistics['incomplete'] ?></p></div>
    </div>
  </div>
</section>
<form method="get" class="row g-2 align-items-end mb-3" id="rosterFilters">
  <input type="hidden" name="id" value="<?= (int)$cls['id'] ?>">
  <div class="col-12 col-sm-6">
    <label for="studentSearch" class="form-label small fw-semibold">Search students</label>
    <input type="search" id="studentSearch" name="q" value="<?= h($search) ?>" class="form-control form-control-sm" placeholder="Name or student ID" aria-controls="gradeTable studentCards">
  </div>
  <div class="col-8 col-sm-4">
    <label for="studentStatus" class="form-label small fw-semibold">Grade status</label>
    <select id="studentStatus" name="status" class="form-select form-select-sm" aria-controls="gradeTable studentCards">
      <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All students</option>
      <option value="passed" <?= $status === 'passed' ? 'selected' : '' ?>>Passed</option>
      <option value="incomplete" <?= $status === 'incomplete' ? 'selected' : '' ?>>Incomplete</option>
      <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
    </select>
  </div>
  <div class="col-4 col-sm-2"><button type="submit" class="btn btn-primary btn-sm w-100 mb-1">Apply</button><button type="button" id="clearStudentFilters" class="btn btn-outline-secondary btn-sm w-100">Clear</button></div>
  <div class="col-12"><p id="studentFilterCount" class="text-muted small mb-0" role="status">Showing <?= count($students) ?> of <?= $matching ?> matching students (<?= $statistics['total'] ?> in class)</p></div>
</form>
<p id="studentFilterEmpty" class="text-center text-muted py-3" <?= $matching > 0 || $statistics['total'] === 0 ? 'hidden' : '' ?>>No students match your search and grade status.</p>
<nav class="d-flex align-items-center gap-3 mb-3" aria-label="Roster pages">
  <?php if ($rosterPage > 1): ?><a href="?<?= h(http_build_query(['id' => $cls['id'], 'q' => $search, 'status' => $status, 'roster_page' => $rosterPage - 1])) ?>">Previous</a><?php endif; ?>
  <span>Page <?= $rosterPage ?> of <?= $pageCount ?></span>
  <?php if ($rosterPage < $pageCount): ?><a href="?<?= h(http_build_query(['id' => $cls['id'], 'q' => $search, 'status' => $status, 'roster_page' => $rosterPage + 1])) ?>">Next</a><?php endif; ?>
</nav>

<div class="mb-3" aria-live="polite">
  <span data-save-status>All changes saved</span>
  <button type="button" id="retryScoreSaves" class="btn btn-outline-danger btn-sm ms-2" hidden>Retry unsaved scores</button>
  <p id="rosterRefreshHint" class="text-muted small mb-0" hidden>Scores updated. Apply the filters again to refresh matching students.</p>
</div>
<!-- Add Student Bar -->
<div class="add-student-bar">
  <div class="field-wrap flex-grow-1">
    <label>Student Name</label>
    <input class="form-control form-control-sm" id="as_name" placeholder="Full Name, e.g. Garcia, Sophia L." required>
  </div>
  <div class="field-wrap" style="min-width:160px;">
    <label>Student ID</label>
    <input class="form-control form-control-sm" id="as_id" placeholder="2025-00000">
  </div>
  <button type="button" class="btn btn-primary btn-sm" onclick="submitAddStudent()">
    <i class="bi bi-person-plus me-1"></i> Add Student
  </button>
</div>

<!-- Desktop grade table -->
<div class="gradesheet-wrap">
  <div class="table-responsive">
    <table class="gradesheet" id="gradeTable">
      <thead>
        <tr class="r1">
          <th rowspan="2" class="stu-col">Student</th>
          <th colspan="3" class="grp-lec">Class Standing (<?= h($cls['class_standing']) ?>%)</th>
          <th colspan="2" class="grp-lec">Major Exams (<?= h($cls['major_exams']) ?>%)</th>
          <th rowspan="2" class="grp-lecfin">Lecture Grade</th>
          <th rowspan="2" class="grp-lab">Perf. Task (<?= h($cls['performance_task']) ?>%)</th>
          <th rowspan="2" class="grp-lab">Activity (<?= h($cls['activity_output']) ?>%)</th>
          <th rowspan="2" class="grp-labfin">Lab Grade</th>
          <th rowspan="2" class="grp-final">Final %</th>
          <th rowspan="2" class="grp-final">GPA</th>
          <th rowspan="2" class="grp-final">Remarks</th>
          <th rowspan="2" class="grp-final"></th>
        </tr>
        <tr class="r2">
          <th>Quiz</th><th>Oral</th><th>Attend.</th>
          <th>Prelim</th><th>Final</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $s): $g = compute_grade($s, $cls); ?>
        <tr data-row-id="<?= $s['id'] ?>" data-student-search="<?= h($s['name'] . ' ' . $s['student_id']) ?>" data-passed="<?= $g['passed'] ? 'true' : 'false' ?>" data-final-percent="<?= h($g['finalPercent']) ?>" data-grade-status="<?= h($g['status']) ?>">
          <td class="stu-col">
            <p class="stu-name"><?= h($s['name']) ?></p>
            <p class="stu-id"><?= h($s['student_id']) ?></p>
          </td>
          <?php foreach (['quiz','oral','attendance','prelim','final_exam'] as $f): ?>
          <td>
            <input type="number" class="score-input" min="0" max="100" step="0.01" aria-label="<?= h($s['name'] . ' ' . $f) ?>" value="<?= h($s[$f]) ?>"
                   data-student-id="<?= $s['id'] ?>" data-class-id="<?= $cls['id'] ?>" data-field="<?= $f ?>">
          </td>
          <?php endforeach; ?>
          <td class="lec-cell js-lecture"><?= $g['lectureGrade'] ?? '—' ?></td>
          <?php foreach (['performance_task','activity_output'] as $f): ?>
          <td>
            <input type="number" class="score-input" min="0" max="100" step="0.01" aria-label="<?= h($s['name'] . ' ' . $f) ?>" value="<?= h($s[$f]) ?>"
                   data-student-id="<?= $s['id'] ?>" data-class-id="<?= $cls['id'] ?>" data-field="<?= $f ?>">
          </td>
          <?php endforeach; ?>
          <td class="lab-cell js-lab"><?= $g['labGrade'] ?? '—' ?></td>
          <td class="fin-cell js-final"><?= $g['finalPercent'] === null ? '—' : $g['finalPercent'] . '%' ?></td>
          <td class="gpa-cell js-gpa"><?= ($g['gpa'] === null ? '—' : number_format($g['gpa'], 2)) ?></td>
          <td>
            <span class="badge js-remarks <?= $g['status'] === 'incomplete' ? 'badge-incomplete' : ($g['passed'] ? 'badge-pass' : 'badge-fail') ?>">
              <?= strtoupper($g['status']) ?>
            </span>
          </td>
          <td>
            <button class="btn btn-sm btn-outline-danger" title="Remove student"
                    data-action="remove-student" data-id="<?= (int)$s['id'] ?>" data-name="<?= h($s['name']) ?>">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$students && $statistics['total'] === 0): ?>
        <tr><td colspan="14" class="text-center text-muted py-4">No students yet. Add students above to begin encoding grades.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Mobile student cards -->
<div class="stu-cards mt-2" id="studentCards">
  <?php foreach ($students as $s): $g = compute_grade($s, $cls); ?>
  <div class="stu-card" data-row-id="<?= $s['id'] ?>" data-student-search="<?= h($s['name'] . ' ' . $s['student_id']) ?>" data-passed="<?= $g['passed'] ? 'true' : 'false' ?>" data-final-percent="<?= h($g['finalPercent']) ?>" data-grade-status="<?= h($g['status']) ?>">
    <div class="d-flex align-items-start justify-content-between gap-2">
      <div>
        <p class="stu-name"><?= h($s['name']) ?></p>
        <p class="stu-id"><?= h($s['student_id']) ?></p>
      </div>
      <span class="badge js-remarks <?= $g['status'] === 'incomplete' ? 'badge-incomplete' : ($g['passed'] ? 'badge-pass' : 'badge-fail') ?>">
        <?= strtoupper($g['status']) ?>
      </span>
    </div>

    <p class="grp-title">Class Standing (<?= h($cls['class_standing']) ?>%)</p>
    <div class="score-grid">
      <?php foreach ([['quiz','Quiz'],['oral','Oral'],['attendance','Attend.']] as [$f,$lbl]): ?>
      <label><?= $lbl ?>
        <input type="number" class="score-input form-control form-control-sm" min="0" max="100" step="0.01"
               value="<?= h($s[$f]) ?>" data-student-id="<?= $s['id'] ?>" data-class-id="<?= $cls['id'] ?>" data-field="<?= $f ?>">
      </label>
      <?php endforeach; ?>
    </div>

    <p class="grp-title">Major Exams (<?= h($cls['major_exams']) ?>%)</p>
    <div class="score-grid">
      <?php foreach ([['prelim','Prelim'],['final_exam','Final']] as [$f,$lbl]): ?>
      <label><?= $lbl ?>
        <input type="number" class="score-input form-control form-control-sm" min="0" max="100" step="0.01"
               value="<?= h($s[$f]) ?>" data-student-id="<?= $s['id'] ?>" data-class-id="<?= $cls['id'] ?>" data-field="<?= $f ?>">
      </label>
      <?php endforeach; ?>
    </div>

    <p class="grp-title lab">Laboratory (<?= h($cls['performance_task'] + $cls['activity_output']) ?>%)</p>
    <div class="score-grid">
      <?php foreach ([['performance_task','Perf. Task'],['activity_output','Activity']] as [$f,$lbl]): ?>
      <label><?= $lbl ?>
        <input type="number" class="score-input form-control form-control-sm" min="0" max="100" step="0.01"
               value="<?= h($s[$f]) ?>" data-student-id="<?= $s['id'] ?>" data-class-id="<?= $cls['id'] ?>" data-field="<?= $f ?>">
      </label>
      <?php endforeach; ?>
    </div>

    <div class="summary">
      <div><p class="lbl">Lecture</p><p class="fw-bold mb-0 js-lecture" style="color:#4338ca;"><?= $g['lectureGrade'] ?? '—' ?></p></div>
      <div><p class="lbl">Lab</p><p class="fw-bold mb-0 js-lab" style="color:#047857;"><?= $g['labGrade'] ?? '—' ?></p></div>
      <div><p class="lbl">Final %</p><p class="fw-bold mb-0 js-final" style="color:#1e293b;"><?= $g['finalPercent'] === null ? '—' : $g['finalPercent'] . '%' ?></p></div>
    </div>
    <p class="gpaline">GPA Equivalent: <span class="fw-semibold js-gpa" style="color:#475569;"><?= ($g['gpa'] === null ? '—' : number_format($g['gpa'], 2)) ?></span></p>
    <button class="remove-btn-full" data-action="remove-student" data-id="<?= (int)$s['id'] ?>" data-name="<?= h($s['name']) ?>">
      <i class="bi bi-trash me-1"></i> Remove student
    </button>
  </div>
  <?php endforeach; ?>
  <?php if (!$students && $statistics['total'] === 0): ?>
  <p class="text-center text-muted py-4" style="font-size:.875rem;">No students yet.</p>
  <?php endif; ?>
</div>

<script>
  async function submitAddStudent() {
    const name = document.getElementById('as_name').value.trim();
    const sid  = document.getElementById('as_id').value.trim();
    if (!name) { showToast('Student name is required.', true); return; }
    const res = await apiPost('/api/student_add.php', { class_id: <?= $cls['id'] ?>, name, student_id: sid });
    if (res.ok) location.reload(); else showToast(res.message || 'Failed to add student.', true);
  }
  async function removeStudent(id, name) {
    if (!confirm('Remove ' + name + ' from the class?')) return;
    const res = await apiPost('/api/student_remove.php', { id });
    if (res.ok) location.reload(); else showToast(res.message || 'Failed', true);
  }
</script>

<?php else: /* ==================== WEIGHTS TAB ==================== */ ?>

<div class="row justify-content-center">
  <div class="col-lg-9">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <p class="text-muted mb-4" style="font-size:.875rem;">
          Adjust how much each grading component contributes to this class's final grade. The total must always equal 100%.
        </p>

        <div id="cwScope" data-weights-scope>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="weight-box lecture">
                <p class="wtitle lecture-total-label">Lecture — <?= (float)$cls['class_standing'] + (float)$cls['major_exams'] ?>%</p>
                <div class="row g-2">
                  <div class="col-6">
                    <div class="weight-field">
                      <label>Class Standing</label>
                      <p class="hint">Quizzes / Oral / Attendance</p>
                      <div class="input-wrap">
                        <input type="number" class="weight-input" data-w="class_standing"
                               value="<?= h($cls['class_standing']) ?>" min="0" max="100" step="0.5">
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
                               value="<?= h($cls['major_exams']) ?>" min="0" max="100" step="0.5">
                        <span class="pct-label">%</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="weight-box lab">
                <p class="wtitle lab-total-label">Laboratory — <?= (float)$cls['performance_task'] + (float)$cls['activity_output'] ?>%</p>
                <div class="row g-2">
                  <div class="col-6">
                    <div class="weight-field">
                      <label>Performance Task</label>
                      <p class="hint">Skills demonstration</p>
                      <div class="input-wrap">
                        <input type="number" class="weight-input" data-w="performance_task"
                               value="<?= h($cls['performance_task']) ?>" min="0" max="100" step="0.5">
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
                               value="<?= h($cls['activity_output']) ?>" min="0" max="100" step="0.5">
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
            <button class="btn btn-primary btn-sm weights-save-btn" onclick="saveClassWeights()">
              <i class="bi bi-save me-1"></i> Save Weights
            </button>
          </div>
          <p id="cwMsg" class="mt-2 mb-0"></p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  async function saveClassWeights() {
    const scope = document.getElementById('cwScope');
    const payload = { class_id: <?= $cls['id'] ?> };
    scope.querySelectorAll('.weight-input').forEach(i => payload[i.dataset.w] = i.value);
    const res = await apiPost('/api/class_weights_save.php', payload);
    const msg = document.getElementById('cwMsg');
    msg.className   = res.ok ? 'msg-ok' : 'msg-err';
    msg.textContent = res.ok ? '✓ Weights saved successfully.' : (res.message || 'Unable to save weights.');
  }

</script>

<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout_end.php'; ?>

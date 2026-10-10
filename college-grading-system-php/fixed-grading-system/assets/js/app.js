// College Grading System — shared front-end helpers

function showToast(message, isError) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = message;
  t.className = 'toast-custom show' + (isError ? ' error' : '');
  clearTimeout(window.__toastTimer);
  window.__toastTimer = setTimeout(() => { t.className = 'toast-custom'; }, 2600);
}

function getCsrfToken() {
  const el = document.querySelector('meta[name="csrf-token"]');
  return el ? el.getAttribute('content') : '';
}

function getBaseUrl() {
  const el = document.querySelector('meta[name="base-url"]');
  return el ? el.getAttribute('content').replace(/\/$/, '') : '';
}

async function apiPost(url, data) {
  const body = new FormData();
  for (const k in data) body.append(k, data[k]);
  body.append('csrf_token', getCsrfToken());
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 15000);
  try {
    const response = await fetch(getBaseUrl() + url, { method: 'POST', body, signal: controller.signal });
    const json = await response.json();
    if (!response.ok) return { ok: false, message: json.message || 'Unable to save. Please try again.' };
    return json;
  } catch (error) {
    return { ok: false, message: 'Save could not be confirmed. Check your connection and retry.' };
  } finally {
    clearTimeout(timeout);
  }
}

// ---- Sidebar toggle ----
document.addEventListener('DOMContentLoaded', () => {
  const sidebar   = document.getElementById('sidebar');
  const backdrop  = document.getElementById('sidebarBackdrop');
  const menuBtn   = document.getElementById('menuBtn');

  function openSidebar()  { sidebar?.classList.add('show'); backdrop?.classList.add('show'); }
  function closeSidebar() { sidebar?.classList.remove('show'); backdrop?.classList.remove('show'); }

  menuBtn?.addEventListener('click', openSidebar);
  backdrop?.addEventListener('click', closeSidebar);
});

// Keep one request in flight; replace queued edits to the same cell with its newest value.
const pendingScores = new Map();
const failedScores = new Map();
const scoreVersions = new Map();
let scoreTimer;
let savingScores = false;

function hasUnsavedScores() {
  return savingScores || pendingScores.size > 0 || failedScores.size > 0;
}

function showSaveStatus() {
  const message = failedScores.size ? 'Unsaved changes — retry required'
    : (savingScores || pendingScores.size ? 'Saving changes…' : 'All changes saved');
  document.querySelectorAll('[data-save-status]').forEach(el => { el.textContent = message; });
  const retry = document.getElementById('retryScoreSaves');
  if (retry) retry.hidden = !failedScores.size;
}

function queueScoreSave(input) {
  const { studentId, field, classId } = input.dataset;
  const key = studentId + ':' + field;
  const version = (scoreVersions.get(key) || 0) + 1;
  scoreVersions.set(key, version);
  const value = input.value.trim();
  document.querySelectorAll('.score-input').forEach(other => {
    if (other.dataset.studentId === studentId && other.dataset.field === field) other.value = value;
  });
  const job = { key, version, studentId, field, classId, value };
  failedScores.delete(key);
  if (value !== '' && (!Number.isFinite(Number(value)) || Number(value) < 0 || Number(value) > 100
      || Math.abs(Number(value) * 100 - Math.round(Number(value) * 100)) > 0.000001)) {
    pendingScores.delete(key);
    failedScores.set(key, job);
    showToast('Enter 0–100 with up to two decimals, or leave blank for incomplete.', true);
  } else {
    pendingScores.set(key, job);
  }
  clearTimeout(scoreTimer);
  scoreTimer = setTimeout(flushScoreSaves, 500);
  showSaveStatus();
}

async function flushScoreSaves() {
  if (savingScores) return;
  savingScores = true;
  showSaveStatus();
  try {
    while (pendingScores.size) {
      const [key, job] = pendingScores.entries().next().value;
      pendingScores.delete(key);
      const result = await apiPost('/api/save_score.php', {
        class_id: job.classId, student_id: job.studentId, field: job.field, value: job.value
      });
      if (!result.ok) {
        if (scoreVersions.get(key) === job.version) failedScores.set(key, job);
        showToast(result.message || 'Score was not saved.', true);
        continue;
      }
      if (scoreVersions.get(key) === job.version) {
        failedScores.delete(key);
        document.querySelectorAll('.score-input').forEach(input => {
          if (input.dataset.studentId === job.studentId && input.dataset.field === job.field) input.value = result.value ?? '';
        });
      }
      // A newer edit for this student must not be replaced by an older computed grade.
      if (![...pendingScores.values(), ...failedScores.values()].some(item => item.studentId === job.studentId)) {
        updateRowComputed(job.studentId, result.grade);
      }
      if (result.statistics) renderStatistics(result.statistics);
    }
  } finally {
    savingScores = false;
    showSaveStatus();
  }
}

function renderStatistics(stats) {
  const values = {
    statAverage: stats.average === null ? '—' : Number(stats.average).toFixed(2) + '%',
    statPassRate: stats.passRate === null ? '—' : Number(stats.passRate).toFixed(1) + '%',
    statPassed: stats.passed, statFailed: stats.failed, statIncomplete: stats.incomplete
  };
  for (const [id, value] of Object.entries(values)) {
    const element = document.getElementById(id);
    if (element) element.textContent = String(value);
  }
}

function updateRowComputed(studentId, grade) {
  document.querySelectorAll('[data-row-id="' + studentId + '"]').forEach(row => {
    row.dataset.gradeStatus = grade.status;
    row.dataset.passed = String(grade.passed);
    row.dataset.finalPercent = grade.finalPercent ?? '';
    const values = { '.js-lecture': grade.lectureGrade ?? '—', '.js-lab': grade.labGrade ?? '—',
      '.js-final': grade.finalPercent === null ? '—' : grade.finalPercent + '%',
      '.js-gpa': grade.gpa === null ? '—' : Number(grade.gpa).toFixed(2) };
    for (const [selector, value] of Object.entries(values)) {
      const element = row.querySelector(selector);
      if (element) element.textContent = value;
    }
    const remarks = row.querySelector('.js-remarks');
    if (remarks) {
      remarks.textContent = grade.status.toUpperCase();
      remarks.className = 'badge js-remarks ' + (grade.status === 'incomplete' ? 'badge-incomplete' : (grade.passed ? 'badge-pass' : 'badge-fail'));
    }
  });
  const hint = document.getElementById('rosterRefreshHint');
  if (hint) hint.hidden = false;
}

document.addEventListener('input', event => {
  if (event.target.classList?.contains('score-input')) queueScoreSave(event.target);
});
window.addEventListener('beforeunload', event => {
  if (hasUnsavedScores()) { event.preventDefault(); event.returnValue = ''; }
});

// ---- Weights live total ----
function recalcWeightsTotal(scopeId) {
  const scope = document.getElementById(scopeId);
  if (!scope) return;
  const inputs = scope.querySelectorAll('.weight-input');
  let total = 0;
  inputs.forEach(i => total += (parseFloat(i.value) || 0));
  total = Math.round(total * 100) / 100;

  const lecTotal = (parseFloat(scope.querySelector('[data-w=class_standing]')?.value) || 0)
                 + (parseFloat(scope.querySelector('[data-w=major_exams]')?.value) || 0);
  const labTotal = (parseFloat(scope.querySelector('[data-w=performance_task]')?.value) || 0)
                 + (parseFloat(scope.querySelector('[data-w=activity_output]')?.value) || 0);

  const lecLabel = scope.querySelector('.lecture-total-label');
  const labLabel = scope.querySelector('.lab-total-label');
  if (lecLabel) lecLabel.textContent = 'Lecture — ' + lecTotal + '%';
  if (labLabel) labLabel.textContent = 'Laboratory — ' + labTotal + '%';

  const valid = total === 100 && [...inputs].every(input => input.value.trim() !== '' && Number.isFinite(Number(input.value)) && Number(input.value) >= 0 && Number(input.value) <= 100 && Math.abs(Number(input.value) * 100 - Math.round(Number(input.value) * 100)) < 0.000001);
  const footer    = scope.querySelector('.weights-footer');
  const totalTxt  = scope.querySelector('.weights-total-txt');
  const saveBtn   = scope.querySelector('.weights-save-btn');

  if (footer)   footer.className   = 'weights-footer ' + (valid ? 'valid' : 'invalid');
  if (totalTxt) totalTxt.textContent = 'Total: ' + total + '% ' + (valid ? '— Balanced ✓' : '— Use values from 0 to 100 totaling 100%');
  if (saveBtn)  saveBtn.disabled   = !valid;
}

document.addEventListener('input', (e) => {
  if (e.target.classList?.contains('weight-input')) {
    const scope = e.target.closest('[data-weights-scope]');
    if (scope) recalcWeightsTotal(scope.id);
  }
});


// Search/filter/pagination use server queries so they cover the entire class.
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-weights-scope]').forEach(scope => recalcWeightsTotal(scope.id));
  document.getElementById('clearStudentFilters')?.addEventListener('click', () => {
    document.getElementById('studentSearch').value = '';
    document.getElementById('studentStatus').value = 'all';
    document.getElementById('rosterFilters').requestSubmit();
  });
  document.getElementById('retryScoreSaves')?.addEventListener('click', () => {
    for (const [key, job] of failedScores) {
      if (job.value === '' || (Number.isFinite(Number(job.value)) && Number(job.value) >= 0 && Number(job.value) <= 100
          && Math.abs(Number(job.value) * 100 - Math.round(Number(job.value) * 100)) < 0.000001)) {
        pendingScores.set(key, job);
        failedScores.delete(key);
      }
    }
    flushScoreSaves();
  });
});

document.addEventListener('click', event => {
  const action = event.target.closest('[data-action]');
  if (action) {
    event.stopPropagation();
    const id = Number(action.dataset.id);
    if (action.dataset.action === 'remove-student') removeStudent(id, action.dataset.name);
    if (action.dataset.action === 'delete-class') deleteClass(id, action.dataset.name);
    if (action.dataset.action === 'delete-teacher') deleteTeacher(id, action.dataset.name);
    if (action.dataset.action === 'edit-teacher') openTeacherModal(JSON.parse(action.dataset.teacher));
    return;
  }
  const card = event.target.closest('[data-class-url]');
  if (card) location.href = card.dataset.classUrl;
});
document.addEventListener('keydown', event => {
  if (event.target.matches('[data-class-url]') && (event.key === 'Enter' || event.key === ' ')) {
    event.preventDefault();
    location.href = event.target.dataset.classUrl;
  }
});

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
  const fullUrl = getBaseUrl() + url;
  const res = await fetch(fullUrl, { method: 'POST', body });
  let json;
  try { json = await res.json(); } catch (e) { json = { ok: false, message: 'Unexpected server response.' }; }
  return json;
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

// ---- Debounced score save (grade sheet) ----
let scoreSaveTimers = {};
function queueScoreSave(inputEl) {
  const studentId = inputEl.dataset.studentId;
  const field     = inputEl.dataset.field;
  const key       = studentId + ':' + field;
  clearTimeout(scoreSaveTimers[key]);
  scoreSaveTimers[key] = setTimeout(() => saveScore(inputEl), 500);
}

async function saveScore(inputEl) {
  let value = parseFloat(inputEl.value);
  if (isNaN(value)) value = 0;
  value = Math.max(0, Math.min(100, value));
  inputEl.value = value;

  const studentId = inputEl.dataset.studentId;
  const field     = inputEl.dataset.field;
  const classId   = inputEl.dataset.classId;

  const res = await apiPost('/api/save_score.php', {
    class_id: classId, student_id: studentId, field: field, value: value
  });

  if (res.ok) {
    updateRowComputed(studentId, res.grade);
    showToast('Saved');
  } else {
    showToast(res.message || 'Failed to save score.', true);
  }
}

function updateRowComputed(studentId, g) {
  const row = document.querySelector('tr[data-row-id="' + studentId + '"]');
  if (row) {
    row.querySelector('.js-lecture')?.replaceChildren(document.createTextNode(g.lectureGrade));
    row.querySelector('.js-lab')?.replaceChildren(document.createTextNode(g.labGrade));
    row.querySelector('.js-final')?.replaceChildren(document.createTextNode(g.finalPercent + '%'));
    row.querySelector('.js-gpa')?.replaceChildren(document.createTextNode(Number(g.gpa).toFixed(2)));
    const remarks = row.querySelector('.js-remarks');
    if (remarks) {
      remarks.textContent = g.passed ? 'PASSED' : 'FAILED';
      remarks.className   = 'badge js-remarks ' + (g.passed ? 'badge-pass' : 'badge-fail');
    }
  }
  const card = document.querySelector('.stu-card[data-row-id="' + studentId + '"]');
  if (card) {
    card.querySelector('.js-lecture')?.replaceChildren(document.createTextNode(g.lectureGrade));
    card.querySelector('.js-lab')?.replaceChildren(document.createTextNode(g.labGrade));
    card.querySelector('.js-final')?.replaceChildren(document.createTextNode(g.finalPercent + '%'));
    card.querySelector('.js-gpa')?.replaceChildren(document.createTextNode(Number(g.gpa).toFixed(2)));
    const remarks = card.querySelector('.js-remarks');
    if (remarks) {
      remarks.textContent = g.passed ? 'PASSED' : 'FAILED';
      remarks.className   = 'badge js-remarks ' + (g.passed ? 'badge-pass' : 'badge-fail');
    }
  }
}

document.addEventListener('input', (e) => {
  if (e.target.classList?.contains('score-input')) queueScoreSave(e.target);
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

  const valid     = total === 100;
  const footer    = scope.querySelector('.weights-footer');
  const totalTxt  = scope.querySelector('.weights-total-txt');
  const saveBtn   = scope.querySelector('.weights-save-btn');

  if (footer)   footer.className   = 'weights-footer ' + (valid ? 'valid' : 'invalid');
  if (totalTxt) totalTxt.textContent = 'Total: ' + total + '% ' + (valid ? '— Balanced ✓' : '— Must equal exactly 100%');
  if (saveBtn)  saveBtn.disabled   = !valid;
}

document.addEventListener('input', (e) => {
  if (e.target.classList?.contains('weight-input')) {
    const scope = e.target.closest('[data-weights-scope]');
    if (scope) recalcWeightsTotal(scope.id);
  }
});

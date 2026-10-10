const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const shared = fs.readFileSync(path.join(__dirname, '../assets/js/app.js'), 'utf8');
function load(file) {
  const dom = new JSDOM(fs.readFileSync(file, 'utf8'), { runScripts: 'outside-only', url: 'http://127.0.0.1:8000' });
  for (const script of dom.window.document.querySelectorAll('script:not([src])')) dom.window.eval(script.textContent);
  dom.window.eval(shared);
  dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));
  return dom;
}
const tick = () => new Promise(resolve => setImmediate(resolve));
(async () => {
  const dom = load('/tmp/grading-class-test.html');
  const w = dom.window, d = w.document;
  try {
    const requests = [];
    w.fetch = (url, options) => new Promise((resolve, reject) => requests.push({ url, options, resolve, reject }));
    const inputs = [...d.querySelectorAll('.score-input[data-student-id="1"][data-field="quiz"]')];
    assert.equal(inputs.length, 2);
    function edit(value) { inputs[0].value = value; inputs[0].dispatchEvent(new w.Event('input', { bubbles: true })); }
    function answer(request, value, percent) {
      request.resolve({ ok: true, json: async () => ({ ok: true, value,
        grade: { lectureGrade: 50, labGrade: 30, finalPercent: percent, gpa: 2, passed: true, status: 'passed' },
        statistics: { average: percent, passRate: 100, passed: 6, failed: 0, incomplete: 0 } }) });
    }
    edit('90');
    assert.equal(inputs[1].value, '90', 'Mobile input mirrors desktop');
    const event = new w.Event('beforeunload', { cancelable: true });
    w.dispatchEvent(event);
    assert.equal(event.defaultPrevented, true, 'Unsaved edits guard navigation');
    const running = w.flushScoreSaves();
    assert.equal(requests.length, 1);
    edit('95');
    w.flushScoreSaves();
    assert.equal(requests.length, 1, 'Only one request may be in flight');
    const originalFinal = d.querySelector('tr[data-row-id="1"] .js-final').textContent;
    answer(requests[0], 90, 80);
    await tick();
    assert.equal(requests.length, 2);
    assert.equal(inputs[0].value, '95', 'Older response must not overwrite a newer edit');
    assert.equal(d.querySelector('tr[data-row-id="1"] .js-final').textContent, originalFinal);
    answer(requests[1], 95, 85);
    await running;
    assert.equal(d.querySelector('tr[data-row-id="1"] .js-final').textContent, '85%');
    assert.equal(d.querySelector('.stu-card[data-row-id="1"] .js-final').textContent, '85%');
    assert.equal(d.getElementById('statAverage').textContent, '85.00%');
    assert.equal(w.hasUnsavedScores(), false);
    edit('96');
    const failed = w.flushScoreSaves();
    requests[2].reject(new Error('offline'));
    await failed;
    assert.equal(w.hasUnsavedScores(), true);
    assert.equal(d.getElementById('retryScoreSaves').hidden, false);
    assert.match(d.querySelector('[data-save-status]').textContent, /Unsaved/);
    d.getElementById('retryScoreSaves').click();
    assert.equal(requests.length, 4);
    answer(requests[3], 96, 86);
    await tick();
    assert.equal(w.hasUnsavedScores(), false);
    edit('');
    const incomplete = w.flushScoreSaves();
    requests[4].resolve({ ok: true, json: async () => ({ ok: true, value: null,
      grade: { lectureGrade: null, labGrade: 30, finalPercent: null, gpa: null, passed: null, status: 'incomplete' },
      statistics: { average: 86, passRate: 100, passed: 5, failed: 0, incomplete: 1 } }) });
    await incomplete;
    assert.equal(d.querySelector('tr[data-row-id="1"] .js-remarks').textContent, 'INCOMPLETE');
    assert.equal(d.querySelector('.stu-card[data-row-id="1"] .js-final').textContent, '—');
    assert.equal(d.getElementById('statIncomplete').textContent, '1');
    // Attribute payloads are dispatched as data, not interpreted JavaScript.
    let removal;
    w.removeStudent = (...args) => { removal = args; };
    d.querySelector('[data-action="remove-student"]').click();
    assert.deepEqual(removal, [1, 'Aguilar, Mariz S.']);
    // Table's two header rows cover exactly the same 14 columns as its body.
    const top = [...d.querySelectorAll('#gradeTable thead .r1 th')];
    assert.equal(top.reduce((sum, th) => sum + th.colSpan, 0), 14);
    assert.equal(d.querySelectorAll('#gradeTable tbody tr:first-child td').length, 14);
    const scope = d.createElement('div');
    scope.id = 'testWeights'; scope.dataset.weightsScope = '';
    scope.innerHTML = '<input class="weight-input" value="-10"><input class="weight-input" value="110"><button class="weights-save-btn"></button>';
    d.body.append(scope);
    w.recalcWeightsTotal('testWeights');
    assert.equal(scope.querySelector('button').disabled, true);
  } finally { w.close(); }
  const admin = load('/tmp/grading-admin-test.html');
  try {
    const w = admin.window, d = w.document;
    let shown = false;
    w.bootstrap = { Modal: class { show() { shown = true; } } };
    const button = [...d.querySelectorAll('[data-action="edit-teacher"]')].find(b => b.dataset.teacher.includes('review_test_'));
    button.click();
    assert.equal(shown, true);
    assert.equal(w.reviewInjected, undefined);
    assert.match(d.getElementById('t_name').value, /O'Neil/);
    assert.equal(d.getElementById('t_password').value, '');
  } finally { admin.window.close(); }
  for (const file of ['/tmp/grading-class-weights-test.html', '/tmp/grading-admin-weights-test.html']) {
    const weights = load(file);
    try {
      assert.equal(weights.window.document.querySelector('.weights-save-btn').disabled, false);
      assert.match(weights.window.document.querySelector('.weights-total-txt').textContent, /Balanced/);
    } finally { weights.window.close(); }
  }
  console.log('PASS: save serialization, stale responses, offline retry, navigation guard, mobile synchronization, incomplete statistics, safe actions, modal initialization, weight controls, and table columns');
})().catch(error => { console.error(error); process.exitCode = 1; });

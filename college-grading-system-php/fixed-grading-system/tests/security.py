"""Run only on the seeded local development database. Temporary fixtures are deleted."""
import csv
import html
import http.cookiejar
import io
import json
import os
import re
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

BASE = os.environ.get('GRADING_TEST_URL', 'http://127.0.0.1:8000')
class Client:
    def __init__(self):
        self.http = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.csrf = None
    def request(self, path, data=None):
        body = None if data is None else urllib.parse.urlencode(data).encode()
        try:
            response = self.http.open(BASE + path, body)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            text = response.read().decode('utf-8-sig')
            return response.status, text, response.geturl(), response.headers
    def login(self, username, password):
        _, page, _, _ = self.request('/index.php')
        token = re.search(r'name="csrf_token" value="([^"]+)"', page).group(1)
        status, page, url, headers = self.request('/index.php', dict(username=username, password=password, csrf_token=token))
        meta = re.search(r'name="csrf-token" content="([^"]+)"', page)
        if meta: self.csrf = meta.group(1)
        return status, page, url, headers
    def post(self, path, **data):
        code, body, _, _ = self.request('/api/' + path + '.php', dict(data, csrf_token=self.csrf))
        return code, json.loads(body)

admin = Client()
assert admin.login('admin', 'admin123')[2].endswith('/admin/dashboard.php')
username = 'review_test_' + os.urandom(4).hex()
password = 'Temporary-Test-123!'
name = 'O\'Neil "<script>window.reviewInjected=true</script>'
teacher_id = None
try:
    assert admin.post('teacher_save', name=name, username=username, password=password, email='', department='Testing')[1]['ok']
    page = admin.request('/admin/dashboard.php?page=teachers')[1]
    assert 'password_hash' not in page and '$2y$' not in page
    assert '<script>window.reviewInjected=true</script>' not in page
    teachers = [json.loads(html.unescape(value)) for value in re.findall(r'data-teacher="([^"]+)"', page)]
    teacher_id = next(t['id'] for t in teachers if t['username'] == username)
    Path('/tmp/grading-admin-test.html').write_text(page)
    teacher = Client()
    assert teacher.login(username, password)[2].endswith('/teacher/dashboard.php')
    result = teacher.post('class_create', code='TEST', subject='Regression', section='A', school_year='2026-2027', semester='1st Semester')[1]
    assert result['ok'], result
    class_id = int(result['class_id'])
    for path in ['class_weights_save', 'default_weights_save']:
        client = teacher if path.startswith('class') else admin
        for value in ['-10', '101', 'broken', '1e309', '20.001']:
            result = client.post(path, class_id=class_id, class_standing=value, major_exams=60, performance_task=20, activity_output=30)[1]
            assert not result['ok'], (path, value, result)
    Path('/tmp/grading-class-weights-test.html').write_text(teacher.request(f'/teacher/class.php?id={class_id}&tab=weights')[1])
    Path('/tmp/grading-admin-weights-test.html').write_text(admin.request('/admin/dashboard.php?page=weights')[1])
    assert teacher.request('/api/student_add.php')[0] == 405
    assert teacher.request('/api/student_add.php', dict(class_id=class_id, name='No token'))[0] == 403
    student = teacher.post('student_add', class_id=class_id, name='=1+1', student_id='REVIEW-ID')[1]
    assert student['ok'], student
    student_id = student['id']
    assert teacher.post('student_add', class_id=class_id, name='duplicate', student_id='REVIEW-ID')[0] == 409
    page = teacher.request(f'/teacher/class.php?id={class_id}')[1]
    assert 'INCOMPLETE' in page
    fields = ['quiz', 'oral', 'attendance', 'prelim', 'final_exam', 'performance_task', 'activity_output']
    for field in fields:
        result = teacher.post('save_score', class_id=class_id, student_id=student_id, field=field, value='0')[1]
        assert result['ok'], result
    assert result['grade']['status'] == 'failed' and result['statistics']['failed'] == 1
    result = teacher.post('save_score', class_id=class_id, student_id=student_id, field='quiz', value='')[1]
    assert result['grade']['status'] == 'incomplete' and result['statistics']['failed'] == 0
    assert result['statistics']['average'] is None
    for value in ['-1', '101', 'bad', '1e309', '1.001']:
        assert teacher.post('save_score', class_id=class_id, student_id=student_id, field='quiz', value=value)[0] == 422
    csv_body = teacher.request(f'/api/export_class.php?id={class_id}')[1]
    rows = list(csv.reader(io.StringIO(csv_body)))
    assert rows[7][2] == "'=1+1" and rows[7][-1] == 'INCOMPLETE', rows[7]
    for i in range(51):
        assert teacher.post('student_add', class_id=class_id, name=f'Page Student {i:02}', student_id=f'PAGE-{i:02}')[1]['ok']
    page1 = teacher.request(f'/teacher/class.php?id={class_id}')[1]
    page2 = teacher.request(f'/teacher/class.php?id={class_id}&roster_page=2')[1]
    assert len(re.findall(r'<tr data-row-id=', page1)) == 50
    assert len(re.findall(r'<tr data-row-id=', page2)) == 2
    searched = teacher.request(f'/teacher/class.php?id={class_id}&q=PAGE-50&status=incomplete')[1]
    assert len(re.findall(r'<tr data-row-id=', searched)) == 1 and 'Page Student 50' in searched
    assert 'No students match' in teacher.request(f'/teacher/class.php?id={class_id}&status=passed')[1]
    # Account disabling must invalidate an existing authenticated API session.
    assert admin.post('teacher_toggle', id=teacher_id)[1]['status'] == 'disabled'
    assert teacher.post('student_add', class_id=class_id, name='Blocked', student_id='BLOCKED')[0] == 401
    assert admin.post('teacher_toggle', id=teacher_id)[1]['status'] == 'active'
    active_teacher = Client()
    assert active_teacher.login(username, password)[2].endswith('/teacher/dashboard.php')
    # A new session must not bypass account-level throttling.
    for _ in range(5):
        assert Client().login(username, 'incorrect')[0] == 200
    limited = Client().login(username, password)
    assert limited[0] == 429 and int(limited[3]['Retry-After']) > 0
    assert admin.post('teacher_delete', id=teacher_id)[1]['ok']
    teacher_id = None
    assert active_teacher.post('student_add', class_id=class_id, name='Deleted account')[0] == 401
    print('PASS: attribute escaping, private teacher fields, weights/scores, incomplete grades, CSV formulas, duplicate IDs, pagination/search, session revocation, and login throttling')
finally:
    if teacher_id is not None:
        assert admin.post('teacher_delete', id=teacher_id)[1]['ok'], 'Temporary teacher cleanup failed'

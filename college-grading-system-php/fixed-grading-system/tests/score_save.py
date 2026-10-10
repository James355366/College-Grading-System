"""Integration regression: run against the seeded local development database."""
import csv
import http.cookiejar
import io
import json
import os
import re
import urllib.parse
import urllib.request
from pathlib import Path

base = os.environ.get('GRADING_TEST_URL', 'http://127.0.0.1:8000')
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def get(path):
    with client.open(base + path) as response:
        return response.read().decode('utf-8-sig')

page = get('/index.php')
token = re.search(r'name="csrf_token" value="([^"]+)"', page).group(1)
data = urllib.parse.urlencode(dict(username='teacher', password='teacher123', csrf_token=token)).encode()
with client.open(base + '/index.php', data) as response:
    assert response.geturl().endswith('/teacher/dashboard.php'), 'Demo login failed'
page = get('/teacher/class.php?id=1')
Path('/tmp/grading-class-test.html').write_text(page)
token = re.search(r'name="csrf-token" content="([^"]+)"', page).group(1)
rows = list(csv.reader(io.StringIO(get('/api/export_class.php?id=1'))))
assert rows[7][1] == '2023-00123', 'Requires the seeded development class'
fields = ['quiz', 'oral', 'attendance', 'prelim', 'final_exam', 'performance_task', 'activity_output']
original = dict(zip(fields, map(float, [rows[7][i] for i in (3, 4, 5, 7, 8, 11, 12)])))

def save(field, value, class_id=1):
    body = urllib.parse.urlencode(dict(class_id=class_id, student_id=1, field=field, value=value, csrf_token=token)).encode()
    with client.open(base + '/api/save_score.php', body) as response:
        return json.load(response)

def expected(scores):
    return round(sum(scores[f] for f in ('quiz', 'oral', 'attendance')) / 3 * .2
                 + (scores['prelim'] + scores['final_exam']) / 2 * .4
                 + scores['performance_task'] * .2 + scores['activity_output'] * .2, 2)

scores = original.copy()
try:
    for field, value in [('quiz', original['quiz']), ('quiz', 100), ('performance_task', 100), ('activity_output', 0)]:
        scores[field] = value
        result = save(field, value)
        assert result['ok'], result
        assert result['grade']['finalPercent'] == expected(scores), result
        assert result['grade']['passed'] == (expected(scores) >= 75), result
        assert result['grade']['performanceTask'] == scores['performance_task'], result
        assert result['grade']['activityOutput'] == scores['activity_output'], result
        exported = list(csv.reader(io.StringIO(get('/api/export_class.php?id=1'))))
        assert float(exported[7][14]) == result['grade']['finalPercent'], exported[7]
    assert not save('quiz', 100, class_id=2)['ok'], 'Wrong-class update must be denied'
    print('PASS: unchanged, quiz, lab-score updates, export agreement, and class ownership')
finally:
    for field in ('quiz', 'performance_task', 'activity_output'):
        assert save(field, original[field])['ok'], 'Could not restore original demo score'

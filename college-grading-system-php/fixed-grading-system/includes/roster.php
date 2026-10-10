<?php
// SQL NULL propagates missing assessments; fixed-point arithmetic matches compute_grade().
function final_grade_sql(): string {
    return 'ROUND(((s.quiz + s.oral + s.attendance) / 3 * c.class_standing
        + (s.prelim + s.final_exam) / 2 * c.major_exams
        + s.performance_task * c.performance_task + s.activity_output * c.activity_output) / 100, 2)';
}

// One aggregate query for the selected classes, with no student rows loaded into PHP.
function class_statistics(PDO $pdo, array $classIds): array {
    if (!$classIds) return [];
    $marks = implode(',', array_fill(0, count($classIds), '?'));
    $grade = final_grade_sql();
    $stmt = $pdo->prepare("SELECT c.id, COUNT(s.id) AS total,
        COUNT($grade) AS completed, COALESCE(SUM($grade >= 75), 0) AS passed,
        COALESCE(SUM($grade < 75), 0) AS failed, ROUND(AVG($grade), 2) AS average
        FROM classes c LEFT JOIN students s ON s.class_id = c.id
        WHERE c.id IN ($marks) GROUP BY c.id");
    $stmt->execute($classIds);
    $result = [];
    foreach ($stmt as $row) {
        foreach (['total', 'completed', 'passed', 'failed'] as $key) $row[$key] = (int)$row[$key];
        $row['incomplete'] = $row['total'] - $row['completed'];
        $row['passRate'] = $row['completed'] ? round($row['passed'] / $row['completed'] * 100, 1) : null;
        $row['average'] = $row['average'] === null ? null : (float)$row['average'];
        $result[$row['id']] = $row;
    }
    return $result;
}

<?php
/**
 * Grading logic — ported from src/utils/grading.ts
 * Philippine CHED-standard transmutation, 75% passing floor.
 */

function weights_total(array $w): float {
    return (float)($w['class_standing'] ?? 0) + (float)($w['major_exams'] ?? 0)
         + (float)($w['performance_task'] ?? 0) + (float)($w['activity_output'] ?? 0);
}

function is_valid_weights(array $w): bool {
    return round(weights_total($w), 2) == 100.0;
}

function lecture_portion(array $w): float {
    return (float)($w['class_standing'] ?? 0) + (float)($w['major_exams'] ?? 0);
}

function lab_portion(array $w): float {
    return (float)($w['performance_task'] ?? 0) + (float)($w['activity_output'] ?? 0);
}

/**
 * Percent -> Grade Point (GPA) scale
 * 97-100=1.0, 93-96=1.25, 89-92=1.5, 85-88=1.75, 81-84=2.0,
 * 77-80=2.25, 76=2.75, 75=3.0 (lowest passing), <75=5.0 FAILED
 */
function percent_to_gpa(float $percent): float {
    $p = round($percent, 2);
    if ($p >= 97) return 1.0;
    if ($p >= 93) return 1.25;
    if ($p >= 89) return 1.5;
    if ($p >= 85) return 1.75;
    if ($p >= 81) return 2.0;
    if ($p >= 77) return 2.25;
    if ($p >= 76) return 2.75;
    if ($p >= 75) return 3.0;
    return 5.0;
}

function is_passing(float $percent): bool {
    return $percent >= 75;
}

function avg_scores(array $nums): float {
    $valid = array_map(fn($n) => (float)($n ?? 0), $nums);
    if (count($valid) === 0) return 0.0;
    return array_sum($valid) / count($valid);
}

function round1(float $n): float {
    return round($n, 2);
}

/**
 * Compute the full grade breakdown for a student row given class weights.
 * $scores keys: quiz, oral, attendance, prelim, final_exam, performance_task, activity_output
 * $weights keys: class_standing, major_exams, performance_task, activity_output
 */
function compute_grade(array $scores, array $weights): array {
    $classStanding = avg_scores([$scores['quiz'], $scores['oral'], $scores['attendance']]);
    $majorExams    = avg_scores([$scores['prelim'], $scores['final_exam']]);
    $performanceTask = (float)($scores['performance_task'] ?? 0);
    $activityOutput  = (float)($scores['activity_output'] ?? 0);

    $lectureGrade = ($classStanding * (float)($weights['class_standing'] ?? 0)) / 100
                  + ($majorExams * (float)($weights['major_exams'] ?? 0)) / 100;

    $labGrade = ($performanceTask * (float)($weights['performance_task'] ?? 0)) / 100
              + ($activityOutput * (float)($weights['activity_output'] ?? 0)) / 100;

    $finalPercent = round($lectureGrade + $labGrade, 2);
    $gpa = percent_to_gpa($finalPercent);
    $passed = is_passing($finalPercent);

    return [
        'classStanding'   => round1($classStanding),
        'majorExams'      => round1($majorExams),
        'lectureGrade'    => round1($lectureGrade),
        'performanceTask' => round1($performanceTask),
        'activityOutput'  => round1($activityOutput),
        'labGrade'        => round1($labGrade),
        'finalPercent'    => $finalPercent,
        'gpa'             => $gpa,
        'passed'          => $passed,
    ];
}

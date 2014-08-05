<?php

header('Content-type: text/csv');
header('Content-Disposition: attachment; filename=' . $assignment_id . '_grades.csv');
header('Pragma: no-cache');
header('Expires: 0');

echo "user_id, total, grade_raw, grade_adjusted, submission_id" . PHP_EOL;

foreach ($records as $r) {
	
	$user_id = addslashes($r['user_id']);
	$grade_raw = intval($r['grade']);
	$grade_adj = intval($r['grade_adjustment']);
	$grade_total = $grade_raw + $grade_adj;
	
	$submission_id = $r['id'];
	
	echo "$user_id, $grade_total, $grade_raw, $grade_adj, $submission_id" . PHP_EOL;
}


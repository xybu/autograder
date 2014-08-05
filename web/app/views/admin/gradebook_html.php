<?php
header('Content-type: text/html');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="dns-prefetch" href="//cdnjs.cloudflare.com/">
		<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootswatch/3.2.0+1/yeti/bootstrap.min.css" />
		<!--[if lt IE 9]>
			<script src="//cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7/html5shiv.min.js"></script>
			<script src="//cdnjs.cloudflare.com/ajax/libs/respond.js/1.4.2/respond.min.js"></script>
		<![endif]-->
		<title><?php echo "Grades for $assignment_id"?></title>
	</head>
	<body>
		<div class="container">
			<article class="row">
				<h1 class="page-header"><?php echo $assignment_id?></h1>
				<p><strong><?php echo count($records)?></strong> records found.</p>
				<table class="table table-hover table-bordered">
					<thead><tr><td>user_id</td><td>total</td><td>raw grade</td><td>&Delta; grade</td><td>submission_id</td><td>detail</td></tr></thead>
					<tbody>			
<?php
foreach ($records as $r) {
	
	$submission_id = $r['id'];
	$user_id = addslashes($r['user_id']);
	$grade_raw = intval($r['grade']);
	$grade_adj = intval($r['grade_adjustment']);
	$grade_total = $grade_raw + $grade_adj;
	$grade_detail = $r['grade_detail'];
	
	if (!empty($grade_detail)) {
		$grade_detail_data = json_decode(html_entity_decode($grade_detail), true);
		
		if ($grade_detail_data !== null && ksort($grade_detail_data)) {
			unset($grade_detail_data['max']);
			$grade_detail = implode(', ', array_values($grade_detail_data));
		}
	} else $grade_detail = "(empty)";
	
	echo "\t\t\t\t\t\t<tr><td>$user_id</td><td>$grade_total</td><td>$grade_raw</td><td>$grade_adj</td><td>$submission_id</td><td><code>$grade_detail</code></td></tr>" . PHP_EOL;
}
?>
					</tbody>
				</table>
			</article>
		</div>
	</body>
</html>

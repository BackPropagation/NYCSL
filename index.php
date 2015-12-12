<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<meta name="description" content="">
	<meta name="author" content="">

	<title>Home</title>

	<link href="lib/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="lib/katex.min.css">

	<link href="style/general.css" rel="stylesheet">
	<link href="style/index.css" rel="stylesheet">
</head>

<body>
	<div id="wrap">
		<div class="container">
			<?php include 'includes/navbar.php'; ?>

			<div class="jumbotron">
				<h1 id="jHeader">TSP</h1>
				<p id="jParagraph">Herp derp.</p>
			</div>
			<div class="row">
				<div class="col-sm-5">
					<div class="panel panel-primary">
						<div class="panel-heading">
							<h3 class="panel-title">Rules</h3>
						</div>
						<div class="panel-body" id="rulesPanelBody">
							<?php include 'problems/descriptions/TSP.html'; ?>
						</div>
					</div>
				</div>
				<div class="col-sm-7">
					<?php include 'includes/leaderboard.php' ?>
				</div>
			</div>
		</div>
	</div>

	<?php include 'includes/footer.php'; ?>

	<script src="lib/jquery.min.js"></script>
	<script src="lib/bootstrap.min.js"></script>
	<script src="lib/katex.min.js"></script>
	<script src="lib/auto-render.min.js"></script>
	<script src="script/backend.js"></script>
	<script src="script/general.js"></script>
</body>
</html>

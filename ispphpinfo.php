<?php
	echo(date('Y-m-d H:i:s'));
	session_start();
?><html>
	<head>
		<title>PHP Test</title>
	</head>
	<body>
		<!-- testing sessions -->
		<table border="1">
		<tbody>
		<tr><td colspan="2"><?php print_r(microtime(true))?></td></tr>
		<tr><td>useragent test:</td><td><?php echo ($_SERVER['HTTP_USER_AGENT']);?></td></tr>
		<tr><td>session test:</td><td><?php echo(session_id())?></td></tr>
	 	<tr><td>phpinfo test:</td><td><?php phpinfo(); ?></td></tr>
		</tbody>
		</table>
	</body>
</html>

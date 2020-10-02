<?php

// connect to DB
$dbConnection =  new mysqli(getenv('DBHOST'), getenv('DBUSR'), getenv('DBPASS'), getenv('DBSCHEMA'), getenv('DBPORT'));
// if we cant connect, respond with server error
if ($dbConnection->connect_error) {
	header("HTTP/1.1 503 Site Unavailable");
	echo "Site temporarily unavailable.";
	exit;
}

// check to see if we were posted to
$tmpOrigin = '';
if(isset($_POST['tmpOrigin'])) {
	$tmpOrigin = $_POST['tmpOrigin'];
}

if(! filter_var($tmpOrigin, FILTER_VALIDATE_URL)) {
	header('Content-Type: text/html; charset=UTF-8');
	?>
	<!DOCTYPE html><html>
		<body>
			<div style="margin: 1em;">
				Please enter origin with schema (http://www.example.com) <br><br>
				<form method="post">
					<input type="text" name="tmpOrigin" id="tmpOrigin" /> 
					<input type="submit" value="Set Origin" />
				</form>
			</div>
		</body>
	</html>
	<?php
} else {
	// build our origin from the parts
	$u = parse_url($tmpOrigin);
	$myOrigin = $u['scheme'] . '://';
	if(isset($u['user']) && isset($u['pass'])) { $myOrigin .= $u['user'] . ':' . $u['pass'] . '@'; }
	$myOrigin .= $u['host'] . (isset($u['port'])?':'.$u['port']:'') . '/';

	// insert origin into DB
	$dbConnection->query("insert into cache.info (origin) value ('$myOrigin');");

	// queue up first page
	$dbConnection->query("insert into cache.queue (payload) value ('/');");

	header('Refresh: 5;');
	header('Content-Type: text/html; charset=UTF-8');
	echo "Origin set to <b>" . $myOrigin . "</b></br></br>Page will automatically refresh.";

}


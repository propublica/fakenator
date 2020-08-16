<?php

// connect to DB
$dbConnection =  new mysqli(getenv('DBHOST'), getenv('DBUSR'), getenv('DBPASS'), getenv('DBSCHEMA'), getenv('DBPORT'));
if ($dbConnection->connect_error) {
	echo "houston, we have a problem.\n";
	exit;
}

// find the first five queued items.
// this way our process wont run too long, and we will set a cron to run it fairly frequently.
if(! $myDbResults = $dbConnection->query("select id, payload from cache.queue order by id limit 0, 5") ) {
	// if we err'ed out here, we likely dont have a "cache" schema
	echo "Error running query against cache database. If the database has not been set up, run:\n\nbash bin/startUp.sh\n\n";
	exit;
}

// loop the results
while($queueRecord = $myDbResults->fetch_assoc()) {
	$key = $queueRecord['payload'];

	// generate the HTML & header
	$myPage = getHtml($key);

	// dummy check to make sure our origin is responding for that page
	if(! isset($myPage['info']) || ! isset($myPage['info']['http_code']) || $myPage['info']['http_code'] >= 500)  {
		continue;
	}

	// prepare data to write to DB (and preserve newlines)
	$tmpHeader  = explode("\n",$myPage['header']);
	for($i=0; $i<count($tmpHeader); $i++) { $tmpHeader[$i] = $dbConnection->real_escape_string($tmpHeader[$i]); }
  	$myHeader = implode("\n",$tmpHeader);
	$myHtml = $dbConnection->real_escape_string($myPage['html']);

	// set expiry for content -- 1 hour from now
	$myExpiry = (new DateTime())->modify("+1 hour")->format('YmdHis');

	// write to back to db - query prep
	// first check to see if we have the key?
	$myDbResults = $dbConnection->query("select * from cache.dataStore where `key` = '$key'");
	if($cacheRecord = $myDbResults->fetch_assoc()) {
		// if we have results, then we update
		$query = "update cache.dataStore set `html` = '$myHtml', `header` = '$myHeader', `expiry` = '$myExpiry' where `key` = '$key'";
	} else {
		// if we did not have data we insert
		$query = "insert into cache.dataStore (`key`,`html`,`header`,`expiry`) value ('$key','$myHtml','$myHeader','$myExpiry')";
	}

	// actual write to DB
	if( $dbConnection->query($query) ) {
		// if successful, delete item from queue;
		$dbConnection->query("delete from cache.queue where id = {$queueRecord['id']}");
	}

}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
// helper functions
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //

function getHtml($key) {
	
	// get  URL from key
	$myOrigin = 'https://www.propublica.org/';
	$myUrl = $myOrigin . $key;

	// generate HTML & header
	$ch = curl_init();
	$myHeader = [];
	curl_setopt($ch, CURLOPT_URL, $myUrl);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HEADERFUNCTION,
		function($curl, $header) use (&$myHeader) {
			$len = strlen($header);
			$header = explode(':', $header, 2);
			if (count($header) < 2) // ignore invalid headers
				return $len;

			$myHeader[] = strtolower(trim($header[0])) . ':' . trim($header[1]);

    		return $len;
  		}
  	);
  	$myHtml = curl_exec($ch);
	$myInfo = curl_getinfo($ch);

	return [ 'header' => implode("\n",$myHeader), 'html' => $myHtml, 'info' => $myInfo ];

  	// prep vars for sql
}

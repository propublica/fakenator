<?php

// connect to DB
$dbConnection =  new mysqli(getenv('DBHOST'), getenv('DBUSR'), getenv('DBPASS'), getenv('DBSCHEMA'), getenv('DBPORT'));
if ($dbConnection->connect_error) {
	echo "unable to connect to database server.\n";
	exit;
}

// find the first five queued items.
// this way our process wont run too long, and we will set a cron to run it fairly frequently.
if(! $myDbResults = $dbConnection->query("select id, payload from cache.queue order by id limit 0, 5") ) {
	// if we err'ed out here, we likely dont have a "cache" schema, so lets build it.
	echo "Running  start-up DB  script...\n\n";

	// grab script text
	$createScript = file_get_contents(dirname(__DIR__) . '/createTables.sql');

	// break up by query & run each
	$lines = explode(";\n",$createScript);
	foreach($lines as $query) { 
		if( $query ) { $dbConnection->query($query); } 
	}

	exit;
}

// loop the results
while($queueRecord = $myDbResults->fetch_assoc()) {
	$key = $queueRecord['payload'];

	// check expiry
	if( $myDbResults = $dbConnection->query("select expiry from cache.dataStore where `key` = '$key'")) {
		$now = date('YmdHis');
		$recordExpiry = $myDbResults->fetch_assoc()['expiry'];
		// check to see if we have valid cache
		if($now < $recordExpiry)  {
			// remove row from queue and move to next.
			$dbConnection->query("delete from cache.queue where id = {$queueRecord['id']}");
			continue;
		}
	}

	// generate the HTML & header
	$myPage = getHtml($key);

	// dummy check to make sure our origin is responding for that page
	if(! isset($myPage['info']) || ! isset($myPage['info']['http_code']) || $myPage['info']['http_code'] >= 500)  {
		// if item is 5xx at the origin, lets remove it from the queue, so we dont hammer the origin with a bunch of requests for a bad page.
		if($myPage['info']['http_code'] >= 500) {
			$dbConnection->query("delete from cache.queue where id = {$queueRecord['id']}");
		}
		continue;
	}

	// prepare data to write to DB (and preserve newlines in the header 'real_escape_string' strips them out)
	$tmpHeader  = explode("\n",$myPage['header']);
	for($i=0; $i<count($tmpHeader); $i++) { $tmpHeader[$i] = $dbConnection->real_escape_string($tmpHeader[$i]); }
  	$myHeader = implode("\n",$tmpHeader);
	$myHtml = $dbConnection->real_escape_string($myPage['html']);

	// set expiry for content -- 1 hour from now
	//   we could get fancier here, by adjusting the expiry length per http response, or content type, etc.
	$myExpiry = (new DateTime())->modify("+1 hour")->format('YmdHis');

	// write to db - query prep
	//   first check to see if we have the key?
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
		echo  "cache generated for: " . $key . "\n";
	}

}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
// helper functions
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //

function getHtml($key) {
	
	// get  URL from key
	$myOrigin = 'https://www.propublica.org/';  // WITH TRAILING SLASHH
	$myUrl = $myOrigin . $key;

	// generate HTML & header
	$ch = curl_init();
	$tmpHeader = [];
	curl_setopt($ch, CURLOPT_URL, $myUrl);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HEADERFUNCTION,
		function($curl, $header) use (&$tmpHeader, &$myOrigin) {
			$len = strlen($header);
			$header = explode(':', $header, 2);

			// ignore invalid headers & "date" header
			if(count($header) < 2 || $header[0] == 'date')
				return $len;

			// normalize values
			for($i=0; $i<count($header); $i++) { 
				$header[$i] = strtolower(trim($header[$i])); 
			}

			// if we have a 'location' header, lets parse out the origin if its there, so we point to our caching layer.
			if($header[0] == 'location')
				$header[1] = str_replace( $myOrigin , '/', $header[1]);

			$tmpHeader[] = $header[0] . ': ' . $header[1];
    			return $len;
  		}
  	);
	$myHtml = curl_exec($ch);
	// parse out absolute URLs to origin, 
	$myHtml = preg_replace('#href="'.$myOrigin.'?#i','href="/',$myHtml);
	$myInfo = curl_getinfo($ch);
	$myHeader = "HTTP/1.1 " . $myInfo['http_code'] . "\n" . implode("\n",$tmpHeader);

	return [ 'header' => $myHeader, 'html' => $myHtml, 'info' => $myInfo ];
}

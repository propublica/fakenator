<?php

// connect to DB
$dbConnection =  new mysqli(getenv('DBHOST'), getenv('DBUSR'), getenv('DBPASS'), getenv('DBSCHEMA'), getenv('DBPORT'));
if ($dbConnection->connect_error) {
	echo "unable to connect to database server.\n";
	exit;
}

// check to make sure the schema exists
if(! $myDbResults = $dbConnection->query("select origin from cache.info limit 0, 1")) {

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

// check to make sure the origin is set
if(! $myOrigin = $myDbResults->fetch_assoc()['origin']) {
	echo "Origin is not yet set, please visit http://localhost:8888/ to set origin.\n\n";
	sleep(5);
	exit;
}


// find the first five queued items.
// this way our process wont run too long, and we will set a cron to run it fairly frequently.
if($myDbResults = $dbConnection->query("select id, payload from cache.queue order by id limit 0, 5") ) {

	// loop the results
	while($queueRecord = $myDbResults->fetch_assoc()) {
		$key = $queueRecord['payload'];

		// grab our cache record, if we have it
		$myDbResults = $dbConnection->query("select * from cache.dataStore where `key` = '$key'");
		$cacheRecord = $myDbResults->fetch_assoc();

		// if we do have cache, lets check its expiry
		if( $cacheRecord ) {
			$now = date('YmdHis');
			if($now < $cacheRecord['expiry'])  {
				// cache is still valid, so lets remove this row from queue and move to next.
				$dbConnection->query("delete from cache.queue where id = {$queueRecord['id']}");
				continue;
			}
		}

		// generate the HTML & header
		$myPage = getHtml($myOrigin, $key);

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
		for($i=0; $i<count($tmpHeader); $i++) { 
			$tmpHeader[$i] = $dbConnection->real_escape_string($tmpHeader[$i]); 
		}
  		$myHeader = implode("\n",$tmpHeader);
		$myHtml = $dbConnection->real_escape_string($myPage['html']);

		// set expiry for content -- 1 hour from now
		//   we could get fancier here, by adjusting the expiry length per http response, or content type, etc.
		$myExpiry = (new DateTime())->modify("+1 hour")->format('YmdHis');

		// write to db - query prep
		if( $cacheRecord ) {
			// if we have cache, then we update
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
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
// helper functions
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //

function getHtml($origin, $key) {
	
	// create URL
	$myUrl = $origin . $key;

	// generate HTML & header
	$ch = curl_init();
	$tmpHeader = [];
	curl_setopt($ch, CURLOPT_URL, $myUrl);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	// handy CURLOPT which allows us to run a function on each header entry
	curl_setopt($ch, CURLOPT_HEADERFUNCTION,
		function($curl, $header) use (&$tmpHeader, &$myOrigin) {
			$len = strlen($header);
			$header = explode(':', $header, 2);

			// normalize values
			for($i=0; $i<count($header); $i++)
				$header[$i] = strtolower(trim($header[$i])); 

			// ignore invalid headers & "date" header
			if(count($header) < 2 || $header[0] == 'date')
				return $len;

			// if we have a 'location' header, lets parse out the origin if its there, so we point to our caching layer.
			if($header[0] == 'location')
				$header[1] = preg_replace('#'.$origin.'?#i',  '/', $header[1]);

			// now we load them up for retrieval later
			$tmpHeader[] = $header[0] . ': ' . $header[1];
			return $len;
  		}
  	);
	$myHtml = curl_exec($ch);
	// parse out absolute URLs to origin, 
	$myHtml = preg_replace('#href="'.$origin.'?#i','href="/',$myHtml);
	$myInfo = curl_getinfo($ch);
	$myHeader = "HTTP/1.1 " . $myInfo['http_code'] . "\n" . implode("\n",$tmpHeader);

	return [ 'header' => $myHeader, 'html' => $myHtml, 'info' => $myInfo ];
}

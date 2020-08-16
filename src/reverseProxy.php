<?php

// connect to our cache data story db
$dbConnection =  new mysqli(getenv('DBHOST'), getenv('DBUSR'), getenv('DBPASS'), getenv('DBSCHEMA'), getenv('DBPORT'));
// if we cant connect, respond with server error
if ($dbConnection->connect_error) {
	respondWith("HTTP/1.0 503 Site Unavailable", 'Site temporarily unavailable.');
	exit;
}

// use the URL as the key (ie. article/cache-rules-everything-around-me)
// $myKey =  $_SERVER['REQUEST_URI'];
if(! $myKey = htmlspecialchars($_GET["q"]) ){
	$myKey = '/';
}

// look-up in cache data store
$myDbResults = $dbConnection->query("select * from cache.dataStore where `key` = '$myKey'");

// if we have data, 
if($cacheRecord = $myDbResults->fetch_assoc()) {
	// send response
	respondWith($cacheRecord['header'], $cacheRecord['html']);

	// check expiry 
	$now = date('YmdHis');
	if($now > $cacheRecord['expiry']) {
		// if expired, add to queue
		$dbConnection->query("insert into cache.queue (payload) values ('$myKey')");
	}

// if we did not have data, serve a 404 and queue page to get generated
} else {
	// send response
	respondWith("HTTP/1.0 404 Not Found\nRefresh: 15;", 'Page Not Found.');
	// add to queue
	$dbConnection->query("insert into cache.queue (payload) values ('$myKey')");
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
// helper functions
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //

function respondWith($header, $payload) {
	// break up header into lines
	$headerLines = explode("\n",  $header);

	// send each header line
	foreach($headerLines as $h) {
		header($h);
	}

	// send data
	echo $payload;
	return;
}


<?php

// connect to DB
$dbConnection =  new mysqli(getenv('DBHOST'), getenv('DBUSR'), getenv('DBPASS'), getenv('DBSCHEMA'), getenv('DBPORT'));
// if we cant connect, respond with server error
if ($dbConnection->connect_error) {
	respondWith("HTTP/1.1 503 Site Unavailable", 'Site temporarily unavailable.');
	exit;
}

// check to make sure we have origin set
$myDbResults = $dbConnection->query("select origin from cache.info limit 0, 1");
if(! $myOrigin = $myDbResults->fetch_assoc()) {
       // if not, we ask to set it
       require_once(__DIR__ . '/setOrigin.php');
       exit;
}

// use the URL as the key (ie. path/to/my/page )
//   we have configured our .htaccess file to pass the path via query string
if(! $myKey = htmlspecialchars($_GET["q"]) ){
	// if we didnt have a path, we assume the homepage
	$myKey = '/';
 }

// Check to see if this is an admin page
$adminPage = htmlspecialchars($_GET["admin"]);

// If this query comes from a POST request, refresh the cache.

/*
 * Considerations
 * This is theoretically problematic if the cached site has form submissions,
 * but the fakenator passes origin-site form submissions back to the origin,
 * anyway, so it doesn't cause any disruption in practice.
 * Alternatives could have included a query string (which would have needed
 * to be clearly unique relative to any query strings sent by the cached
 * site), or sending an X-Refresh-Page header in the GET request.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	 if ($dbConnection->query("delete from cache.dataStore where `key` = '{$myKey}'") === true ) {
	  error_log("Record deleted successfully");
	} else {
	  error_log("Error deleting record: " . $dbConnection->error);
	}
}

// Now load the page

// look-up in cache data store
$myDbResults = $dbConnection->query("select * from cache.dataStore where `key` = '$myKey'");

// if we have data,
if($cacheRecord = $myDbResults->fetch_assoc()) {
   // send response
   respondWith($cacheRecord['header'], addRefreshButton($cacheRecord['html'], $adminPage));

   // check expiry
   $now = date('YmdHis');
   if($now > $cacheRecord['expiry']) {
           // if expired, add to queue
           $dbConnection->query("insert into cache.queue (payload) values ('$myKey')");
   }

// if we did not have data, serve a 404 (with refresh header) and queue page to get generated
} else {
       // send response
       respondWith("HTTP/1.1 404 Not Found\nRefresh: 5;\nContent-Type: text/html; charset=UTF-8", "Generating cache for <b>$myKey</b></br></br>Page will automatically refresh");
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

function addRefreshButton($html, $adminPage) {
	// Add a button to the page

    /*
     * Considerations:
     *
     * 1. This code emphasizes low overhead by adding the CSS and the button
     *    with regular expressions. HTML is not a regular language, and can't
     *    be accurately parsed with regexps. However, parsing the cached
     *    HTML into a node tree, while safer and more correct, is resource-
     *    intensive. Whether regexps are safe depends on the expected usage
     *    of the feature. In a context like this, where you might expect:
     *     a. All the cached pages to be controlled by the button users,
     *     b. The button only appearing for backoffice users, perhaps logged-in
     *        users with a given security access,
     *    the efficiency benefits of using regexps outweigh the risks.
     *
     * 2. Putting the <button> at the end of the page seems to limit its
     *    chances of interfering with page CSS.
     *
     * 3. Consider whether the audience for this function would need any
     *    translation strings for the button text.
     *
     * 4. Adding the button to the displayed HTML instead of the saved HTML
     *    means the work is happening more often, but it also means we're not
     *    saving local changes to the cached remote-origin data. The button can
     *    change without rebuilding the cache. This also allows the admin
     *    switch.
     */

    if (! $adminPage) {
        return $html;
    }

    $cssMatchPattern = '/<head>/';
	$cssReplacement = <<<EOT
    <head>
    <style>
    button.refresher {
        position: fixed;
        bottom: 0;
        background:#304154;
        padding: .5em;
        margin: .5em;
        font-family:Graphik,system-ui,segoe ui,Roboto,Helvetica,Arial,Verdana,sans-serif;
     }</style>
    EOT;
	$html = preg_replace($cssMatchPattern, $cssReplacement, $html);
	$buttonMatchPattern = '/<\/body>/';
	$buttonReplacement = <<<EOT
    <form action="" method="post">
        <button class="refresher">Refresh Page</button>
    </form>
    </body>
    EOT;
	$html = preg_replace($buttonMatchPattern, $buttonReplacement, $html);

    return $html;
}

# fakenator

this app sets up a local baconator caching system.
To run, use docker-compose, from the root:

`docker-compose build &&  docker-compose up`

access site at:
http://localhost:8888/

It fronts www.propublica.org

The first time you browse to a page, it will 404 and queue page  for generation.  
The page will refresh after 5 sec and should load the right content.  
Subsequent visits will server the cached content, if cache is expired, page will be queued for regeneration.

## Baconator Elements

### Reverse Proxy
[this script](https://github.com/propublica/fakenator/blob/master/src/reverseProxy.php) TKTKTK   

### Data Store
mysql table, defined [here](https://github.com/propublica/fakenator/blob/master/createTables.sql#L5) TKTKTK   

### Queue Worker
[this script](https://github.com/propublica/fakenator/blob/master/src/queueWorker.php) TKTKTK   

### Queue
mysql table, defined [here](https://github.com/propublica/fakenator/blob/master/createTables.sql#L15) TKTKTK   

### Origin
Set up to be www.propublica.org, but [swap in](https://github.com/propublica/fakenator/blob/master/src/queueWorker.php#L92) your own!   


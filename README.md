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
[this script](https://github.com/propublica/fakenator/blob/master/src/reverseProxy.php), which is [configured](https://github.com/propublica/fakenator/blob/master/src/.htaccess) to receive all requests. TKTKTK   

### Data Store
mysql table, defined [here](https://github.com/propublica/fakenator/blob/master/createTables.sql#L5) TKTKTK   

### Queue Worker
[this script](https://github.com/propublica/fakenator/blob/master/src/queueWorker.php), which is [set up](https://github.com/propublica/fakenator/blob/master/entrypoint.sh#L12) to run on loop. TKTKTK   

### Queue
mysql table, defined [here](https://github.com/propublica/fakenator/blob/master/createTables.sql#L15) TKTKTK   

### Origin
Set up to be www.propublica.org, but [swap in](https://github.com/propublica/fakenator/blob/master/src/queueWorker.php#L92) your own!   


## Under the hood

### Accessing the database
In order to access the DB, the docker rig needs to be running, and you'll need a MySQL client, configured with the following:  
 - **server**: 127.0.0.1
 - **user**: docker
 - **pass**: docker
 - **port**: 33306
 - **schema**: cache

To see all cache in your cached pages:
```
select * from `cache`.`dataStore`
```

To view pending items to be generated in cache:
```
select *  from `cache`.`queue`
```



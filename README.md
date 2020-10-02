# 🥓 Baconator (local) 🥓

This app sets up a static site generator on localhost. Read more about it on ProPublica’s [Nerd Blog](https://www.propublica.org/nerds/baconator-news-site-caching-reverse-proxy-queue-worker).

A quick note on the name: In 2018, we began designing and implementing a new caching layer for ProPublica’s website. Inspired by how static site generators “bake a page out,” we referred to it as the `bake-n-ator`, which quickly became `baconator`. Also, naming things is hard ¯\\\_(ツ)\_/¯.

## Setup

### Prerequisites
 - [Docker compose](https://docs.docker.com/compose/install/)

## Running the App  
To run, clone repo and use *docker-compose*, from the root:

```
> git clone https://github.com/propublica/fakenator.git
> cd fakenator/
> docker-compose build && docker-compose up
```

After docker builds the web and database servers, you'll need to navigate to http://localhost:8888/ to set the origin.

The first time you browse to a new page, it will 404 and queue that page for generation. Your browser should refresh after 5 seconds and load the cached content. On subsequent visits to the same page, this app will serve from it cache. If a cache row has expired, its corresponding page will be queued for regeneration.  
**note** the first time you browse your cached site you may need to refresh a couple of times if your hosting css or helper scripts at the same origin.  It can take some time for these assets to get written into cache.


## Baconator Elements

**Reverse Proxy**  
[This script](https://github.com/propublica/fakenator/blob/master/src/reverseProxy.php), which is [configured](https://github.com/propublica/fakenator/blob/master/src/.htaccess) to receive all requests to the server.  

**Data Store**  
MySQL table, defined [here](https://github.com/propublica/fakenator/blob/master/helpers/createTables.sql#L5). Houses cache, served by the reverse proxy script.  

**Queue Worker**  
[This script](https://github.com/propublica/fakenator/blob/master/src/queueWorker.php), which is [set up](https://github.com/propublica/fakenator/blob/master/helpers/entrypoint.sh#L12-L16) to run on loop.  

**Queue**  
MySQL table, defined [here](https://github.com/propublica/fakenator/blob/master/helpers/createTables.sql#L15). Holds pages to be regenerated -- managed by queue worker script.  

**Origin**  
Set up when bootstrapping this application.  


## Under the Hood

### Accessing the database
In order to access the DB, the docker rig needs to be running, and you'll need a MySQL client, configured with the following:  
 - **server**: 127.0.0.1
 - **user**: docker
 - **pass**: docker
 - **port**: 33306
 - **schema**: cache

### Useful queries
To see all your cached pages:
```
select * from `cache`.`dataStore`
```

To invalidate all caches (so that they will be regenerated after the next visit to that page):
```
update `cache`.`dataStore` set `expiry` = "0" where `key` like '%'
```

To delete all caches (to start over or before swapping in new origin):  
```
truncate `cache`.`dataStore`
```

To swap in a new origin (run sql then browse to http://localhost:8888/):
```
truncate `cache`.`info`
```

To view pending items to be generated in cache:  
```
select *  from `cache`.`queue`
```



# fakenator

this app sets up a local baconator caching system.
To run, use docker-compose, from the root:

`docker-compose build &&  docker-compose up`

access site at:
http://localhost:8888/

It fronts www.propublica.org

The first time you browse to a page, it will 404 and queue page  for generation.  
The page will refresh after 10 sec and should load the right content.  
Subsequent visits will server the cached content, if cache is expired, page will be queued for regeneration.

## Baconator Elements

### Reverse Proxy
TKTKTK   

### Data Store
TKTKTK   

### Queue Worker
TKTKTK   

### Queue
TKTKTK   

### Origin
TKTKTK   


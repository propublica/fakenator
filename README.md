# fakenator

this app sets up a little mini-baconator.
To run, use docker-compose, from the root:

`docker-compose build &&  docker-compose up`

access site at:
http://localhost:8888/

It fronts www.propublica.org

The first time you browse to a page, it will 404 and queue page  for generation.  Subsequent visits will server the content at the same path at www.propublica.org.

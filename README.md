# *libphp*
*A collection of procedural php libraries.*

## libhttp
HTTP library for routing, parameter handling, response writing, etc...
```
libhttp_init();
libhttp_router_add("GET /", "home");
libhttp_router_add("GET /note/:id", "note_retrieve");
libhttp_router_dispatch();
```

## liblog
Simple logging procedures.
```
liblog_init("/tmp/app.log", array("debug", "warning"));
liblog_debug("test");
```

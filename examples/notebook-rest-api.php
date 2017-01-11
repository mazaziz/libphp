<?php

function note_retrieve()
{
    $id = libhttp_require_route_param("id");
    if (!is_numeric($id)) {
        libhttp_return_error(400, "note id must be numeric");
    }
    libhttp_return_success(200, null, array(
        'id' => $id,
        'content' => "some content",
        "tags" => array("tag1", "tag3")
    ));
}

function note_list()
{
    libhttp_return_success(200, "list of notes", array(
        array(
            'id' => 19,
            'content' => "note #19",
            'tags' => array("php", "libphp")
        ),
        array(
            'id' => 81,
            'content' => "note #81",
            'tags' => array()
        )
    ));
}

function note_tag_list()
{
    libhttp_return_success(200, "note tag list", array("tag1", "tag2"));
}

function note_create()
{
    libhttp_return_success(201, "note created", array(
        'id' => rand(1, 1000),
        'content' => libhttp_require_param("content"),
        'tags' => array()
    ));    
}

function home()
{
    liblog_error("test");
    liblog_debug("debug log");    
    liblog_log("warning", "asd");
    liblog_log("warn", "asd2");
    libhttp_return_success(200, "notebook-rest-api example", array(
        'api-doc' => libhttp_router_doc()
    ));
}

function main()
{
    require __DIR__ . '/../libhttp.php';
    //    liblog_init("/tmp/notebook-rest-api.log", array("warning"));
    liblog_init("/tmp/notebook-rest-api.log", array("debug"));
    libhttp_init();                
    libhttp_router_add("GET /", "home");
    libhttp_router_add("GET /note", "note_list");
    libhttp_router_add("POST /note", "note_create");
    libhttp_router_add("GET /note/:id", "note_retrieve");
    libhttp_router_add("GET /note/:id/tag", "note_tag_list");
    libhttp_router_dispatch();
}

date_default_timezone_set("UTC");
exit(main());

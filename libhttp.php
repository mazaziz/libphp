<?php

require_once __DIR__ . '/liblog.php';

function libhttp_router_build(array $routes)
{
    $GLOBALS['libhttp']['router'] = array();
    foreach ($routes as $dst => $handler) {
        libhttp_router_add($dst, $handler);
    }
}

function libhttp_router_doc($path = "/", $node = null)
{
    if (null === $node) {
        $node = &$GLOBALS['libhttp']['router'];
    }
    $routes = array();
    if (is_array(@$node['handler'])) {
        foreach ($node['handler'] as $verb => $handler) {
            $routes[] = "{$verb} {$path}";
        }
    }
    if (is_array(@$node['static'])) {
        $sep = "/" == $path ? "" : "/";
        foreach (array_keys($node['static']) as $s) {
            $routes = array_merge($routes, libhttp_router_doc($path . $sep . $s, $node['static'][$s]));
        }
    }
    if (isset($node['dynamic'])) {
        $sep = "/" == $path ? "" : "/";
        $routes = array_merge($routes, libhttp_router_doc("{$path}{$sep}:{$node['dynamic']['placeholder']}", $node['dynamic']));
    }
    return $routes;
}

function libhttp_router_add($dst, $handler)
{
    if (false === ($dst = preg_split("/\s+/", $dst, -1, PREG_SPLIT_NO_EMPTY)) || 2 != count($dst)) {
        liblog_error("invalid route destionation");
        libhttp_return_error(500);
    }
    $ss = ("/" === $dst[1]) ? array() : explode('/', ltrim($dst[1], '/'));
    $node = &$GLOBALS['libhttp']['router'];
    for ($i = 0; $i < count($ss); $i++) {
        if (':' === $ss[$i][0]) { // dynamic link
            if (isset($node['static'])) {
                liblog_error(__FUNCTION__ . "() you cannot create dynamic link '{$ss[$i]}' when there is a static link");
                libhttp_return_error(500);
            }
            $placeholder = substr($ss[$i], 1);
            if (!isset($node['dynamic'])) {
                $node['dynamic'] = array(
                    'placeholder' => $placeholder
                );
            }
            if ($placeholder !== $node['dynamic']['placeholder']) {
                liblog_error(__FUNCTION__ . "() you cannot create placeholder '{$placeholder}' since placeholder '{$node['dynamic']['placeholder']}' already registered");
                libhttp_return_error(500);
            }
            $node = &$node['dynamic'];
        } else {
            if (isset($node['dynamic'])) {
                liblog_error(__FUNCTION__ . "() you cannot create static link '{$ss[$i]}' when there is a dynamic link");
                libhttp_return_error(500);
            }
            if (!isset($node['static'])) {
                $node['static'] = array();
            }
            if (!isset($node['static'][$ss[$i]])) {
                $node['static'][$ss[$i]] = array();
            }
            $node = &$node['static'][$ss[$i]];
        }
    }
    if (!isset($node['handler'])) {
        $node['handler'] = array();
    }
    if (isset($node['handler'][$dst[0]])) {
        liblog_error(__FUNCTION__ . "() you cannot register multiple handlers for '{$dst[0]} {$dst[1]}'");
        libhttp_return_error(500);
    }
    $node['handler'][$dst[0]] = $handler;
}

function libhttp_router_dispatch()
{
    if ("PUT" == $_SERVER['REQUEST_METHOD']) {
        if ("application/x-www-form-urlencoded" != @$_SERVER['CONTENT_TYPE']) {
            libhttp_return_error(415, "Content type of PUT requests must be of 'application/x-www-form-urlencoded'.");
        }
        $put_vars = array();
        parse_str(file_get_contents("php://input"), $put_vars);
        $_REQUEST = array_merge($_REQUEST, $put_vars);
    }
    
    $GLOBALS['libhttp']['route_params'] = array();
    if (false
        || false === ($path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))
        || false === ($ss = preg_split("/\//", $path, -1, PREG_SPLIT_NO_EMPTY))
    ) {
        libhttp_return_error(400);
    }
    
    $node = &$GLOBALS['libhttp']['router'];
    for ($i = 0; $i < count($ss); $i++) {
        if (isset($node['dynamic'])) {
            $GLOBALS['libhttp']['route_params'][$node['dynamic']['placeholder']] = $ss[$i];
            $node = &$node['dynamic'];
        } else if (isset($node['static'][$ss[$i]])) {
            $node = &$node['static'][$ss[$i]];
        } else {
            libhttp_return_error(404);
        }
    }
    if (!isset($node['handler'])) {
        libhttp_return_error(404, "not found");
    }
    if (!isset($node['handler'][$_SERVER['REQUEST_METHOD']])) {
        libhttp_return_error(405);
    }
    if (!function_exists($node['handler'][$_SERVER['REQUEST_METHOD']])) {
        liblog_error("undefined route handler function '{$node['handler'][$_SERVER['REQUEST_METHOD']]}'");
        libhttp_return_error(503);
    }
    $node['handler'][$_SERVER['REQUEST_METHOD']]();
}

function libhttp_require_route_param($name)
{
    if (null === ($v = @$GLOBALS['libhttp']['route_params'][$name])) {
        liblog_error(sprintf(
            "required route parameter '%s' not found on '%s'",
            $name,
            parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
        ));
        libhttp_return_error(500);
    }
    return $v;
}

function libhttp_require_params()
{
    $v = array();
    foreach (func_get_args() as $k) {
        $v[$k] = libhttp_require_param($k);
    }
    return $v;
}

function libhttp_require_param($name)
{
    if (!isset($_REQUEST[$name])) {
        libhttp_return_error(400, "missing required parameter: {$name}");
    }
    return $_REQUEST[$name];
}

function libhttp_init()
{
    $GLOBALS['libhttp']['status_map'] = array(
        200 => "OK",
        201 => "Created",
        204 => "No Content",
        400 => "Bad Request",
        401 => "Unauthorized",
        403 => "Forbidden",
        404 => "Not Found",
        405 => "Method Not Allowed",
        409 => "Conflict",
        410 => "Gone",
        413 => "Payload Too Large",
        415 => "Unsupported Media Type",
        500 => "Internal Server Error",
        501 => "Not Implemented",
        502 => "Bad Gateway",
        503 => "Service Unavailable"
    );
}

function libhttp_header_status($code)
{
    $code = (int) $code;
    if ($code < 100 || $code > 599) {
        liblog_error("invalid HTTP status given = {$code}");
        libhttp_return_error(500);
    }
    if (null === ($text = @$GLOBALS['libhttp']['status_map'][$code])) {
        header("HTTP/1.1 {$code}");
    } else {
        header("HTTP/1.1 {$code} {$text}");
    }
}

function libhttp_return_error($code, $msg = null)
{
    $code = (int) $code;
    if (null === $msg) {
        $msg = (string) @$GLOBALS['libhttp']['status_map'][$code];
    }
    
    libhttp_return_json($code, array(
        'success' => false,
        'code' => $code,
        'message' => $msg
    ));
}

function libhttp_return_success($code, $msg = null, $data = null)
{
    $v = array(
        'success' => true,
        'code' => $code,
    );
    if (null !== $msg) {
        $v['message'] = $msg;
    }    
    if (null !== $data) {
        $v['data'] = $data;
    }
    libhttp_return_json($code, $v);
}

function libhttp_return_json($code, $data)
{
    $encode_opts = defined('JSON_UNESCAPED_SLASHES') ? JSON_UNESCAPED_SLASHES : null;
    if (is_array($data) && false === ($data = json_encode($data, $encode_opts))) {
        libhttp_header_status(500);
        echo "json encode failed";
        exit();
    }
    ob_start("ob_gzhandler");
    libhttp_header_status($code);
    header("Content-Type: application/json");
    echo $data;
    exit();
}

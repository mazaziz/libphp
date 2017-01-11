<?php

function liblog_init($log_file, array $enable = array())
{
    $GLOBALS['liblog'] = array(
        'file' => $log_file,
        'level_error' => true
    );
    foreach ($enable as $level) {
        $GLOBALS['liblog']["level_{$level}"] = true;
    }
}

function liblog_log($level, $msg)
{
    if (false
        || null === ($file = @$GLOBALS['liblog']['file'])
        || true !== @$GLOBALS['liblog']["level_{$level}"]
    ) {
        return;
    }
    @file_put_contents(
        $file,
        sprintf(
            "%s %5s %7s: %s\n",
            date(DATE_ISO8601),
            getmypid(),
            strtoupper($level),
            $msg
        ),
        FILE_APPEND
    );
}

function liblog_error($msg)
{
    liblog_log("error", $msg);
}

function liblog_debug($msg)
{
    liblog_log("debug", $msg);
}

function liblog_info($msg)
{
    liblog_log("info", $msg);
}

function liblog_warning($msg)
{
    liblog_log("info", $msg);
}

function liblog_critical($msg)
{
    liblog_log("critical", $msg);
}

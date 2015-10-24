<?php

$f3 = require(__DIR__.'/libs/f3/base.php');

$f3->set('DEBUG',0);
$f3->set('APP_ROOT', __DIR__);
$f3->set('version','2.14');
$f3->set('AUTOLOAD',
    \F3::get('APP_ROOT').'/;' .
    \F3::get('APP_ROOT').'/libs/f3/;' .
    \F3::get('APP_ROOT').'/libs/;' .
    \F3::get('APP_ROOT').'/libs/WideImage/;' .
    \F3::get('APP_ROOT').'/daos/;' .
    \F3::get('APP_ROOT').'/libs/twitteroauth/;' .
    \F3::get('APP_ROOT').'/libs/FeedWriter/;');

if(!isset($CONFDIR))
  $CONFDIR=\F3::get('APP_ROOT');
if(!isset($WEBDIR))
  $WEBDIR=\F3::get('APP_ROOT');
if(!isset($FAVICON_DIR))
  $FAVICON_DIR=\F3::get('APP_ROOT').'/public/favicons';
if(!isset($THUMB_DIR))
  $THUMB_DIR=\F3::get('APP_ROOT').'/public/thumbails';
if(!isset($CACHE_DIR))
  $CACHE_DIR=\F3::get('APP_ROOT').'/data/cache';
if(!isset($LOGS_DIR))
  $LOGS_DIR=\F3::get('APP_ROOT').'/data/logs';

$f3->set('CONFDIR', $CONFDIR);
$f3->set('WEBDIR', $WEBDIR);
$f3->set('FAVICON_DIR', $FAVICON_DIR);
$f3->set('THUMB_DIR', $THUMB_DIR);
$f3->set('LOGS_DIR', $LOGS_DIR);

$f3->set('cache',$CACHE_DIR);
$f3->set('BASEDIR',__dir__);
$f3->set('LOCALES',__dir__.'/public/lang/');

// read defaults
$f3->config('defaults.ini');

// read config, if it exists
$f3->config(\F3::get('APP_ROOT').'/defaults.ini');
if(file_exists(\F3::get('CONFDIR').'/config.ini')){
    $f3->config(\F3::get('CONFDIR').'/config.ini');
}

// overwrite config with ENV variables
$env_prefix = $f3->get('env_prefix');
foreach($f3->get('ENV') as $key => $value) {
    if(strncasecmp($key,$env_prefix,strlen($env_prefix)) == 0) {
        $f3->set(strtolower(substr($key,strlen($env_prefix))),$value);
    }
}

// init logger
$f3->set(
    'logger',
    new \helpers\Logger( \F3::get('LOGS_DIR').'/default.log', $f3->get('logger_level') )
);

// init error handling
$f3->set('ONERROR',
    function($f3) {
        $trace = $f3->get('ERROR.trace');
        $tracestr = "\n";
        foreach($trace as $entry) {
            $tracestr = $tracestr . $entry['file'] . ':' . $entry['line'] . "\n";
        }
        
        \F3::get('logger')->log($f3->get('ERROR.text') . $tracestr, \ERROR);
        if (\F3::get('DEBUG')!=0) {
            echo $f3->get('lang_error') . ": ";
            echo $f3->get('ERROR.text') . "\n";
            echo $tracestr;
        } else {
            echo $f3->get('lang_error');
        }
    }
);

if (\F3::get('DEBUG')!=0)
    ini_set('display_errors',0);

<?php
require('config.php');

chdir(__DIR__);
require(__DIR__.'/common.php');

$f3->set('DEBUG',0);
$f3->set(
    'logger',
    new \helpers\Logger( \F3::get('LOGS_DIR').'/default.log', $f3->get('logger_level') )
);

$f3->set('FTRSS_DATA_DIR', __dir__.'/data/fulltextrss');

$loader = new \helpers\ContentLoader();
$loader->update();

?>

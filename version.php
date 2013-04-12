<?php
require_once(dirname(__FILE__) . '/../../config/config.inc.php');
require_once(dirname(__FILE__) . '/../../init.php');
require_once(dirname(__FILE__) . '/doofinder.php');

header("Content-Type:text/plain; charset=utf-8");
echo Doofinder::VERSION;
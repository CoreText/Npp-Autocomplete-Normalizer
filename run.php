<?php

error_reporting(E_ALL);
ini_set('display_errors',1);
ini_set('display_startup_errors',1);

//error_reporting(0);
//ini_set('display_errors',0);
//ini_set('display_startup_errors',0);

require_once('env.php');
require_once('lib/functions.php');
require_once('lib/phpQuery.php');


// parser to run
// require_once('parsers/wp_parse_docs.php');
require_once('parsers/php_parse_docs.php');

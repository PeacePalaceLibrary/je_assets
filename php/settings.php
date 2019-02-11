<?php
/*
* Twig is installed using composer
* in a production environment Twig might already exist
* then comment out the require_once line below
*
*/
require_once(__DIR__.'/../vendor/autoload.php');

/*
* mysql database:
* constants start with JE (json editor) because of
* possible name conflicts in Wordpress
*/
// development
//define("JE_DB_HOST", "localhost");
// production
define("JE_DB_HOST", "staff.ppl.nl");

define("JE_DB_USER", "librarycard");
define("JE_DB_PW", "pplgrotius");
define("JE_DB_NAME", "librarycard");
define("JE_TABLE_NAME", "patrons");

// development
define("JE_ACT_URL", "localhost/oclcAPIs/register/activation.php");
// production
//define("JE_ACT_URL", "http://www.peacepalacelibrary.nl/activation/");
?>
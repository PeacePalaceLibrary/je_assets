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
* constants start with JE (json editor) because:
* when installed in Wordpress: possible name conflicts
*/
define("JE_DB_HOST", "your database host");

//mysql database
define("JE_DB_USER", "your database user");
define("JE_DB_PW", "your database user's password");
define("JE_DB_NAME", "your database name");
define("JE_TABLE_NAME", "your database table name");

//mail instellingen (PHP Mailer)
define("JE_MAIL_HOST", "your mail host");
define("JE_MAIL_USER", "your mail user");
define("JE_MAIL_PW", "your mail user's password");
define("JE_MAIL_FROM","your mail sender");
define("JE_MAIL_From2","your mail sender 2");
define("JE_MAIL_SUBJ","your mail subject");

/*
* url of activation page
*/
define("JE_ACT_URL", "your activation page URL" );
?>
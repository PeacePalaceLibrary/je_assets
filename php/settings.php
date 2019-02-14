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

define("JE_MAIL_HOST", "smtp.office365.com");
define("JE_MAIL_USER", "h.groot@ppl.nl");
define("JE_MAIL_PW", "coldsnow78");
define("JE_MAIL_FROM","webteam@ppl.nl");
define("JE_MAIL_From2","PeacePalaceLibrary");
define("JE_MAIL_SUBJ","Peace Palace Library card");

// development
//define("JE_ACT_URL", "localhost/oclcAPIs/register/activation.php");
// production
define("JE_ACT_URL", "http://web03u18b.ppl.nl/register/activation.php" );
?>
<?php

require_once('./helpers.php');
/*
* $POST is an associative array as send by the registration form
* see je_assets/jsoneditor/regSchema.js for its schema
*
*/
//insert a new customer both in the mysql database and in WMS
$codes = new_customer($_POST);

if (array_key_exists('error',$codes)) {
  echo $codes['error'];
  exit(0);
}
else if (array_key_exists('code',$codes) && array_key_exists('barcode',$codes)) {
  exit(0);
}
else {
  header('HTTP/1.1 500 Internal Server Error');
  exit("Registration failed.");
}


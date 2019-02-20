<?php
/*
script called by the account webpage using jQuery.post (AJAX)
*/

//delete or comment out the 3 lines about errors in a production environment
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('./helpers.php');

$codes = update_customer($_POST);

if (array_key_exists('error',$codes)) {
  echo $codes['error'];
  exit(0);
}
else if (array_key_exists('code',$codes) && array_key_exists('barcode',$codes)) {
  exit(0);
}
else {
  header('HTTP/1.1 500 Internal Server Error');
  exit("Update failed.");
}

?>

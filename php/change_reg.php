<?php
require_once('./helpers.php');

$code = FALSE;

$code = update_customer($_POST);

if ($code){
  exit(0);
}
else{
  //header('HTTP/1.1 500 Internal Server Error');
  exit("Update failed.");
}

?>

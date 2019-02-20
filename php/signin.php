<?php
/*
script called by the sign-in webpage using jQuery.post (AJAX)
*/

//delete or comment out the 3 lines about errors in a production environment
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('./helpers.php');

$check = check_login($_POST['email'],$_POST['password']);
if ($check === TRUE) {
  $code = new_code($_POST['email']);
  if ($code){
    echo $code;
    exit(0);
  }
  else{
    header('HTTP/1.1 500 Internal Server Error');
    exit("Get code failed.");
  }
}
else{
  header('HTTP/1.1 500 Internal Server Error');
  exit("Customer unknown.");
}

?>

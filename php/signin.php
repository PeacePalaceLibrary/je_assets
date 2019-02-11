<?php
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

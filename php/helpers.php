<?php
//debugging, in production delete these 3 lines
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__.'/settings.php');
require_once(__DIR__.'/patron.php');

/*
* generates a new sha256 hash code
* and updates the activationCode column of the $userName row in the database
* and returns the code
*
*/
function new_code($userName) {
  $result = '';
  $mysqli = new mysqli(JE_DB_HOST,JE_DB_USER,JE_DB_PW,JE_DB_NAME);
  if (!$mysqli) {
    $result = FALSE;
  }
  else {
    $code = hash('sha256',time().time());
    $q = "UPDATE ".JE_TABLE_NAME." SET activationCode='$code' WHERE userName='$userName'";
    $res = $mysqli->query($q);
    if ($res === FALSE) {
      $result = FALSE;
    }
    else {
      $result = $code;
    }
    mysqli_close($mysqli);
  }
  return $result;
}

/*
* generates a new code
* updates the activationCode AND the passwd column
* of the $userName row in the database
*
* used for setting a new password
*
* returns the code
*
*/
function new_code_pw($userName,$password) {
  $result = '';
  $mysqli = new mysqli(JE_DB_HOST,JE_DB_USER,JE_DB_PW,JE_DB_NAME);
  if (!$mysqli) {
    $result = FALSE;
  }
  else {
    $code = hash('sha256',time().time());
    $q = "UPDATE ".JE_TABLE_NAME." SET activated=FALSE, activationCode='$code', passwd='$password' WHERE userName='$userName'";
    $res = $mysqli->query($q);
    if ($res === FALSE) {
      $result = FALSE;
    }
    else {
      $result = $code;
    }
    mysqli_close($mysqli);
  }
  return $result;
}

/*
* retrieves a customer row with a given $code (activation code)
* returns FALSE if nothing could be found and
* returns FALSE if more then one row is found
*
* returns the row if one is found
*/
function get_customer_from_code($code) {
  $result = '';
  $mysqli = new mysqli(JE_DB_HOST,JE_DB_USER,JE_DB_PW,JE_DB_NAME);
  if (!$mysqli) {
    $result = FALSE;
  }
  else {
    $q = "SELECT * FROM ".JE_TABLE_NAME." WHERE activationCode='$code'";
    $res = $mysqli->query($q);

    if ($res === FALSE) {
      $result = FALSE;
    }
    else {
      if ($res->num_rows == 1) {
        $res->data_seek(0);
        $row = $res->fetch_assoc();
        $result = $row;
      }
      else {
        $result = FALSE;
      }
    }
    mysqli_close($mysqli);
  }
  return $result;
}

/*
* retrieves a customer row with a given $username AND $password
* used for checking login
*
* returns FALSE if nothing could be found and
* returns FALSE if more then one row is found
*
* returns the row if one is found
*/
function check_login($userName, $password) {
  $result = array();
  $mysqli = new mysqli(JE_DB_HOST,JE_DB_USER,JE_DB_PW,JE_DB_NAME);
  if (!$mysqli) {
    $result = FALSE;
  }
  else {
    $q = "SELECT * FROM ".JE_TABLE_NAME." WHERE userName='$userName' AND passwd='$password' AND activated=TRUE";
    $res = $mysqli->query($q);
    if ($res === FALSE) {
      $result = FALSE;
    }
    else {
      $result = ($res->num_rows == 1) ? TRUE : FALSE;
    }
    mysqli_close($mysqli);
  }
  return $result;
}

/*
* retrieves a customer row with a given $username
*
* returns FALSE if nothing could be found and
* returns FALSE if more then one row is found
*
* returns the row if one is found
*/
function get_customer_from_userName($userName) {
  $result = array();
  $mysqli = new mysqli(JE_DB_HOST,JE_DB_USER,JE_DB_PW,JE_DB_NAME);
  if (!$mysqli) {
    $result = FALSE;
  }
  else {
    $q = "SELECT * FROM ".JE_TABLE_NAME." WHERE userName='$userName'";
    $res = $mysqli->query($q);
    if ($res === FALSE) {
      $result = FALSE;
    }
    else {
      if ($res->num_rows == 1) {
        $res->data_seek(0);
        $row = $res->fetch_assoc();
        $result = $row;
      }
      else {
        $result = FALSE;
      }
    }
    mysqli_close($mysqli);
  }
  return $result;
}


/*
* deletes a customer row with a given $username
*
* returns TRUE if delete succeeded
*/

function delete_customer($userName) {
  $result = '';
  $mysqli = new mysqli(JE_DB_HOST,JE_DB_USER,JE_DB_PW,JE_DB_NAME);
  if (!$mysqli) {
    $result = FALSE;
    //echo 'No connection';
  }
  else {
    $q = "DELETE FROM ".JE_TABLE_NAME." WHERE userName = $userName";
    $res = $mysqli->query($q);
    if ($res === FALSE) {
      $result = FALSE;
    }
    else {
      $result = TRUE;
    }
  }
  return $result;
}

/*
* inserts a new customer in the database
* with a newly generated barcode
* and a newly generated activation code
* 
* sends an insert request to WMS, patron is blocked and not verified
*
* returns FALSE the userName is used in an existing row or WMS gives an error
*
* the WMS ppid is stored in the database for later updates
*
* returns an array with the activation code and the barcode
*/
function new_customer($json) {
  $result = array();
  $errors = array();
  
  $mysqli = new mysqli(JE_DB_HOST,JE_DB_USER,JE_DB_PW,JE_DB_NAME);
  if (!$mysqli) {
    $errors[] = 'No connection';
    //echo 'No connection';
  }
  else {
    $q = "SELECT * FROM ".JE_TABLE_NAME." WHERE userName='".$json['id']['userName']."'";
    $res = $mysqli->query($q);
    if ($res === FALSE) {
      //SQL statement failed
      $errors[] = 'mysqli_error: '.mysqli_error($mysqli);
      //echo 'mysqli_error: '.mysqli_error($mysqli);
    }
    else {
      if ($res->num_rows > 0) {
        //this must be a new customer
        $res->data_seek(0);
        $row = $res->fetch_assoc();
        $errors[] = 'Username: '.$row['userName'].' already registered.';
        //echo 'Username: '.$row['userName'].' already registered.';
      }
      else {
        //not registered yet:
        $ppid = '';
        $barcode = '';
        if ($json['services']['membership'] == 'Yes' ) {
          //try to register in WMS
          $barcode = wms_get_new_barcode($json);
          if (strlen($barcode) == 0) {
            $errors[] = 'Could not generate a unique barcode.';
          }
          else {
            //barcode is indeed new in WMS
            $ppid = wms_create($barcode,$json);
            if (strlen($ppid) == 0) $errors[] = 'new_customer - wms_create failed.';
          }
        }
        
        //register in MySQL
        $code = hash('sha256',time().time());
        $q = "INSERT INTO ".JE_TABLE_NAME." (userName, passwd, json, activated, activationCode, datetime, ppid, barcode) VALUES (".
        "'".$json['id']['userName']."',".
        "'".$json['id']['password']."',".
        "'".json_encode($json)."',".
        "FALSE,".
        "'".$code."',".
        "now(),".
        "'".$ppid."',".
        "'".$barcode."'".
        ")";
        $res = $mysqli->query($q);
        if ($res === FALSE) {
          $errors[] = 'mysqli_error: '.mysqli_error($mysqli);
        }
        else {
          $result = array('code' => $code,'barcode' => $barcode);
          send_mail('activation',$json,$result);
        }
      }
    }
    mysqli_close($mysqli);
  }
  if (count($errors) > 0) $result['error'] = implode(' -+- ', $errors);
  return $result;
}

/*
* activates a new customer in the database and in WMS
* 
* sends an update request to WMS, patron is unblocked and verified
* sends an email to the customer with username and barcode
* returns TRUE or FALSE 
*
*/
function activate_customer($ppid, $userName, $barcode, $json) {
  $result = '';
  if ($json['services']['membership'] == 'Yes' ) { 
    //change some things in WMS
    $wms_ok = wms_activate($ppid, $barcode, $json);
    //if (!$wms_ok) ....
  }

  //update customer in databse
  $code = '0';
  $mysqli = new mysqli(JE_DB_HOST,JE_DB_USER,JE_DB_PW,JE_DB_NAME);
  if (!$mysqli) {
    $result = FALSE;
  }
  else {
    //now change record
    $code = hash('sha256',time().time());
    $q = "UPDATE ".JE_TABLE_NAME." SET ".
    "activationCode='$code', ".
    "activated=TRUE,".
    "datetime=NOW()".
    " WHERE userName='".$userName."'";
    $result = $mysqli->query($q);
  }
  mysqli_close($mysqli);

  if ($result === TRUE) {
    send_mail('confirmation',$json, array('barcode' => $barcode,'code' => $code));
    if ($json['services']['receiveNews'] =='Yes') send_mail('procurios',$json, array('barcode' => $barcode,'code' => $code));
  }
  return $result;
}

function update_customer($changed_json) {
  $result = array();
  $errors = array();

  //the users are not allowed to change their userName
  //so get customer from database
  $old_row = get_customer_from_userName($changed_json['id']['userName']);
  $old_json = json_decode($old_row['json'],TRUE);

  //first handle WMS
  $new_member = FALSE;
  $ppid = $old_row['ppid'];
  $barcode = $old_row['barcode'];
  $activated = $old_row['activated'];
  if (strlen($old_row['ppid']) > 0) {
    //the user is present in WMS
    if ($changed_json['services']['membership'] == 'No') {
      //the user wants to stop membership
      $ppid = '';
      $barcode = '';
      $activated = FALSE;
      //update in WMS?
    }
    else {
      //update WMS, ppid and barcode stay the same
      $wms_ok = wms_update($ppid, $barcode, $changed_json);
    }
  }
  else {
    //the user is not in WMS
    if ($changed_json['services']['membership'] == 'Yes') {
      //but wants a membership
      $new_member = TRUE;
          //try to register in WMS
          $barcode = wms_get_new_barcode($changed_json);
          if (strlen($barcode) == 0) {
            $errors[] = 'Could not generate a unique barcode.';
          }
          else {
            //barcode is indeed new in WMS
            $ppid = wms_create($barcode,$changed_json);
            if (strlen($ppid) == 0) $errors[] = 'update_customer - wms_create failed.';
            $activated = FALSE;
          }

    }
    else {
      //nothing to do in WMS
    }
  }

  //then handle the database
  //update customer in databse
  $mysqli = new mysqli(JE_DB_HOST,JE_DB_USER,JE_DB_PW,JE_DB_NAME);
  if (!$mysqli) {
    $errors[] = 'No connection';
  }
  else {
    //now change record (passwd, json, activationCode, datetime)
    $code = hash('sha256',time().time());

    $q = "UPDATE ".JE_TABLE_NAME." SET ".
    "passwd='".$changed_json['id']['password']."', ".
    "json='".json_encode($changed_json)."',".
    "activationCode='$code',".
    "activated=$activated,".
    "ppid='$ppid',".
    "barcode='$barcode',".
    "datetime=NOW()".
    " WHERE userName='".$changed_json['id']['userName']."'";
    $res = $mysqli->query($q);
    if ($res === FALSE) {
      $errors[] = 'mysqli_error: '.mysqli_error($mysqli);
    }
    else {
      $result = array('code' => $code,'barcode' => $barcode);
      if ($new_member) send_mail('confirmation',$changed_json,$result);
    }
  }
  mysqli_close($mysqli);

  if (count($errors) > 0) $result['error'] = implode(' -+- ', $errors);
  return $result;
}

/*
* generates a barcode
* from a sha26 hash in which letters are replaced by numbers
* all barcodes start with 90
*
*/
function new_barcode($userName) {
  $hash = substr(hash('sha256',$userName.time()), 0, 20);
  $chars = str_split($hash);
  $newhash = '';
  foreach ($chars as $c) {
    $num = ord($c);
    if (($num > 47) && ($num < 58)) {
      //numbers
      $newhash .= $c;
    }
    else if (($num > 64) && ($num < 91)) {
      //uppercase letters
      $newhash .= strval($num - 64);
    }
    else if (($num > 96) && ($num < 123)) {
      //lowercase letters
      $newhash .= strval($num - 96);
    }
    //else do nothing with $newhash
  }
  return '90'.substr($newhash, 0, 8);
}

function wms_get_new_barcode($json) {
  $barcode = new_barcode($json['id']['userName']);
  //check max 20 times in WMS whether the barcode already exists
  $max_repeats = 20;
  $repeat = 0;
  while (wms_barcode_exists($barcode) && ($repeat < $max_repeats)) {
    $repeat++;
    $barcode = new_barcode($json['id']['userName']);
  }
  if ($repeat >= $max_repeats) $barcode = '';
  return $barcode;
}

/*
* checks whether the barcode is already used in WMS 
*
* returns TRUE or FALSE
*/
function wms_barcode_exists($barcode){
  $patron = new Patron();
  $search = '{"schemas": ["urn:ietf:params:scim:api:messages:2.0:SearchRequest"], '.
  '"filter": "External_ID eq \"'.$barcode.'\""}';
  $patron->search_patron($search);
  return ($patron->search["totalResults"] == 0) ? FALSE : TRUE;
}

/*
* creates a new customer in WMS
* blocked is set to TRUE and verified to FALSE
* patron type is set to website
*
* returns the new ppid or an empty string
*/
function wms_create($barcode,$json) {
  $ppid = '';
  $json['extra'] = array(
  'barcode' => $barcode,
  'country' => get_countrycode($json['address']['country']),
  'date' => date("Y-m-d"),
  'expDate' => date('Y-m-d\TH:i:s\Z'),
  'blocked' => 'true',
  'verified' => 'false'
  );
  file_put_contents('form.json',json_encode($json, JSON_PRETTY_PRINT));

  $loader = new Twig_Loader_Filesystem(__DIR__);
  $twig = new Twig_Environment($loader, array(
  //specify a cache directory only in a production setting
  //'cache' => './compilation_cache',
  ));
  $scim_json = $twig->render('scim_create_template.json', $json);
  file_put_contents('form_scim.json',json_encode($scim_json, JSON_PRETTY_PRINT));

  $patron = new Patron();
  $patron->create_patron($scim_json);
  file_put_contents('form_response.json',json_encode($patron->create, JSON_PRETTY_PRINT));

  return array_key_exists('id',$patron->create) ? $patron->create['id'] : '';
}


/*
* activates a new customer in WMS
* meaning that blocked is set to FALSE and verified to TRUE
* 
* returns TRUE or FALSE
*/
function wms_activate($ppid, $barcode, $json){
  //calculate expiry date
  $expDate = ($json['services']['membershipPeriod'] == "week") ? date('Y-m-d\TH:i:s\Z', strtotime("+9 days")) : date('Y-m-d\TH:i:s\Z', strtotime("+1 year"));
  $json['extra'] = array(
  'barcode' => $barcode,
  'date' => date("Y-m-d"),
  'expDate' => $expDate,
  'blocked' => 'false',
  'verified' => 'true'
  );
  //file_put_contents('form.json',json_encode($json, JSON_PRETTY_PRINT));

  $loader = new Twig_Loader_Filesystem(__DIR__);
  $twig = new Twig_Environment($loader, array(
  //specify a cache directory only in a production setting
  //'cache' => './compilation_cache',
  ));
  $scim_json = $twig->render('scim_activate_template.json', $json);
  //file_put_contents('form_scim.json',json_encode($scim_json, JSON_PRETTY_PRINT));

  $patron = new Patron();
  $patron->update_patron($ppid,$scim_json);
  //file_put_contents('form_response.json',json_encode($patron->update, JSON_PRETTY_PRINT));

  return array_key_exists('id',$patron->update) ? TRUE : FALSE;
}

/*
* updates a customer in WMS
*
* returns TRUE or FALSE
*/
function wms_update($ppid, $barcode, $json) {
  $json['extra'] = array(
  'country' => get_countrycode($json['address']['country']),
  'date' => date("Y-m-d")
  );
  //file_put_contents('form.json',json_encode($json, JSON_PRETTY_PRINT));

  $loader = new Twig_Loader_Filesystem(__DIR__);
  $twig = new Twig_Environment($loader, array(
  //specify a cache directory only in a production setting
  //'cache' => './compilation_cache',
  ));
  $scim_json = $twig->render('scim_update_template.json', $json);
  //file_put_contents('form_scim.json',json_encode($scim_json, JSON_PRETTY_PRINT));

  $patron = new Patron();
  $patron->update_patron($ppid,$scim_json);
  //file_put_contents('form_response.json',json_encode($patron->update, JSON_PRETTY_PRINT));

  return array_key_exists('id',$patron->update) ? TRUE : FALSE;
}


/*
* gets a 2 letter country code according to ISO 3166-1 alpha-2 
* WMS requires this code instead of the name of the country
* codes are in a separate file country2code.php
*/
function get_countrycode($country) {
  require(__DIR__.'/country2code.php');
  return array_key_exists($country,$codeOfCountry) ? $codeOfCountry[$country] : '';
}

/*
* sends mails for activationCode
*  $type = activation
* and for confirmation of avtivation:
*  $type = barcode
*/
function send_mail($type, $json, $codes) {
  //initialize Twig
  $loader = new Twig_Loader_Filesystem(__DIR__);
  $twig = new Twig_Environment($loader, array(
  //specify a cache directory only in a production setting
  //'cache' => './compilation_cache',
  ));
  
  if ($type == 'activation') {
    $twigins = array('url' => JE_ACT_URL, 'code' => $codes['code']);
    $alt_message = $twig->render('activation_template.txt', $twigins);
    $message = $twig->render('activation_template.html', $twigins);
  }
  else if ($type == 'confirmation') {
    $twigins = array('username' => $json['id']['userName'],'barcode' => $codes['barcode'], 'services' => $json['services']);
    $alt_message = $twig->render('confirmation_template.txt', $twigins);
    $message = $twig->render('confirmation_template.html', $twigins);
  }
  else if ($type == 'procurios') {
    $alt_message = $twig->render('procurios_template.txt', $json);
    $message = $twig->render('procurios_template.html', $json);
  }
  else {
    return FALSE;
  }
  //echo $message;
  //echo $alt_message;
  require_once('class.phpmailer.php');
  require_once('class.smtp.php');

  $mail = new PHPMailer();
  $mail->IsSMTP(); // telling the class to use SMTP
  $mail->SMTPDebug = false;
  $mail->Host = JE_MAIL_HOST;
  $mail->Port = 587;
  $mail->SMTPSecure = "tls";
  $mail->SMTPAuth = true;
  $mail->Username = JE_MAIL_USER;
  $mail->Password = JE_MAIL_PW;
  $mail->SMTPDebug  = 1; // enables SMTP debug information (for testing) 1 = errors and messages $
  $mail->SetFrom(JE_MAIL_FROM,JE_MAIL_From2);
  $mail->Subject = JE_MAIL_SUBJ;
  $mail->Body = $message;
  $mail->AltBody = $alt_message;
  //$mail->MsgHTML($message);
  
  if ($type == 'procurios') {
    $recipient = JE_MAIL_PROCURIOS;
  }
  else {
    $recipient = $json['id']['userName'];
  }
  //debugging: delete the following 3 lines in production
  $recipient = 'f.latum@ppl.nl';
  $mail->AddCC('a.janson@ppl.nl');
  $mail->AddCC('j.verweij@ppl.nl');
  
  
  $mail->AddAddress($recipient);
  if($mail->Send()) {
    return TRUE;
  }
  else {
    return FALSE;
  }
}


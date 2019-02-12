<?php
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
  $mysqli = new mysqli(JE_DB_HOST,JE_DB_USER,JE_DB_PW,JE_DB_NAME);
  if (!$mysqli) {
    $result['error'] = 'No connection';
  }
  else {
    $q = "SELECT * FROM ".JE_TABLE_NAME." WHERE userName='".$json['id']['userName']."'";
    $res = $mysqli->query($q);
    if ($res === FALSE) {
      //SQL statement failed
      $result['error'] = 'mysqli_error: '.mysqli_error($mysqli);
    }
    else {
      if ($res->num_rows > 0) {
        //this must be a new customer
        $res->data_seek(0);
        $row = $res->fetch_assoc();
        $result['error'] = 'Username: '.$row['userName'].' already registered.';
      }
      else {
        $barcode = new_barcode($json['id']['userName']);
        //check max 20 times in WMS whether the barcode already exists
        $repeat = 0;
        $max_repeats = 20;
        while (wms_barcode_exists($barcode) && ($repeat < $max_repeats)) {
          $repeat++;
          $barcode = new_barcode($json['id']['userName']);
        }

        if ($repeat < $max_repeats) {
          //barcode is indeed new in WMS
          $ppid = wms_create($barcode,$json);

          $code = hash('sha256',time().time());
          if (strlen($ppid) > 0) {
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
              $result['error'] = 'mysqli_error: '.mysqli_error($mysqli);
            }
            else {
              $result = array('code' => $code,'barcode' => $barcode);
              send_mail('activation',$json['id']['userName'],$json['person']['email'],$result);
            }
          }
          else {
            $result['error'] = 'wms_create failed.';
          }
        }
        else {
          $result['error'] = 'Could not generate a unique barcode.';
        }
      }
    }
    mysqli_close($mysqli);
  }
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
  $result = wms_activate($ppid, $barcode, $json);
  if ($result) {
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
      "WHERE userName='".$userName."'";
      $result = $mysqli->query($q);
    }
    mysqli_close($mysqli);

    if ($result === TRUE) send_mail('barcode',$userName,$json['person']['email'],array('barcode' => $barcode,'code' => $code));
  }
  return $result;
}

function update_customer($json) {
  $result = '';
  //get customer from database
  $row = get_customer_from_userName($json['id']['userName']);

  $result = wms_update($row['ppid'], $row['barcode'], json_decode($row['json'],TRUE));
  if ($result) {
    //update customer in databse
    $code = '0';
    $mysqli = new mysqli(JE_DB_HOST,JE_DB_USER,JE_DB_PW,JE_DB_NAME);
    if (!$mysqli) {
      $result = FALSE;
    }
    else {
      //now change record (passwd, json, activationCode, datetime)
      $code = hash('sha256',time().time());
      $q = "UPDATE ".JE_TABLE_NAME." SET ".
      "passwd='".$json['id']['password']."', ".
      "json='".json_encode($json)."',".
      "activationCode='$code',".
      "datetime=NOW()".
      "WHERE userName='".$json['id']['userName']."'";
      $result = $mysqli->query($q);
    }
    mysqli_close($mysqli);

    //send email?? -- if ($result === TRUE) send_mail('change',$userName,$json['person']['email'],array('barcode' => $barcode,'code' => $code));
  }

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

/*
* checks whether the barcode is already used in WMS 
*
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
  'blocked' => 'true',
  'verified' => 'false'
  );
  //file_put_contents('form.json',json_encode($json, JSON_PRETTY_PRINT));

  $loader = new Twig_Loader_Filesystem(__DIR__);
  $twig = new Twig_Environment($loader, array(
  //specify a cache directory only in a production setting
  //'cache' => './compilation_cache',
  ));
  $scim_json = $twig->render('scim_user_template.json', $json);
  //file_put_contents('form_scim.json',json_encode($scim_json, JSON_PRETTY_PRINT));

  $patron = new Patron();
  $patron->create_patron($scim_json);
  //file_put_contents('form_response.json',json_encode($patron->create, JSON_PRETTY_PRINT));

  return array_key_exists('id',$patron->create) ? $patron->create['id'] : '';
}


/*
* activates a new customer in WMS
* meaning that blocked is set to FALSE and verified to TRUE
* 
*/
function wms_activate($ppid, $barcode, $json){
  $json['extra'] = array(
  'barcode' => $barcode,
  'date' => date("Y-m-d"),
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
function send_mail($type, $username, $email, $codes) {
  if ($type == 'activation') {
    $alt_message = "Dear customer,\n\n".
    "Thank you for applying for a library card online.\n".
    "Please copy the following URL in your browser:\n\n".
    JE_ACT_URL."?je_ac=".$codes['code']."\n\n".
    "Once your account is activated you will receive your username and password. (If nothing seems to happen, please also check your spam box).\n\n".
    "Kind regards,\n".
    "Peace Palace Library\n\n\n".
    '-- '.$codes['code'].' --';
    
    $message = '<p>Dear customer,</p>'.
    '<p>Thank you for applying for a library card online.</p>'.
    '<p>Please click on this <a href="'.JE_ACT_URL.'?je_ac='.$codes['code'].'">link</a> to activate your account.</p>'.
    '<p>You can alsp copy the following URL in your browser:<br/>'.
    JE_ACT_URL.'?je_ac='.$codes['code'].'</p>'.
    '<p>Once your account is activated you will receive your username and password. (If nothing seems to happen, please also check your spam box).</p><br/>'.
    '<p>Kind regards,<br/>'.
    'Peace Palace Library</p><br/>';
    '-- '.$codes['code'].' --';
  }
  else if ($type == 'barcode') {
    $alt_message = "Dear customer,\n\n".
    "Please find your Library card number below.\n\n".
    "user name  : ".$username."\n\n".
    "card number: ".$codes['barcode']."\n\n".
    "You can now make reservations and borrow books from our library. Visit our catalogue at http://catalogue.ppl.nl/\n\n".
    "Please visit the library to obtain a library card and access to online available publications. You will have to show this email for verification.\n\n".
    "Don't forget you need a valid ID when coming to the library. Your books are kept for 3 working days.\n\n".
    "Kind regards,\n\n".
    "Peace Palace Library, Reading Room Staff\n\n".
    "website: http://www.peacepalacelibrary.nl\n".
    "e-mail: peacelib@ppl.nl\n".
    "telephone: +31-(0)70-3024242\n".
    "read our privacy policy: http://www.peacepalacelibrary.nl/privacy/\n\n\n".
    "----------------\n".
    "If, for any reason, you are getting this e-mail by mistake, please hit the reply button and tell us to unsubscribe. Sorry for the inconvenience.\n\n\n".
    '<< '.$codes['code'].'  >>';
    
    $message = 'Dear customer,</p>'.
    '<p>Please find your Library card number below.</p>'.
    '<p>user name  : '.$username.'<br/>'.
    'card number: '.$codes['barcode'].'</p>'.
    '<p>You can now make reservations and borrow books from our library. Visit our catalogue at <a href="http://catalogue.ppl.nl/">http://catalogue.ppl.nl/</a></p>'.
    '<p>Please visit the library to obtain a library card and access to online available publications. You will have to show this email for verification.</p>'.
    '<p>Do not forget you need a valid ID when coming to the library. Your books are kept for 3 working days.</p>'.
    '<p>Kind regards,</p>'.
    '<p>Peace Palace Library, Reading Room Staff</p>'.
    '<p>website: http://www.peacepalacelibrary.nl</p>'.
    '<p>e-mail: peacelib@ppl.nl</p>'.
    '<p>telephone: +31-(0)70-3024242</p>'.
    '<p>read our privacy policy: http://www.peacepalacelibrary.nl/privacy/</p>'.
    '<p>----------------</p>'.
    '<p>If, for any reason, you are getting this e-mail by mistake, please hit the reply button and tell us to unsubscribe. Sorry for the inconvenience.</p><br/>'.
    '<< '.$codes['code'].'  >>';
  }
  else {
    return FALSE;
  }
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
  $mail->AddAddress('f.latum@ppl.nl');//$email);
  if($mail->Send()) {
    return TRUE;
  }
  else {
    return FALSE;
  }
}


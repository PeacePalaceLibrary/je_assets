<?php

require_once 'OCLC/Auth/WSKey.php';
require_once 'OCLC/User.php';

/**
* A class that represents a patron
*/
class Patron {

  public $errors = [];
  private $error_log = __DIR__.'/../patron_error';
  private $logging = 'all'; //'none','errors','all' (not yet implemented

  //must be provided as parameters in $patron = new Patron($wskey,$secret,$ppid), see __construct
  private $wskey = null;
  private $secret = null;
  private $ppid = null;

  public $institution = "57439";
  public $defaultBranch = "262638";

  //$ppid_namespace is extended in __construct
  public $ppid_namespace = "urn:oclc:platform:";

  public $token_url = 'https://authn.sd00.worldcat.org/oauth2/accessToken';
  public $token_method = 'POST';
  public $token_POST = true;
  public $token_headers = ['Accept: application/json'];

  public $token_params = [
  'grant_type' => 'client_credentials',
  'authenticatingInstitutionId' => '57439',
  'contextInstitutionId' => '57439',
  'scope' => 'SCIM'
  ];


  //$read_url and search_url are extended in __construct
  public $idm_url = "share.worldcat.org/idaas/scim/v2/Users";

  public $read_url = "";
  public $read_headers = ['Accept: application/scim+json'];

  public $search_url = "";
  public $search_headers = ['Accept: application/scim+json',
  'Content-Type: application/scim+json'];
  public $search_method = 'POST';
  public $search_POST = true;

  public $create_url = "";
  public $create_headers = [
  'Content-Type: application/scim+json'];
  public $create_method = 'POST';
  public $create_POST = true;
  
  public $update_url = "";
  public $update_headers = [
  'Content-Type: application/scim+json'];
  public $update_method = 'PUT';
  

  public $patron = null;
  public $search = null;
  public $create = null;
  public $update = null;

  public function __construct() {

    require(__DIR__.'/key_idm.php');
    $this->wskey = $config_idm['wskey'];
    $this->secret = $config_idm['secret'];
    $this->ppid = $config_idm['ppid'];

    $this->ppid_namespace = $this->ppid_namespace.$this->institution;

    //https://{institution-identifier}.share.worldcat.org/idaas/scim/v2/Users/{id}
    $this->read_url = 'https://'.$this->institution.'.'.$this->idm_url;

    //https://{institution-identifier}.share.worldcat.org/idaas/scim/v2/Users/.search
    $this->search_url = 'https://'.$this->institution.'.'.$this->idm_url.'/.search';

    //https://{institution-identifier}.share.worldcat.org/idaas/scim/v2/Users
    $this->create_url = 'https://'.$this->institution.'.'.$this->idm_url;
    
    //https://{institution-identifier}.share.worldcat.org/idaas/scim/v2/Users/{id}
    $this->update_url = 'https://'.$this->institution.'.'.$this->idm_url; 
  }

  public function __toString(){
    //create an array and return json_encoded string
    $json = [
    'errors' => $this->errors,

    'institution' => $this->institution,
    'defaultBranch' => $this->defaultBranch,

    'ppid_namespace' => $this->ppid_namespace,
    'token_url' => $this->token_url,
    'token_method' => $this->token_method,
    'token_POST' => $this->token_POST,
    'token_headers' => $this->token_headers,
    'token_params' => $this->token_params,
    'idm_url' => $this->idm_url,
    'read_url' => $this->read_url,
    'read_headers' => $this->read_headers,
    'search_url' => $this->search_url,
    'search_headers' => $this->search_headers,
    'search_method' => $this->search_method,
    'search_POST' => $this->search_POST,
    'update_url' => $this->update_url,
    'update_headers' => $this->update_headers,
    'update_method' => $this->update_method,

    'patron' => $this->patron,
    'search' => $this->search,
    'create' => $this->create,
    'update' => $this->update,

    ];
    return json_encode($json, JSON_PRETTY_PRINT);
  }

  private function log_entry($t,$c,$m) {
    $this->errors[] = date("Y-m-d H:i:s")." $t [$c] $m";
    $name = $this->error_log.'.'.date("Y-W").'.log';
    return file_put_contents($name, date("Y-m-d H:i:s")." $t [$c] $m\n", FILE_APPEND);
  }

  private function get_auth_header($url,$method) {
    //get an authorization header
    //  with wskey, secret and if necessary user data from $config
    //  for the $method and $url provided as parameters

    $authorizationHeader = '';
    if ($this->wskey && $this->secret) {
      $options = array();
      if ($this->institution && $this->ppid && $this->ppid_namespace) {
        //uses OCLC provided programming to get an autorization header
        $user = new User($this->institution, $this->ppid, $this->ppid_namespace);
        $options['user'] = $user;
      }
      //echo "Options: ".json_encode($options, JSON_PRETTY_PRINT);
      if (count($options) > 0) {
        $wskeyObj = new WSKey($this->wskey, $this->secret, $options);
        $authorizationHeader = $wskeyObj->getHMACSignature($method, $url, $options);
      }
      else {
        $wskeyObj = new WSKey($config['wskey'], $config['secret'],null);
        $authorizationHeader = $wskeyObj->getHMACSignature($method, $url, null);
      }
      //check??
      $authorizationHeader = 'Authorization: '.$authorizationHeader;
    }
    else {
      $this->log_entry('Error','get_pulllist_auth_header','No wskey and/or no secret!');
    }
    return $authorizationHeader;
  }

  private function get_access_token_authorization() {
    $token_authorization = "";
    $authorizationHeader = $this->get_auth_header($this->token_url,$this->token_method);
    if (strlen($authorizationHeader) > 0) {
      array_push($this->token_headers,$authorizationHeader);
    }
    else {
      $this->log_entry('Error','get_access_token_authorization','No authorization header created!');
    }

    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $this->token_url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $this->token_headers);
    curl_setopt($curl, CURLOPT_POST, $this->token_POST);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($this->token_params));
    //echo http_build_query($this->token_params);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    //curl_setopt($curl, CURLOPT_, );
    //curl_setopt($curl, CURLOPT_, );

    $result = curl_exec($curl);
    $error_number = curl_errno($curl);
    $error_msg = curl_error($curl);
    curl_close($curl);

    if ($result === FALSE) {
      $this->log_entry('Error','get_access_token_authorization','No result on cUrl request!');
      if ($error_number) $this->log_entry('Error','get_access_token_authorization',"No result, cUrl error [$error_number]: $error_msg");
      return FALSE;
    }
    else {
      if (strlen($result) == 0) {
        $this->log_entry('Error','get_access_token_authorization','Empty result on cUrl request!');
        if ($error_number) {
          $this->log_entry('Error','get_access_token_authorization',"Empty result, cUrl error [$error_number]: $error_msg");
        }
        return FALSE;
      }
      else {
        if ($error_number) {
          $this->log_entry('Error','get_access_token_authorization',"Result but still cUrl error [$error_number]: $error_msg");
        }
        $token_array = json_decode($result,TRUE);
        $json_errno = json_last_error();
        $json_errmsg = json_last_error_msg();
        if ($json_errno == JSON_ERROR_NONE) {
          if (array_key_exists('access_token',$token_array)){
            $token_authorization = 'Authorization: Bearer '.$token_array['access_token'];
          }
          else {
            $this->log_entry('Error','get_access_token_authorization',"No access_token returned (curl result: ".$result.")");
            return FALSE;
          }
        }
        else {
          $this->log_entry('Error','get_access_token_authorization',"json_decode error [$json_errno]: $json_errmsg");
          return FALSE;
        }
      }
    }
    return $token_authorization;
  }

  public function read_patron_ppid($id) {
    //authorization
    $token_authorization = $this->get_access_token_authorization();
    if (strlen($token_authorization) > 0) {
      array_push($this->read_headers,$token_authorization);
    }
    else {
      $this->log_entry('Error','read_patron_ppid','No token authorization header created!');
    }
    
    
    $url = $this->read_url.'/'.$id;
    //CURL
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $this->read_headers);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    //curl_setopt($curl, CURLOPT_, );
    //curl_setopt($curl, CURLOPT_, );

    $result = curl_exec($curl);
    $error_number = curl_errno($curl);
    $error_msg = curl_error($curl);
    curl_close($curl);

    if ($result === FALSE) {
      $this->log_entry('Error','read_patron_ppid','No result on cUrl request!');
      if ($error_number) $this->log_entry('Error','read_patron_ppid',"No result, cUrl error [$error_number]: $error_msg");
      return FALSE;
    }
    else {
      if (strlen($result) == 0) {
        $this->log_entry('Error','read_patron_ppid','Empty result on cUrl request!');
        if ($error_number) {
          $this->log_entry('Error','read_patron_ppid',"Empty result, cUrl error [$error_number]: $error_msg");
        }
        return FALSE;
      }
      else {
        if ($error_number) {
          $this->log_entry('Error','read_patron_ppid',"Result but still cUrl error [$error_number]: $error_msg");
        }
        $patron_received = json_decode($result,TRUE);
        $json_errno = json_last_error();
        $json_errmsg = json_last_error_msg();
        if ($json_errno == JSON_ERROR_NONE) {
          //store result in this object as an array
          $this->patron = $patron_received;
          return TRUE;
        }
        else {
          $this->log_entry('Error','read_patron_ppid',"json_decode error [$json_errno]: $json_errmsg");
          return FALSE;
        }
      }
    }
  }

  public function get_barcode() {
    $barcode = '';
    if ($this->patron && array_key_exists('externalId', $this->patron)) {
      $barcode = $this->patron['externalId'];
    }
    return $barcode;
  }

  public function search_patron($search) {
    //authorization
    $token_authorization = $this->get_access_token_authorization();
    array_push($this->search_headers,$token_authorization);

    //CURL
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $this->search_url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $this->search_headers);
    curl_setopt($curl, CURLOPT_POST, $this->search_POST);
    curl_setopt($curl, CURLOPT_POSTFIELDS,$search);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    //curl_setopt($curl, CURLOPT_, );
    //curl_setopt($curl, CURLOPT_, );

    $result = curl_exec($curl);
    $error_number = curl_errno($curl);
    curl_close($curl);


    if ($error_number) {
      //return info in json format
      $result = '{"Curl_errno": "'.$error_number.'", "Curl_error": "'.curl_error($curl).'"}';
      $this->errors['curl'] = json_decode($result,TRUE);
      return false;
    }
    else {
      //store result in this object as an array
      $this->search = json_decode($result,TRUE);

      return true;
    }
  }

  /*
  For a user which wants to use the Circulation portion of WorldShare Management Services we reccomend at least the following fields:

  givenName
  familyName
  email
  circulationInfo section
  barcode
  borrowerCategory
  homeBranch
  */

  public function create_patron($scim_json) {

    //authorization
    $token_authorization = $this->get_access_token_authorization();
    //echo $token_authorization;
    array_push($this->create_headers,$token_authorization);

    //CURL
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $this->create_url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $this->create_headers);
    curl_setopt($curl, CURLOPT_POST, $this->create_POST);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $scim_json);

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    //curl_setopt($curl, CURLOPT_, );
    //curl_setopt($curl, CURLOPT_, );

    $result = curl_exec($curl);
    $error_number = curl_errno($curl);
    curl_close($curl);


    if ($error_number) {
      //return info in json format
      $result = '{"Curl_errno": "'.$error_number.'", "Curl_error": "'.curl_error($curl).'"}';
      $this->errors['curl'] = json_decode($result,TRUE);
      return false;
    }
    else {
      //store result in this object as an array
      $this->create = json_decode($result,TRUE);

      return $result;

    }
  }

  public function update_patron($ppid, $scim_json) {
    //$ppid must be the value of the "id" key in scim json
    
    //authorization
    $token_authorization = $this->get_access_token_authorization();
    //echo $token_authorization;
    array_push($this->update_headers,$token_authorization);

    //CURL
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $this->update_url.'/'.$ppid);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $this->update_headers);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->update_method);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $scim_json);

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    //curl_setopt($curl, CURLOPT_, );
    //curl_setopt($curl, CURLOPT_, );

    $result = curl_exec($curl);
    $error_number = curl_errno($curl);
    curl_close($curl);


    if ($error_number) {
      //return info in json format
      $result = '{"Curl_errno": "'.$error_number.'", "Curl_error": "'.curl_error($curl).'"}';
      $this->errors['curl'] = json_decode($result,TRUE);
      return false;
    }
    else {
      //store result in this object as an array
      $this->update = json_decode($result,TRUE);

      return $result;
    }
  }
}

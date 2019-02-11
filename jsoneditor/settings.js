
//set debug to false in a production environment
var debug = true; 

/*
* Assets url in the directory of the active worpress theme
*
*/
// development
var assetsURL = window.location.origin+'/oclcAPIs/register/je_assets';
// production in Wordpress
//var assetsURL = window.location.origin+'/wp-content/themes/ppl2/je_assets';
// production stand alone
//var assetsURL = window.location.origin+'???/je_assets';

var registerUrl = assetsURL + "/php/register.php";
var signInUrl   = assetsURL + '/php/signin.php';
var changeUrl   = assetsURL + "/php/change_reg.php";

var accountUrl  = window.location.origin + '/oclcAPIs/register/account.php';

//JSONEditor.plugins.selectize.enable = true;  //selectize.js must be equeued in wordpress if you want to use it
JSONEditor.defaults.options.keep_oneof_values = false; //oneof is not used in the schema's
JSONEditor.defaults.options.theme = 'barebones';

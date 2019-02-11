<?php
require_once(__DIR__.'/php/helpers.php');

//add json-editor javascripts
function json_editor_scripts() {
  global $post;
  if( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'je_form') ) {
    //enqueue stylesheets??
    wp_enqueue_script( 'json-editor', get_theme_file_uri('/je_assets/jsoneditor/jsoneditor.min.js'), $deps = array(), $ver = null, $in_footer = false);
    wp_enqueue_script( 'json-editor-validators', get_theme_file_uri('/je_assets/jsoneditor/regValidators.js'), $deps = array(), $ver = null, $in_footer = false);
    wp_enqueue_script( 'json-editor-settings', get_theme_file_uri('/je_assets/jsoneditor/settings.js'), $deps = array(), $ver = null, $in_footer = false);
  }
}
add_action( 'wp_enqueue_scripts', 'json_editor_scripts' );

//add parameter je_ac to be used in URL's
//in Wordpress URL parameters are ignored and cannot be used in code unless registered in Wordpress
function add_query_vars_filter( $vars ){
  $vars[] = "je_ac";
  return $vars;
}
add_filter( 'query_vars', 'add_query_vars_filter' );

//add processing for shortcode je_form name=...
function json_editor_shortcode_handler( $atts ) {
  if (array_key_exists('name',$atts)) {
    
    echo '<div id = "editorDiv">';
    echo '<div id="editor"></div>';
    
    switch($atts['name']) {
    
      case 'register':
      echo '<button id="submit">Send</button>';
      echo '<button id="empty">Empty form</button>';
      echo '<script type="text/javascript" src="'.get_theme_file_uri( '/je_assets/jsoneditor/lists.js' ).'"></script>';
      echo '<script type="text/javascript" src="'.get_theme_file_uri( '/je_assets/jsoneditor/regSchema.js' ).'"></script>';
      echo '<script type="text/javascript" src="'.get_theme_file_uri( '/je_assets/jsoneditor/regForm.js' ).'"></script>';
      break;

      case 'account':
      if (isset($_GET['je_ac'])) {
        $record = get_customer_from_code($_GET['je_ac']);
        if ($record){
          echo '<script>';
          echo "formValues = JSON.parse('".$record['json']."');";
          echo '</script>';
          echo '<button id="submit">Send</button>';
          //echo '<button id="empty">Empty form</button>';
          echo '<script type="text/javascript" src="'.get_theme_file_uri( '/je_assets/jsoneditor/lists.js' ).'"></script>';
          echo '<script type="text/javascript" src="'.get_theme_file_uri( '/je_assets/jsoneditor/regSchema.js' ).'"></script>';
          echo '<script type="text/javascript" src="'.get_theme_file_uri( '/je_assets/jsoneditor/accountForm.js' ).'"></script>';
        }
      }
      else {
        echo '<p>Please <a href="'.get_site_url(null,'sign-in').'">sign in</a> first. </p>' ;
      }
      break;

      case 'sign_in':
      echo '<button id="submitSignIn">Send</button>';
      //echo '<button id="forgot">Forgot your password?</button>';
      echo '<script type="text/javascript" src="'.get_theme_file_uri( '/je_assets/jsoneditor/siSchema.js' ).'"></script>';
      echo '<script type="text/javascript" src="'.get_theme_file_uri( '/je_assets/jsoneditor/siForm.js' ).'"></script>';
      break;

      case 'activation':
      if (isset($_GET['je_ac'])) {
        echo '<p>Your activation request is being processed. Please wait...</p>';
        $record = get_customer_from_code($_GET['je_ac']);
        if ($record){
          if ($record['activated']) {
            echo '<p>Membership already activated.</p>';
          }
          else {
            $json = json_decode($record['json'], TRUE);
            $activated = activate_customer($record['ppid'], $record['userName'], $record['barcode'], $json );
            if ($activated) {
              echo '<p>Membership is activated.</p>';
            }
            else {
              echo '<p>Membership has NOT been activated. Please contact the services librarian at the desk.</p>';
            }
          }
        }
        else echo '<p>The activation key is no longer valid, register again.</p>';
      }
      else echo '<p>You have to use an url with an activation code.</p>' ;
      break;
    }
    echo '<div id="res" class="alert"></div>';
    echo '</div>';
  }
}
add_shortcode( 'je_form', 'json_editor_shortcode_handler' );

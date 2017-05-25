<?php
/*
Plugin Name: Advanced Concurrent Login Limit
Plugin URI: http://upscalethought.com
Description: Advanced Wordpress Concurrent Login Limit Management system. You can control a Users Number of Concurrent Login. If Limit exceed an error will shown and email notification will be sent.
Version: 1.0
Author: UpScaleThought
Author URI: http://upscalethought.com
Text Domain: advanced-limitlogin
Domain Path: /i18n/languages/  
*/
define('GEN_USTS_LIMITLOGIN_PLUGIN_URL', plugins_url('',__FILE__));
define("GEN_USTS_LIMITLOGIN_BASE_URL", WP_PLUGIN_URL.'/'.plugin_basename(dirname(__FILE__)));
define('GEN_USTS_LIMITLOGIN_DIR', plugin_dir_path(__FILE__) );

include_once('operations/alimitl_advanced_limitlogin_init.php');
//--------i18n--------
function gen_alimitl_load_plugin_textdomain() {
  load_plugin_textdomain( 'advanced-limitlogin', FALSE, basename( dirname( __FILE__ ) ) . '/i18n/languages/' );
}
add_action( 'plugins_loaded', 'gen_alimitl_load_plugin_textdomain' );
//---------------------------

add_action('admin_menu', 'gen_alimitl_plugin_admin_menu');
function gen_alimitl_plugin_admin_menu(){
	add_object_page('Advanced LimitLogin', 'Advanced LimitLogin', 'publish_posts', 'custom_alimitlogin', 'gen_alimitl_settings_menu');
}

function gen_alimitl_settings_menu(){
?>
	<div> <h2><?php _e("Advanced LimitLogin","advanced-limitlogin"); ?></h2></div>
 <?php
}

function gen_alimitlogin_add_menu(){
    add_submenu_page( 'custom_alimitlogin', 'User Limit Settings', 'User Limit Settings', 'manage_options', 'user-limit-setting-menu', 'gen_alimitl_userlimit_settings' );
}
function gen_alimitl_userlimit_settings(){
	include_once('includes/users_loginlimit_settings.php');
}
add_action('admin_menu','gen_alimitlogin_add_menu');

register_activation_hook( __FILE__, 'alimitl_advanced_limitlogin_install' );
register_deactivation_hook( __FILE__, 'alimitl_advanced_limitlogin_uninstall');
//========================================================
add_filter('authenticate', 'gen_check_login', 100, 3);
function gen_check_login($user, $username, $password) {
	global $table_prefix,$wpdb;
    // this filter is called on the log in page
    // make sure we have a username before we move forward
    if (!empty($username)) {
        $user_data = $user->data;
		//die(print_r($user_data->ID));
		//
		$count_key = "_gen_user_login_count";
		$limit_key = "_gen_user_cc_login_limit";
		//$current_user = wp_get_current_user();
		$userid = $user_data->ID;
		$email = $user_data->user_email;
		$admin_email = get_option('admin_email');
		$to = $email.','.$admin_email;

		$cclogin_count = get_user_meta($userid, $count_key, true);
		$cclogin_limit = get_user_meta($userid, $limit_key, true);
		//
		$error_code = 'max_session_reached';
	    $error_message = "Maximum $cclogin_limit login sessions are allowed. Please contact site administrator.";
		//
		if(($cclogin_count != "" || $cclogin_count != NULL) && ($cclogin_limit !="" || $cclogin_limit !=NULL)){
			if ($cclogin_count >= $cclogin_limit) {
			  //get all user
			  $sql = "select * from ".$table_prefix."users order by id ASC";
			  $users = $wpdb->get_results($sql);
			   
			  //die(print_r($users));	
			  gen_alimitl_send_email($to);
			  //return null;
			  return new WP_Error($error_code, $error_message);
			}
			else {
				return $user;
			}
		}
    }

    return $user;
}
function gen_alimitl_send_email($to){
	$subject = "Login Limit Exceeds!";
	$msg  = __( 'Hello!', 'advanced-limitlogin' ) . "\r\n\r\n";
    $msg .= sprintf( __( 'Concurrent Login Limit for you Exceeds.', 'advanced-limitlogin' ), $user_login ) . "\r\n\r\n";
    $msg .= __( "So you cannot login until some other login of your ID logs out.", 'advanced-limitlogin' ) . "\r\n\r\n";
    $msg .= __( 'Please logout from other Browser.', 'advanced-limitlogin' ) . "\r\n\r\n";
    $msg .= __( 'Thanks!', 'advanced-limitlogin' ) . "\r\n";
 	
	wp_mail( $to, $subject, $msg);
    //return $msg;
} 
function gen_alimitl_login_function($user_login, $user) {
	global $table_prefix,$wpdb;
	$key = "_gen_user_login_count";
	//$current_user = wp_get_current_user();
	 $user_data = $user->data;
	 
	$userid = $user_data->ID;
	//$cclogin_count = get_user_meta($userid, $key, false);
	$sql = "select * from ".$table_prefix."usermeta where user_id=".$userid." and meta_key='_gen_user_login_count'";
	$metavaluesin = $wpdb->get_results($sql);
	//die(print_r($metavalues[0]->meta_value));
	$cclogin_count = $metavaluesin[0]->meta_value;
	$cclogin_count = $cclogin_count + 1;
	/*$cclogin_count = 0;
	if($cclogin_limit !="" || $cclogin_limit !=NULL){
		$cclogin_count = $cclogin_limit;
		$cclogin_count = ($cclogin_count+1);
	}
	else{
		$cclogin_count = 1;
	}*/
	update_user_meta( $userid, $key, $cclogin_count );
}
add_action('wp_login', 'gen_alimitl_login_function', 10, 2);
function gen_alimitl_logout_function() {
	global $table_prefix,$wpdb;
	$key = "_gen_user_login_count";
	$current_user = wp_get_current_user();
	$userid = $current_user->ID;
	//$cclogout_count = get_user_meta($userid, $key, false);
	$sql = "select * from ".$table_prefix."usermeta where user_id=".$userid." and meta_key='_gen_user_login_count'";
	$metavaluesout = $wpdb->get_results($sql);
	
	$cclogout_count = $metavaluesout[0]->meta_value;
	if($cclogout_count>0){
		$cclogout_count = $cclogout_count - 1;
	}
	else{
		$cclogout_count = 0;
	}
	/*$cclogin_count = 0;
	if($cclogin_limit !="" || $cclogin_limit !=NULL){
		$cclogin_count = $cclogin_limit;
		if($cclogin_count>0){
			$cclogin_count = ($cclogin_count - 1);
		}
		else{
			$cclogin_count = 0;
		}
	}
	else{
		$cclogin_count = 0;
	}*/
	update_user_meta( $userid, $key, $cclogout_count );
}
add_action('wp_logout', 'gen_alimitl_logout_function');
//==========================AJAX Calls===============================
function gen_alimitl_update_limitlogin(){
	$userid = $_REQUEST['user_id'];
	$cclogin_limit = $_REQUEST['login_limit'];
	update_user_meta( $userid, "_gen_user_cc_login_limit", $cclogin_limit );
}
add_action( 'wp_ajax_nopriv_gen_alimitl_update_limitlogin','gen_alimitl_update_limitlogin' );
add_action( 'wp_ajax_gen_alimitl_update_limitlogin', 'gen_alimitl_update_limitlogin' );
function gen_alimitl_keeptrack_user_idletime(){
	$tevent = $_REQUEST['tevent'];
	$current_user = wp_get_current_user();
	$userid = $current_user->ID;
	if($tevent=='idle'){
		update_user_meta($userid,"_gen_is_user_idle",1);
	}
	else if($tevent=='active'){
		update_user_meta($userid,"_gen_is_user_idle",0);	
	}
	
}
add_action( 'wp_ajax_nopriv_gen_alimitl_keeptrack_user_idletime','gen_alimitl_keeptrack_user_idletime' );
add_action( 'wp_ajax_gen_alimitl_keeptrack_user_idletime', 'gen_alimitl_keeptrack_user_idletime' );
//====================== Enque Scripts ========================================
function gen_alimitl_limitloginjs(){
	wp_register_script('limitloginjs',plugins_url('/assets/js/trackidle.js',__FILE__));
	wp_localize_script( 'limitloginjs', 'ustsLimitloginAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' )));
	
	wp_enqueue_script( 'limitloginjs');
}
//add_action('admin_enqueue_scripts','alimitl_limitloginjs');
add_action('admin_footer','gen_alimitl_limitloginjs');

function gen_alimitl_jqueryidlejs(){
	wp_register_script('jquery.idle',plugins_url('/assets/js/jquery.idle/jquery.idle.js',__FILE__), array( 'jquery' ));

	wp_enqueue_script( 'jquery');
	wp_enqueue_script( 'jquery.idle');
}
add_action('admin_enqueue_scripts','gen_alimitl_jqueryidlejs');
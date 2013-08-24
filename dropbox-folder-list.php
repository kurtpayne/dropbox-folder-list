<?php

/*
Plugin Name: Dropbox Folder List
Plugin URI: http://kpayne.me/
Description: Show the contents of dropbox folder in the_content filter
Author: Kurt Payne
Version: 1.0
Author URI: http://kpayne.me/
License: GPLv2
*/

$dfl_plugin = new Dropbox_Folder_List();

register_activation_hook( __FILE__, array( 'Dropbox_Folder_List', 'activate' ) );
register_uninstall_hook( __FILE__, array( 'Dropbox_Folder_List', 'uninstall' ) );

class Dropbox_Folder_List {
	
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'settings_page' ) );
		add_filter( 'the_content', array( $this, 'show_dropbox_folder' ) );
		add_action( 'init', array( $this, 'add_dropbox_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'dropbox_template_redirect' ) );
	}

	public function add_dropbox_endpoint() {
		add_rewrite_endpoint( 'dropbox', EP_ALL );
	}
	
	public function dropbox_template_redirect() {
		global $wp_query;
		
		// if this is not a request for dropbox or a singular object then bail
		if ( !isset( $wp_query->query_vars['dropbox'] ) ) {
			return;
		}

		// Get the dropbox libraries
		$files = array();
		set_include_path( get_include_path() . PATH_SEPARATOR . dirname( __FILE__ ) . '/HTTP_OAuth' );
		require("Dropbox/autoload.php");
		require("HTTP_OAuth/HTTP/OAuth.php");

		// Oauth bits
		$token = get_option( 'dfl_plugin_token_key' );
		$token_secret = get_option( 'dfl_plugin_token_secret' );
		$consumer_key = get_option( 'dfl_plugin_consumer_key' );
		$consumer_secret = get_option( 'dfl_plugin_consumer_secret' );

		// Connect to dropbox
		$oauth = new Dropbox_OAuth_PEAR( get_option( 'dfl_plugin_consumer_key' ), get_option( 'dfl_plugin_consumer_secret' ) );	
		$dropbox = new Dropbox_API( $oauth );
		$oauth->setToken( array(
			'token'        => $token,
			'token_secret' => $token_secret
		) );
		
		// Get the folder listing
		try {
			$file = $dropbox->getFile( '/' . rtrim( $wp_query->query_vars['dropbox'], '/' ) );
			header( 'Content-type: application/octet-stream');
			header( 'Content-disposition: attachment; filename="' . basename( rtrim( $wp_query->query_vars['dropbox'], '/' ) ) . '"');
			echo $file;
		} catch ( Exception $e ) {
		}
	}

	public function show_dropbox_folder( $content ) {
		global $post;
		
		// Check for a page type
		if ( 'page' !== $post->post_type ) {
			return $content;
		}
		
		// Get the slug of the page
		$permalink = get_permalink( $post );
		$permalink = str_replace( home_url(), '', $permalink );
		$permalink = rtrim( $permalink, '/' );

		// If we're connected, then get dropbox libs
		if ( intval( get_option( 'dfl_plugin_oauth_state', 1 ) ) >= 4 ) {
			$files = $this->getFolderListing( $permalink );
			include( dirname( __FILE__ ) . '/template-listing.php' );
		}

		return $content;
	}
	
	protected function getFolderListing( $folder ) {

		// See if the results are cached
		$transient = get_transient( 'dfl_folder_' . md5( $folder ) );
		if ( !empty( $transient ) ) {
			return $transient;
		}

		// Get the dropbox libraries
		$files = array();
		set_include_path( get_include_path() . PATH_SEPARATOR . dirname( __FILE__ ) . '/HTTP_OAuth' );
		require("Dropbox/autoload.php");
		require("HTTP_OAuth/HTTP/OAuth.php");

		// Oauth bits
		$token = get_option( 'dfl_plugin_token_key' );
		$token_secret = get_option( 'dfl_plugin_token_secret' );
		$consumer_key = get_option( 'dfl_plugin_consumer_key' );
		$consumer_secret = get_option( 'dfl_plugin_consumer_secret' );

		// Connect to dropbox
		$oauth = new Dropbox_OAuth_PEAR( get_option( 'dfl_plugin_consumer_key' ), get_option( 'dfl_plugin_consumer_secret' ) );	
		$dropbox = new Dropbox_API( $oauth );
		$oauth->setToken( array(
			'token'        => $token,
			'token_secret' => $token_secret
		) );
		
		// Get the folder listing
		try {
			$files = $dropbox->getMetaData( $folder );				
		} catch ( Exception $e ) {
		}
		
		// Save to cache
		set_transient( 'dfl_folder_' . md5( $folder ), $files, 300 );
		return $files;
	}

	public static function activate() {
		$opts = array(
			'dfl_plugin_consumer_key',
			'dfl_plugin_consumer_secret',
			'dfl_plugin_token_key',
			'dfl_plugin_token_secret',
			'dfl_plugin_oauth_state',
			'dfl_plugin_oauth_access_tokens',
		);
		foreach ( $opts as $opt ) {
			$_opt = get_option( $opt );
			if ( empty( $_opt ) ) {
				update_option( $opt, '' );
			}
		}
	}
	
	public static function uninstall() {
		if ( defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			$opts = array(
				'dfl_plugin_consumer_key',
				'dfl_plugin_consumer_secret',
				'dfl_plugin_token_key',
				'dfl_plugin_token_secret',
				'dfl_plugin_oauth_state',
				'dfl_plugin_oauth_access_tokens',
			);
			foreach ( $opts as $opt ) {
				delete_option( $opt );
			}
		}
	}
	
	public function register_settings() {
		$section = 'dfl_plugin_settings_section';
		$group = 'dfl_plugin_settings_group';
		add_settings_section( $section, __( 'Settings', 'dfl_plugin' ), '__return_null', 'dfl_plugin-settings' );
		add_settings_field(
			'dfl_plugin_consumer_key',
			__( 'Dropbox application key', 'dfl_plugin' ),
			array( $this, 'show_dfl_application_key' ),
			'dfl_plugin-settings',
			$section
		);
		register_setting( $group, 'dfl_plugin_consumer_key' );
		add_settings_field(
			'dfl_plugin_consumer_secret',
			__( 'Dropbox secret key', 'dfl_plugin' ),
			array( $this, 'show_dfl_secret_key' ),
			'dfl_plugin-settings',
			$section
		);
		register_setting( $group, 'dfl_plugin_consumer_secret' );
	}

	public function settings_page() {
		add_options_page( __( 'Dropbox Folder List', 'dfl_plugin' ), __( 'Dropbox Folder List', 'dfl_plugin' ), 'manage_options', 'dfl_plugin-settings', array( $this, 'plugin_options' ) );
	}
	
	public function plugin_options() {

		// Check permissions
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		
		// Unlink dropbox?
		if ( isset( $_REQUEST['reset_dropbox'] ) && 1 === intval( $_REQUEST['reset_dropbox'] ) ) {
			if ( !check_admin_referer( 'reset_dropbox' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
			}
			$opts = array(
				'dfl_plugin_token_key',
				'dfl_plugin_token_secret',
				'dfl_plugin_oauth_access_tokens',
			);
			foreach ( $opts as $opt ) {
				delete_option( $opt );
			}
			update_option( 'dfl_plugin_oauth_state', 1 );
			wp_redirect( remove_query_arg( 'reset_dropbox') );
			?>
			<script type="text/javascript">
				location.href='<?php echo esc_js( remove_query_arg( 'reset_dropbox' ) ); ?>';
			</script>
			<?php
			exit();
		}

		// Get the dropbox libraries
		set_include_path( get_include_path() . PATH_SEPARATOR . dirname( __FILE__ ) . '/HTTP_OAuth' );
		require("Dropbox/autoload.php");
		require("HTTP_OAuth/HTTP/OAuth.php");

		// Oauth bits
		$token = get_option( 'dfl_plugin_token_key' );
		$token_secret = get_option( 'dfl_plugin_token_secret' );
		$consumer_key = get_option( 'dfl_plugin_consumer_key' );
		$consumer_secret = get_option( 'dfl_plugin_consumer_secret' );

		?>
		<div class="wrap">
			<div id="icon-tools" class="icon32"><br/></div>
			<h2><?php _e( 'Dropbox Folder List', 'dfl_plugin' ); ?></h2>

			<form id="dfl_plugin_settings_form" name="dfl_plugin_form" method="post" action="options.php">
				
				<?php settings_fields( 'dfl_plugin_settings_group' ); ?>
				<?php do_settings_sections( 'dfl_plugin-settings' ); ?>
				<?php submit_button(); ?>

			</form>
			
			<?php
			
			// Several things can go wrong with dropbox auth:
			// 
			// 1. the user hasn't filled out the app key/secret
			// 2. they aren't connected
			// 3. we haven't saved the tokens (connection) properly
			// 4. the tokens were revoked
			
			?>
			
			<?php // Check for missing consumer key / secret ?>
			<?php if ( !empty( $consumer_key ) && !empty( $consumer_secret ) ) : ?>

				<?php // Connect to oauth ?>
				<?php
				if ( 2 === intval( get_option( 'dfl_plugin_oauth_state', 1 ) ) ) {
					try {
						$oauth = new Dropbox_OAuth_PEAR( get_option( 'dfl_plugin_consumer_key' ), get_option( 'dfl_plugin_consumer_secret' ) );	
						$dropbox = new Dropbox_API( $oauth );
						$oauth->setToken( get_option( 'dfl_plugin_oauth_access_tokens' ) );
						$tokens = $oauth->getAccessToken();
						update_option( 'dfl_plugin_oauth_state', 3 );
						$oauth->setToken( $tokens );
					} catch ( Exception $e ) {
						$opts = array(
							'dfl_plugin_token_key',
							'dfl_plugin_token_secret',
							'dfl_plugin_oauth_access_tokens',
						);
						foreach ( $opts as $opt ) {
							delete_option( $opt );
						}
						update_option( 'dfl_plugin_oauth_state', 1 );
					}
				}
				if ( 1 === intval( get_option( 'dfl_plugin_oauth_state', 1 ) ) ) {
					try {
						$oauth = new Dropbox_OAuth_PEAR( get_option( 'dfl_plugin_consumer_key' ), get_option( 'dfl_plugin_consumer_secret' ) );	
						$dropbox = new Dropbox_API( $oauth );
						$tokens = $oauth->getRequestToken();
						$url = $oauth->getAuthorizeUrl();
						update_option( 'dfl_plugin_oauth_access_tokens', $tokens );
						update_option( 'dfl_plugin_oauth_state', 2 );
						$oauth->setToken( $tokens );
					} catch ( Exception $e ) {
					}
				}
				?>

				<?php // Check for non-saved connection or invalid token received from oauth ?>
				<?php
				if ( 3 === get_option( 'dfl_plugin_oauth_state' ) && !empty( $tokens ) && is_array( $tokens ) && isset( $tokens['token']) && isset( $tokens['token_secret']) ) {
					$oauth = new Dropbox_OAuth_PEAR( get_option( 'dfl_plugin_consumer_key' ), get_option( 'dfl_plugin_consumer_secret' ) );	
					$dropbox = new Dropbox_API( $oauth );
					update_option( 'dfl_plugin_token_key', $tokens['token'] );
					update_option( 'dfl_plugin_token_secret', $tokens['token_secret'] );
					$token = get_option( 'dfl_plugin_token_key' );
					$token_secret = get_option( 'dfl_plugin_token_secret' );
					update_option( 'dfl_plugin_oauth_state', 4 );					
				}
				?>

				<?php // Check for revocation ?>
				<?php if ( !empty( $token ) && !empty( $token_secret ) ) : ?>
					<?php
					$oauth->setToken( array(
						'token'        => $token,
						'token_secret' => $token_secret
					) );
					try {
						$info = $dropbox->getAccountInfo();
					} catch( Exception $e ) {
						update_option( 'dfl_plugin_token_key', '' );
						update_option( 'dfl_plugin_token_secret', '' );
					}
					?>
				<?php endif; ?>

				<?php // Controls ?>
				<?php if ( !isset( $info ) || empty( $info ) ) : ?>
					<p><a class="button-secondary" href="<?php echo esc_attr( esc_url( $url ) ); ?>" target="_blank"><?php _e( 'Link to Dropbox account', 'dfl_plugin' ); ?></a></p>
					<p><?php printf( __( 'This opens the Dropbox authorization page in a new window.  When you have authorized the app for your account, return to this page and <a href="%s">refresh</a> it.', 'dfl_plugin' ), remove_query_arg( 'reset_dropbox' ) ); ?></p>
				<?php else : ?>
					<p><a class="button-secondary" href="<?php echo esc_attr( wp_nonce_url( add_query_arg( 'reset_dropbox', 1 ), 'reset_dropbox' ) ); ?>"><?php printf( __( 'Unlink from Dropbox account: %s', 'dfl_plugin' ), $info['email'] ); ?></a></p>
				<?php endif; ?>
			<?php else: ?>
				<p><?php _e( 'Please fill in the appliction key and and secret key fields.', 'dfl_plugin' ); ?>
			<?php endif; ?>
		</div>
		<?php
	}
	
	public function show_dfl_application_key() {
		?>
		<label for="dfl_plugin_consumer_key">
			<input type="text" id="dfl_plugin_consumer_key" name="dfl_plugin_consumer_key" value="<?php echo esc_attr( get_option( 'dfl_plugin_consumer_key' ) ); ?>" />
		</label>
		<?php
	}

	public function show_dfl_secret_key() {
		?>
		<label for="dfl_plugin_consumer_secret">
			<input type="text" id="dfl_plugin_consumer_secret" name="dfl_plugin_consumer_secret" value="<?php echo esc_attr( get_option( 'dfl_plugin_consumer_secret' ) ); ?>" />
		</label>
		<?php
	}
}

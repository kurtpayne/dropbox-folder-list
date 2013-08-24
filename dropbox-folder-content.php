<?php

/*
Plugin Name: Dropbox Folder Content
Plugin URI: http://kpayne.me/
Description: Show the contents of dropbox folder in the_content filter
Author: Kurt Payne
Version: 1.0
Author URI: http://kpayne.me/
*/

$dfc_plugin = new Dropbox_Folder_Content();

register_activation_hook( __FILE__, array( 'Dropbox_Folder_Content', 'activate' ) );
register_uninstall_hook( __FILE__, array( 'Dropbox_Folder_Content', 'uninstall' ) );

class Dropbox_Folder_Content {
	
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'settings_page' ) );
		add_filter( 'the_content', array( $this, 'show_dropbox_folder' ) );
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

		// Oauth bits
		$token = get_option( 'dfc_plugin_token_key' );
		$token_secret = get_option( 'dfc_plugin_token_secret' );
		$consumer_key = get_option( 'dfc_plugin_consumer_key' );
		$consumer_secret = get_option( 'dfc_plugin_consumer_secret' );
		$state = intval( get_option( 'dfc_plugin_oauth_state', 1 ) );

		// If we're connected, then get dropbox libs
		if ( $state >= 4 ) {
			$files = array();
			set_include_path( get_include_path() . PATH_SEPARATOR . dirname( __FILE__ ) . '/HTTP_OAuth' );
			require("Dropbox/autoload.php");
			require("HTTP_OAuth/HTTP/OAuth.php");
			$oauth = new Dropbox_OAuth_PEAR( get_option( 'dfc_plugin_consumer_key' ), get_option( 'dfc_plugin_consumer_secret' ) );	
			$dropbox = new Dropbox_API( $oauth );
			$oauth->setToken( array(
				'token'        => $token,
				'token_secret' => $token_secret
			) );
			try {
				$files = $dropbox->getMetaData( $permalink );				
			} catch ( Exception $e ) {
			}
			include( dirname( __FILE__ ) . '/template-listing.php' );
		}

		return $content;
	}

	public static function activate() {
		$opts = array(
			'dfc_plugin_consumer_key',
			'dfc_plugin_consumer_secret',
			'dfc_plugin_token_key',
			'dfc_plugin_token_secret',
			'dfc_plugin_oauth_state',
			'dfc_plugin_oauth_access_tokens',
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
				'dfc_plugin_consumer_key',
				'dfc_plugin_consumer_secret',
				'dfc_plugin_token_key',
				'dfc_plugin_token_secret',
				'dfc_plugin_oauth_state',
				'dfc_plugin_oauth_access_tokens',
			);
			foreach ( $opts as $opt ) {
				delete_option( $opt );
			}
		}
	}
	
	public function register_settings() {
		$section = 'dfc_plugin_settings_section';
		$group = 'dfc_plugin_settings_group';
		add_settings_section( $section, __( 'Settings', 'dfc_plugin' ), '__return_null', 'dfc_plugin-settings' );
		add_settings_field(
			'dfc_plugin_consumer_key',
			__( 'Dropbox application key', 'dfc_plugin' ),
			array( $this, 'show_dfc_application_key' ),
			'dfc_plugin-settings',
			$section
		);
		register_setting( $group, 'dfc_plugin_consumer_key' );
		add_settings_field(
			'dfc_plugin_consumer_secret',
			__( 'Dropbox secret key', 'dfc_plugin' ),
			array( $this, 'show_dfc_secret_key' ),
			'dfc_plugin-settings',
			$section
		);
		register_setting( $group, 'dfc_plugin_consumer_secret' );
	}

	public function settings_page() {
		add_options_page( __( 'Dropbox Folder Content', 'dfc_plugin' ), __( 'Dropbox Folder Content', 'dfc_plugin' ), 'manage_options', 'dfc_plugin-settings', array( $this, 'plugin_options' ) );
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
				'dfc_plugin_token_key',
				'dfc_plugin_token_secret',
				'dfc_plugin_oauth_access_tokens',
			);
			foreach ( $opts as $opt ) {
				delete_option( $opt );
			}
			update_option( 'dfc_plugin_oauth_state', 1 );
		}

		// Get the dropbox libraries
		set_include_path( get_include_path() . PATH_SEPARATOR . dirname( __FILE__ ) . '/HTTP_OAuth' );
		require("Dropbox/autoload.php");
		require("HTTP_OAuth/HTTP/OAuth.php");

		// Oauth bits
		$token = get_option( 'dfc_plugin_token_key' );
		$token_secret = get_option( 'dfc_plugin_token_secret' );
		$consumer_key = get_option( 'dfc_plugin_consumer_key' );
		$consumer_secret = get_option( 'dfc_plugin_consumer_secret' );
		$state = intval( get_option( 'dfc_plugin_oauth_state', 1 ) );
		if ( empty( $state ) ) {
			$state = 1;
		}

		?>
		<div class="wrap">
			<div id="icon-tools" class="icon32"><br/></div>
			<h2><?php _e( 'Dropbox Folder Content', 'dfc_plugin' ); ?></h2>

			<form id="dfc_plugin_settings_form" name="dfc_plugin_form" method="post" action="options.php">
				
				<?php settings_fields( 'dfc_plugin_settings_group' ); ?>
				<?php do_settings_sections( 'dfc_plugin-settings' ); ?>
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
				$oauth = new Dropbox_OAuth_PEAR( get_option( 'dfc_plugin_consumer_key' ), get_option( 'dfc_plugin_consumer_secret' ) );	
				$dropbox = new Dropbox_API( $oauth );
				if ( 2 === $state ) {
					$oauth->setToken( get_option( 'dfc_plugin_oauth_access_tokens' ) );
					$tokens = $oauth->getAccessToken();
					update_option( 'dfc_plugin_oauth_state', 3 );
					$oauth->setToken( $tokens );
				} elseif ( 1 === $state ) {
					$tokens = $oauth->getRequestToken();
					$url = $oauth->getAuthorizeUrl();
					update_option( 'dfc_plugin_oauth_access_tokens', $tokens );
					update_option( 'dfc_plugin_oauth_state', 2 );
					$oauth->setToken( $tokens );
				}
				$revoked = false;
				?>

				<?php // Check for non-saved connection or invalid token received from oauth ?>
				<?php
				if ( 3 === get_option( 'dfc_plugin_oauth_state' ) && !empty( $tokens ) && is_array( $tokens ) && isset( $tokens['token']) && isset( $tokens['token_secret']) ) {
					update_option( 'dfc_plugin_token_key', $tokens['token'] );
					update_option( 'dfc_plugin_token_secret', $tokens['token_secret'] );
					$token = get_option( 'dfc_plugin_token_key' );
					$token_secret = get_option( 'dfc_plugin_token_secret' );
					update_option( 'dfc_plugin_oauth_state', 4 );					
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
						update_option( 'dfc_plugin_token_key', '' );
						update_option( 'dfc_plugin_token_secret', '' );
						$revoked = true;
					}
					?>
				<?php endif; ?>

				<?php // Controls ?>
				<?php if ( empty( $info ) ) : ?>
					<p><a class="button-secondary" href="<?php echo esc_attr( esc_url( $url ) ); ?>" target="_blank"><?php _e( 'Link to Dropbox account', 'dfc_plugin' ); ?></a></p>
					<p><?php _e( 'This opens the Dropbox authorization page in a new window.  When you have authorized the app for your account, return to this page and refresh it.', 'dfc_plugin' ); ?></p>
				<?php else : ?>
					<p><a class="button-secondary" href="<?php echo esc_attr( wp_nonce_url( add_query_arg( 'reset_dropbox', 1 ), 'reset_dropbox' ) ); ?>"><?php printf( __( 'Unlink from Dropbox account: %s', 'dfc_plugin' ), $info['email'] ); ?></a></p>
				<?php endif; ?>
			<?php else: ?>
				<p><?php _e( 'Please fill in the appliction key and and secret key fields.', 'dfc_plugin' ); ?>
			<?php endif; ?>
		</div>
		<?php
	}
	
	public function show_dfc_application_key() {
		?>
		<label for="dfc_plugin_consumer_key">
			<input type="text" id="dfc_plugin_consumer_key" name="dfc_plugin_consumer_key" value="<?php echo esc_attr( get_option( 'dfc_plugin_consumer_key' ) ); ?>" />
		</label>
		<?php
	}

	public function show_dfc_secret_key() {
		?>
		<label for="dfc_plugin_consumer_secret">
			<input type="text" id="dfc_plugin_consumer_secret" name="dfc_plugin_consumer_secret" value="<?php echo esc_attr( get_option( 'dfc_plugin_consumer_secret' ) ); ?>" />
		</label>
		<?php
	}
}

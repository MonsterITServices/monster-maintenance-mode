<?php
/**
 * Plugin Name: Pro Maintenance Mode (100% Free)
 * Plugin URI:  https://github.com/MonsterITServices/WPMaintenanceMode
 * Description: Full-featured maintenance & coming soon plugin. Features 6 selectable visual templates, selectable 200 OK / 503 HTTP modes, live admin preview, countdown timer, AJAX email capture + CSV export, and role/IP/URL bypass.
 * Version:     2.0.0
 * Author:      Monster IT Services Ltd
 * License:     GPLv2 or later
 * Text Domain: pro-maintenance-free
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pro_Maintenance_Free {

	private $option_name = 'pmf_settings';
	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name =$wpdb->prefix . 'pmf_subscribers';

		// Lifecycle hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );

		// Admin hooks
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_csv_export' ) );
		add_action( 'admin_init', array( $this, 'handle_clear_subscribers' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_indicator' ), 100 );
		add_action( 'admin_head', array( $this, 'admin_bar_styles' ) );
		add_action( 'wp_head', array( $this, 'admin_bar_styles' ) );

		// Frontend & AJAX hooks
		add_action( 'template_redirect', array( $this, 'render_maintenance_page' ), 1 );
		add_action( 'wp_ajax_pmf_subscribe', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_nopriv_pmf_subscribe', array( $this, 'ajax_subscribe' ) );

		// REST API protection
		add_filter( 'rest_authentication_errors', array( $this, 'restrict_rest_api' ) );
	}

	/**
	 * Available visual templates
	 */
	public function get_templates() {
		return array(
			'glass'      => array(
				'name'        => 'Modern Glassmorphism',
				'description' => 'Frosted glass card with smooth blur effects and glowing accents.',
				'preview_bg'  => 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)',
				'accent'      => '#3b82f6',
			),
			'split'      => array(
				'name'        => 'Corporate Split-Screen',
				'description' => 'Two-column agency layout separating your message from the countdown & lead form.',
				'preview_bg'  => 'linear-gradient(90deg, #111827 50%, #1f2937 50%)',
				'accent'      => '#10b981',
			),
			'editorial'  => array(
				'name'        => 'Minimalist Editorial',
				'description' => 'Classic serif typography with clean lines, ideal for publishers and authors.',
				'preview_bg'  => '#f8fafc',
				'accent'      => '#000000',
			),
			'terminal'   => array(
				'name'        => 'Cyber Terminal',
				'description' => 'Matrix-inspired dark command prompt window with phosphor green monospace text.',
				'preview_bg'  => '#050505',
				'accent'      => '#22c55e',
			),
			'neon'       => array(
				'name'        => 'Neon Synthwave Grid',
				'description' => 'Animated 3D perspective horizon grid with cyberpunk magenta and cyan glow.',
				'preview_bg'  => 'linear-gradient(180deg, #090014 0%, #240046 100%)',
				'accent'      => '#f72585',
			),
			'brutalist'  => array(
				'name'        => 'Bold Industrial',
				'description' => 'High-contrast brutalist card with heavy black borders and hard offset shadows.',
				'preview_bg'  => '#facc15',
				'accent'      => '#000000',
			),
		);
	}

	/**
	 * Default plugin options
	 */
	public function get_defaults() {
		return array(
			'status'            => 0,
			'http_status'       => '200',
			'template'          => 'glass',
			'bypass_roles'      => array( 'administrator' ),
			'exclude_urls'      => "/wp-admin\n/wp-login.php\n/feed",
			'whitelisted_ips'   => '',
			'bypass_bots'       => 1,
			'disable_rest'      => 0,
			'page_title'        => get_bloginfo( 'name' ) . ' - Coming Soon',
			'badge_text'        => 'SCHEDULED UPDATE',
			'heading'           => 'We Are Currently Working on Something Awesome',
			'content'           => 'Our website is undergoing scheduled maintenance and upgrades. Thank you for your patience—please check back soon or subscribe below for updates!',
			'bg_color'          => '#0f172a',
			'bg_image'          => '',
			'text_color'        => '#f8fafc',
			'accent_color'      => '#3b82f6',
			'use_custom_colors' => 0,
			'custom_css'        => '',
			'countdown_enabled' => 1,
			'countdown_end'     => gmdate( 'Y-m-d\TH:i', strtotime( '+7 days' ) ),
			'auto_disable'      => 1,
			'subscribe_enabled' => 1,
			'subscribe_text'    => 'Get notified the moment we go live:',
			'social_facebook'   => '',
			'social_twitter'    => '',
			'social_instagram'  => '',
			'social_linkedin'   => '',
			'contact_email'     => get_option( 'admin_email' ),
			'retry_after'       => 3600,
		);
	}

	/**
	 * Get merged settings
	 */
	public function get_settings() {
		$saved = get_option($this->option_name, array() );
		return wp_parse_args( $saved,$this->get_defaults() );
	}

	/**
	 * Create subscribers table and initialize options on activation
	 */
	public function activate() {
		global $wpdb;
		$charset_collate =$wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table_name} ( 		id mediumint(9) NOT NULL AUTO_INCREMENT, 		email varchar(190) NOT NULL, 		ip_address varchar(100) NOT NULL, 		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, 		PRIMARY KEY  (id), 		UNIQUE KEY email (email) 	)$charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		if ( false === get_option( $this->option_name ) ) {
			update_option( $this->option_name,$this->get_defaults() );
		}
	}

	/**
	 * Enqueue WP Media Uploader and Color Picker in Admin
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( 'settings_page_pro-maintenance-free' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
	}

	/**
	 * Add Settings Page
	 */
	public function add_admin_menu() {
		add_options_page(
			'Maintenance Mode',
			'Maintenance Mode',
			'manage_options',
			'pro-maintenance-free',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Register and sanitize settings
	 */
	public function register_settings() {
		register_setting( 'pmf_settings_group', $this->option_name, array($this, 'sanitize_settings' ) );
	}

	public function sanitize_settings( $input ) {$defaults  = $this->get_defaults();$templates = array_keys( $this->get_templates() );$clean     = array();

		$clean['status']            = ! empty( $input['status'] ) ? 1 : 0;
		$clean['http_status']       = ( isset($input['http_status'] ) && '503' === $input['http_status'] ) ? '503' : '200';$clean['template']          = ( isset( $input['template'] ) && in_array($input['template'], $templates, true ) ) ?$input['template'] : 'glass';
		$clean['bypass_bots']       = ! empty( $input['bypass_bots'] ) ? 1 : 0;
		$clean['disable_rest']      = ! empty( $input['disable_rest'] ) ? 1 : 0;
		$clean['countdown_enabled'] = ! empty( $input['countdown_enabled'] ) ? 1 : 0;
		$clean['auto_disable']      = ! empty( $input['auto_disable'] ) ? 1 : 0;
		$clean['subscribe_enabled'] = ! empty( $input['subscribe_enabled'] ) ? 1 : 0;
		$clean['use_custom_colors'] = ! empty( $input['use_custom_colors'] ) ? 1 : 0;

		$clean['bypass_roles'] = array( 'administrator' );
		if ( ! empty( $input['bypass_roles'] ) && is_array($input['bypass_roles'] ) ) {
			$clean['bypass_roles'] = array_unique( array_merge( array( 'administrator' ), array_map( 'sanitize_text_field',$input['bypass_roles'] ) ) );
		}

		$clean['exclude_urls']      = isset($input['exclude_urls'] ) ? sanitize_textarea_field( $input['exclude_urls'] ) : '';$clean['whitelisted_ips']   = isset( $input['whitelisted_ips'] ) ? sanitize_textarea_field( $input['whitelisted_ips'] ) : '';
		$clean['page_title']        = isset($input['page_title'] ) ? sanitize_text_field( $input['page_title'] ) :$defaults['page_title'];
		$clean['badge_text']        = isset($input['badge_text'] ) ? sanitize_text_field( $input['badge_text'] ) :$defaults['badge_text'];
		$clean['heading']           = isset($input['heading'] ) ? sanitize_text_field( $input['heading'] ) :$defaults['heading'];
		$clean['content']           = isset($input['content'] ) ? wp_kses_post( $input['content'] ) :$defaults['content'];
		$clean['bg_color']          = isset($input['bg_color'] ) ? sanitize_hex_color( $input['bg_color'] ) :$defaults['bg_color'];
		$clean['bg_image']          = isset($input['bg_image'] ) ? esc_url_raw( $input['bg_image'] ) : '';$clean['text_color']        = isset( $input['text_color'] ) ? sanitize_hex_color( $input['text_color'] ) : $defaults['text_color'];$clean['accent_color']      = isset( $input['accent_color'] ) ? sanitize_hex_color( $input['accent_color'] ) : $defaults['accent_color'];$clean['custom_css']        = isset( $input['custom_css'] ) ? wp_strip_all_tags( $input['custom_css'] ) : '';
		$clean['countdown_end']     = isset($input['countdown_end'] ) ? sanitize_text_field( $input['countdown_end'] ) :$defaults['countdown_end'];
		$clean['subscribe_text']    = isset($input['subscribe_text'] ) ? sanitize_text_field( $input['subscribe_text'] ) :$defaults['subscribe_text'];
		$clean['social_facebook']   = isset($input['social_facebook'] ) ? esc_url_raw( $input['social_facebook'] ) : '';$clean['social_twitter']    = isset( $input['social_twitter'] ) ? esc_url_raw( $input['social_twitter'] ) : '';
		$clean['social_instagram']  = isset($input['social_instagram'] ) ? esc_url_raw( $input['social_instagram'] ) : '';$clean['social_linkedin']   = isset( $input['social_linkedin'] ) ? esc_url_raw( $input['social_linkedin'] ) : '';
		$clean['contact_email']     = isset($input['contact_email'] ) ? sanitize_email( $input['contact_email'] ) : '';$clean['retry_after']       = isset( $input['retry_after'] ) ? absint( $input['retry_after'] ) : 3600;

		return $clean;
	}

	/**
	 * Top Admin Bar status indicator
	 */
	public function admin_bar_indicator( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings =$this->get_settings();
		$active   = ! empty( $settings['status'] );

		$wp_admin_bar->add_node( array(
			'id'    => 'pmf-status',
			'title' => $active ? '<span class="pmf-dot pmf-active"></span> Maintenance: ON (' . strtoupper( esc_html( $settings['template'] ) ) . ')' : '<span class="pmf-dot pmf-inactive"></span> Maintenance: OFF',
			'href'  => admin_url( 'options-general.php?page=pro-maintenance-free' ),
			'meta'  => array(
				'class' => $active ? 'pmf-bar-on' : 'pmf-bar-off',
			),
		) );
	}

	public function admin_bar_styles() {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<style>
			#wpadminbar .pmf-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:6px; }
			#wpadminbar .pmf-active { background:#ef4444; box-shadow:0 0 6px #ef4444; }
			#wpadminbar .pmf-inactive { background:#10b981; }
			#wpadminbar .pmf-bar-on > a { background:rgba(239, 68, 68, 0.25) !important; font-weight:600 !important; }
		</style>
		<?php
	}

	/**
	 * Export Subscribers to CSV
	 */
	public function handle_csv_export() {
		if ( ! isset( $_GET['pmf_action'] ) \vert{}\vert{} 'export_csv' !==$_GET['pmf_action'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'pmf_export_csv_nonce' ) ) {
			wp_die( 'Unauthorized request.' );
		}

		global $wpdb;
		$results =$wpdb->get_results( "SELECT id, email, ip_address, created_at FROM {$this->table_name} ORDER BY created_at DESC", ARRAY_A );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=maintenance-subscribers-' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'ID', 'Email', 'IP Address', 'Date Subscribed' ) );

		if ( ! empty( $results ) ) {
			foreach ( $results as$row ) {
				fputcsv( $output,$row );
			}
		}
		fclose( $output );
		exit;
	}

	/**
	 * Delete All Subscribers
	 */
	public function handle_clear_subscribers() {
		if ( ! isset( $_GET['pmf_action'] ) \vert{}\vert{} 'clear_subscribers' !==$_GET['pmf_action'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'pmf_clear_subscribers_nonce' ) ) {
			wp_die( 'Unauthorized request.' );
		}

		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$this->table_name}" );
		wp_safe_redirect( admin_url( 'options-general.php?page=pro-maintenance-free&cleared=1' ) );
		exit;
	}

	/**
	 * Handle Frontend AJAX Email Subscription
	 */
	public function ajax_subscribe() {
		check_ajax_referer( 'pmf_subscribe_nonce', 'nonce' );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ) );
		}

		global $wpdb;
		$ip     = $this->get_client_ip();$exists = $wpdb->get_var($wpdb->prepare( "SELECT id FROM {$this->table_name} WHERE email = %s", $email ) );

		if ( $exists ) {
			wp_send_json_error( array( 'message' => 'You are already subscribed!' ) );
		}

		$inserted = $wpdb->insert($this->table_name,
			array(
				'email'      => $email,
				'ip_address' => $ip,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);

		if ( $inserted ) {
			wp_send_json_success( array( 'message' => 'Thank you! We will notify you when we launch.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Could not save your email. Please try again.' ) );
		}
	}

	/**
	 * Determine if current visitor should bypass maintenance mode
	 */
	private function should_bypass() {
		// Allow instant live preview for admins via ?pmf_preview=1
		if ( isset( $_GET['pmf_preview'] ) && current_user_can( 'manage_options' ) ) {
			return false;
		}

		$settings =$this->get_settings();

		// 1. Check if maintenance mode is disabled
		if ( empty( $settings['status'] ) ) {
			return true;
		}

		// 2. Check Auto-Disable on Countdown Expiration
		if ( ! empty( $settings['countdown_enabled'] ) && ! empty( $settings['auto_disable'] ) && ! empty( $settings['countdown_end'] ) ) {
			$end_timestamp = strtotime($settings['countdown_end'] );
			if ( $end_timestamp && time() >= $end_timestamp ) {$settings['status'] = 0;
				update_option( $this->option_name,$settings );
				return true;
			}
		}

		// 3. Always allow WP-CLI, Cron, Admin, Login, and AJAX
		if ( defined( 'WP_CLI' ) || wp_doing_cron() || is_admin() || wp_doing_ajax() ) {
			return true;
		}

		$script_name = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
		if ( in_array( $script_name, array( 'wp-login.php', 'wp-register.php' ), true ) ) {
			return true;
		}

		// 4. Check Logged-in User Roles
		if ( is_user_logged_in() ) {
			if ( current_user_can( 'manage_options' ) ) {
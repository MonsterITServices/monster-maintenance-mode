<?php
/**
 * Plugin Name: Pro Maintenance Mode (100% Free)
 * Plugin URI:  https://github.com/monsteritservice/WPMaintenanceMode
 * Description: Full-featured maintenance mode & coming soon plugin. Includes countdown timer, AJAX email subscription + CSV export, role/IP/URL bypass, bot whitelisting, custom styling, and SEO 503 headers. Zero upsells.
 * Version:     1.0.0
 * Author:      Monster IT Service
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
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_Lock_scripts' ) );
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
	 * Default plugin options
	 */
	public function get_defaults() {
		return array(
			'status'            => 0,
			'bypass_roles'      => array( 'administrator' ),
			'exclude_urls'      => "/wp-admin\n/wp-login.php\n/feed",
			'whitelisted_ips'   => '',
			'bypass_bots'       => 1,
			'disable_rest'      => 0,
			'page_title'        => get_bloginfo( 'name' ) . ' - Under Maintenance',
			'heading'           => 'We Are Currently Working on Something Awesome',
			'content'           => 'Our website is undergoing scheduled maintenance. Thank you for your patience. Please check back soon!',
			'bg_color'          => '#0f172a',
			'bg_image'          => '',
			'text_color'        => '#f8fafc',
			'accent_color'      => '#3b82f6',
			'custom_css'        => '',
			'countdown_enabled' => 1,
			'countdown_end'     => gmdate( 'Y-m-d\TH:i', strtotime( '+7 days' ) ),
			'auto_disable'      => 1,
			'subscribe_enabled' => 1,
			'subscribe_text'    => 'Notify me when it is ready:',
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
	public function admin_Lock_scripts( $hook ) {
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

	public function sanitize_settings( $input ) {$defaults = $this->get_defaults();$clean    = array();

		$clean['status']            = ! empty( $input['status'] ) ? 1 : 0;
		$clean['bypass_bots']       = ! empty( $input['bypass_bots'] ) ? 1 : 0;
		$clean['disable_rest']      = ! empty( $input['disable_rest'] ) ? 1 : 0;
		$clean['countdown_enabled'] = ! empty( $input['countdown_enabled'] ) ? 1 : 0;
		$clean['auto_disable']      = ! empty( $input['auto_disable'] ) ? 1 : 0;
		$clean['subscribe_enabled'] = ! empty( $input['subscribe_enabled'] ) ? 1 : 0;

		$clean['bypass_roles'] = array( 'administrator' );
		if ( ! empty( $input['bypass_roles'] ) && is_array($input['bypass_roles'] ) ) {
			$clean['bypass_roles'] = array_unique( array_merge( array( 'administrator' ), array_map( 'sanitize_text_field',$input['bypass_roles'] ) ) );
		}

		$clean['exclude_urls']      = isset($input['exclude_urls'] ) ? sanitize_textarea_field( $input['exclude_urls'] ) : '';$clean['whitelisted_ips']   = isset( $input['whitelisted_ips'] ) ? sanitize_textarea_field( $input['whitelisted_ips'] ) : '';
		$clean['page_title']        = isset($input['page_title'] ) ? sanitize_text_field( $input['page_title'] ) :$defaults['page_title'];
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
			'title' => $active ? '<span class="pmf-dot pmf-active"></span> Maintenance: ON' : '<span class="pmf-dot pmf-inactive"></span> Maintenance: OFF',
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
				return true;
			}
			$user  = wp_get_current_user();
			$roles = (array)$user->roles;
			if ( array_intersect( $roles, (array)$settings['bypass_roles'] ) ) {
				return true;
			}
		}

		// 5. Check Whitelisted IPs
		$client_ip =$this->get_client_ip();
		if ( ! empty( $settings['whitelisted_ips'] ) && ! empty( $client_ip ) ) {
			$ips = array_filter( array_map( 'trim', explode( "\n", $settings['whitelisted_ips'] ) ) );
			foreach ( $ips as$allowed_ip ) {
				if ( $client_ip ===$allowed_ip || ( false !== strpos( $client_ip,$allowed_ip ) ) ) {
					return true;
				}
			}
		}

		// 6. Check Excluded URLs / Slugs
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( ! empty( $settings['exclude_urls'] ) && ! empty( $request_uri ) ) {
			$urls = array_filter( array_map( 'trim', explode( "\n", $settings['exclude_urls'] ) ) );
			foreach ( $urls as$excluded_path ) {
				if ( '/' !== $excluded_path && false !== stripos( $request_uri,$excluded_path ) ) {
					return true;
				}
			}
		}

		// 7. Check Search Engine Bots
		if ( ! empty( $settings['bypass_bots'] ) ) {
			$user_agent = isset($_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';$bots       = array( 'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider', 'yandexbot', 'facebot', 'ia_archiver' );
			foreach ( $bots as$bot ) {
				if ( false !== strpos( $user_agent,$bot ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Block REST API if configured
	 */
	public function restrict_rest_api( $result ) {
		if ( ! empty( $result ) ) {
			return $result;
		}
		$settings =$this->get_settings();
		if ( ! empty( $settings['status'] ) && ! empty( $settings['disable_rest'] ) && !$this->should_bypass() ) {
			return new WP_Error(
				'rest_maintenance_mode',
				'The site is currently undergoing maintenance.',
				array( 'status' => 503 )
			);
		}
		return $result;
	}

	/**
	 * Helper: Get Visitor IP
	 */
	private function get_client_ip() {
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip_list = explode( ',', sanitize_text_field( wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			return trim( $ip_list[0] );
		}
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/**
	 * Render the Frontend Maintenance Page
	 */
	public function render_maintenance_page() {
		if ( $this->should_bypass() ) {
			return;
		}

		$settings    =$this->get_settings();
		$retry_after = max( 60, absint($settings['retry_after'] ) );

		// Send strict SEO 503 headers
		nocache_headers();
		$protocol = wp_get_server_protocol();
		header( "$protocol 503 Service Unavailable", true, 503 );
		header( "Retry-After: $retry_after" );

		$end_timestamp = ! empty( $settings['countdown_end'] ) ? strtotime( $settings['countdown_end'] ) * 1000 : 0;
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<meta name="robots" content="noindex, nofollow">
			<title><?php echo esc_html( $settings['page_title'] ); ?></title>
			<style>
				:root {
					--bg-color: <?php echo esc_attr( $settings['bg_color'] ); ?>;
					--text-color: <?php echo esc_attr( $settings['text_color'] ); ?>;
					--accent-color: <?php echo esc_attr( $settings['accent_color'] ); ?>;
				}
				* { box-sizing: border-box; margin: 0; padding: 0; }
				body {
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
					background-color: var(--bg-color);
					<?php if ( ! empty( $settings['bg_image'] ) ) : ?>
					background-image: linear-gradient(rgba(15, 23, 42, 0.72), rgba(15, 23, 42, 0.72)), url('<?php echo esc_url( $settings['bg_image'] ); ?>');
					background-size: cover;
					background-position: center;
					background-attachment: fixed;
					<?php endif; ?>
					color: var(--text-color);
					min-height: 100vh;
					display: flex;
					align-items: center;
					justify-content: center;
					padding: 24px;
					text-align: center;
					line-height: 1.6;
				}
				.pmf-card {
					max-width: 680px;
					width: 100%;
					background: rgba(255, 255, 255, 0.05);
					backdrop-filter: blur(12px);
					-webkit-backdrop-filter: blur(12px);
					border: 1px solid rgba(255, 255, 255, 0.12);
					border-radius: 20px;
					padding: 48px 36px;
					box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
				}
				h1 {
					font-size: clamp(1.8rem, 4vw, 2.75rem);
					font-weight: 800;
					margin-bottom: 16px;
					letter-spacing: -0.02em;
					line-height: 1.2;
				}
				.pmf-description {
					font-size: 1.125rem;
					opacity: 0.9;
					margin-bottom: 32px;
				}
				/* Countdown Timer */
				.pmf-countdown {
					display: grid;
					grid-template-columns: repeat(4, 1fr);
					gap: 12px;
					margin-bottom: 36px;
				}
				.pmf-time-box {
					background: rgba(0, 0, 0, 0.25);
					border: 1px solid rgba(255, 255, 255, 0.1);
					border-radius: 12px;
					padding: 16px 8px;
				}
				.pmf-time-val {
					display: block;
					font-size: clamp(1.5rem, 3.5vw, 2.25rem);
					font-weight: 700;
					color: var(--accent-color);
					line-height: 1;
					margin-bottom: 6px;
				}
				.pmf-time-label {
					font-size: 0.75rem;
					text-transform: uppercase;
					letter-spacing: 0.08em;
					opacity: 0.75;
				}
				/* Subscription Form */
				.pmf-subscribe-wrap {
					margin-bottom: 32px;
				}
				.pmf-subscribe-title {
					font-size: 0.95rem;
					margin-bottom: 12px;
					font-weight: 500;
				}
				.pmf-form {
					display: flex;
					gap: 10px;
					max-width: 460px;
					margin: 0 auto;
				}
				.pmf-input {
					flex: 1;
					padding: 12px 16px;
					border-radius: 10px;
					border: 1px solid rgba(255, 255, 255, 0.2);
					background: rgba(0, 0, 0, 0.3);
					color: var(--text-color);
					font-size: 1rem;
					outline: none;
				}
				.pmf-input:focus {
					border-color: var(--accent-color);
				}
				.pmf-btn {
					padding: 12px 24px;
					border-radius: 10px;
					border: none;
					background: var(--accent-color);
					color: #ffffff;
					font-weight: 600;
					font-size: 1rem;
					cursor: pointer;
					transition: opacity 0.2s ease, transform 0.1s ease;
				}
				.pmf-btn:hover { opacity: 0.92; }
				.pmf-btn:active { transform: scale(0.98); }
				.pmf-msg {
					margin-top: 10px;
					font-size: 0.9rem;
					min-height: 1.4em;
				}
				/* Social & Footer Links */
				.pmf-socials {
					display: flex;
					justify-content: center;
					gap: 16px;
					flex-wrap: wrap;
					margin-top: 16px;
				}
				.pmf-socials a {
					color: var(--text-color);
					opacity: 0.75;
					text-decoration: none;
					font-size: 0.9rem;
					padding: 6px 12px;
					border-radius: 8px;
					background: rgba(255, 255, 255, 0.06);
					transition: all 0.2s ease;
				}
				.pmf-socials a:hover {
					opacity: 1;
					background: var(--accent-color);
					color: #fff;
				}
				@media (max-width: 520px) {
					.pmf-card { padding: 32px 20px; }
					.pmf-form { flex-direction: column; }
					.pmf-countdown { gap: 8px; }
				}
				<?php echo $settings['custom_css']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</style>
		</head>
		<body>
			<main class="pmf-card">
				<h1><?php echo esc_html( $settings['heading'] ); ?></h1>
				<div class="pmf-description">
					<?php echo wp_kses
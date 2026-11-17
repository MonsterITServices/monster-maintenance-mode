<?php
/**
 * Plugin Name: Monster Maintenance Mode
 * Plugin URI:  https://github.com/MonsterITServices/Monster-Maintenance-Mode
 * Description: Full-featured maintenance & coming soon plugin. Features an interactive Admin Dashboard External IP Manager, Direct File Reading controls, 6 selectable visual templates, 200 OK / 503 HTTP modes, live admin preview, countdown timer, AJAX email capture + CSV export, and role/URL bypass.
 * Version:     2.2.0
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
		$this->table_name = $wpdb->prefix . 'pmf_subscribers';

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
			'status'                  => 0,
			'http_status'             => '200',
			'template'                => 'glass',
			'bypass_roles'            => array( 'administrator' ),
			'exclude_urls'            => "/wp-admin\n/wp-login.php\n/wp-content/uploads/\n/feed",
			'whitelisted_ips'         => '',
			'allow_file_reading'      => 1,
			'allowed_file_extensions' => 'pdf, zip, rar, 7z, txt, csv, json, xml, doc, docx, xls, xlsx, jpg, jpeg, png, gif, webp, svg, ico, mp3, mp4, woff, woff2, ttf, css, js',
			'allow_wp_attachments'    => 1,
			'bypass_bots'             => 1,
			'disable_rest'            => 0,
			'page_title'              => get_bloginfo( 'name' ) . ' - Coming Soon',
			'badge_text'              => 'SCHEDULED UPDATE',
			'heading'                 => 'We Are Currently Working on Something Awesome',
			'content'                 => 'Our website is undergoing scheduled maintenance and upgrades. Thank you for your patience—please check back soon or subscribe below for updates!',
			'bg_color'                => '#0f172a',
			'bg_image'                => '',
			'text_color'              => '#f8fafc',
			'accent_color'            => '#3b82f6',
			'use_custom_colors'       => 0,
			'custom_css'              => '',
			'countdown_enabled'       => 1,
			'countdown_end'           => gmdate( 'Y-m-d\TH:i', strtotime( '+7 days' ) ),
			'auto_disable'            => 1,
			'subscribe_enabled'       => 1,
			'subscribe_text'          => 'Get notified the moment we go live:',
			'social_facebook'         => '',
			'social_twitter'          => '',
			'social_instagram'        => '',
			'social_linkedin'         => '',
			'contact_email'           => get_option( 'admin_email' ),
			'retry_after'             => 3600,
		);
	}

	/**
	 * Get merged settings
	 */
	public function get_settings() {
		$saved = get_option( $this->option_name, array() );
		return wp_parse_args( $saved, $this->get_defaults() );
	}

	/**
	 * Create subscribers table and initialize options on activation
	 */
	public function activate() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table_name} (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL,
			ip_address varchar(100) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		if ( false === get_option( $this->option_name ) ) {
			update_option( $this->option_name, $this->get_defaults() );
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
		register_setting( 'pmf_settings_group', $this->option_name, array( $this, 'sanitize_settings' ) );
	}

	public function sanitize_settings( $input ) {
		$defaults  = $this->get_defaults();
		$templates = array_keys( $this->get_templates() );
		$clean     = array();

		$clean['status']               = ! empty( $input['status'] ) ? 1 : 0;
		$clean['http_status']          = ( isset( $input['http_status'] ) && '503' === $input['http_status'] ) ? '503' : '200';
		$clean['template']             = ( isset( $input['template'] ) && in_array( $input['template'], $templates, true ) ) ? $input['template'] : 'glass';
		$clean['allow_file_reading']   = ! empty( $input['allow_file_reading'] ) ? 1 : 0;
		$clean['allow_wp_attachments'] = ! empty( $input['allow_wp_attachments'] ) ? 1 : 0;
		$clean['bypass_bots']          = ! empty( $input['bypass_bots'] ) ? 1 : 0;
		$clean['disable_rest']         = ! empty( $input['disable_rest'] ) ? 1 : 0;
		$clean['countdown_enabled']    = ! empty( $input['countdown_enabled'] ) ? 1 : 0;
		$clean['auto_disable']         = ! empty( $input['auto_disable'] ) ? 1 : 0;
		$clean['subscribe_enabled']    = ! empty( $input['subscribe_enabled'] ) ? 1 : 0;
		$clean['use_custom_colors']    = ! empty( $input['use_custom_colors'] ) ? 1 : 0;

		$clean['bypass_roles'] = array( 'administrator' );
		if ( ! empty( $input['bypass_roles'] ) && is_array( $input['bypass_roles'] ) ) {
			$clean['bypass_roles'] = array_unique( array_merge( array( 'administrator' ), array_map( 'sanitize_text_field', $input['bypass_roles'] ) ) );
		}

		$clean['exclude_urls']            = isset( $input['exclude_urls'] ) ? sanitize_textarea_field( $input['exclude_urls'] ) : '';
		$clean['whitelisted_ips']         = isset( $input['whitelisted_ips'] ) ? sanitize_textarea_field( $input['whitelisted_ips'] ) : '';
		$clean['allowed_file_extensions'] = isset( $input['allowed_file_extensions'] ) ? sanitize_text_field( $input['allowed_file_extensions'] ) : $defaults['allowed_file_extensions'];
		$clean['page_title']              = isset( $input['page_title'] ) ? sanitize_text_field( $input['page_title'] ) : $defaults['page_title'];
		$clean['badge_text']              = isset( $input['badge_text'] ) ? sanitize_text_field( $input['badge_text'] ) : $defaults['badge_text'];
		$clean['heading']                 = isset( $input['heading'] ) ? sanitize_text_field( $input['heading'] ) : $defaults['heading'];
		$clean['content']                 = isset( $input['content'] ) ? wp_kses_post( $input['content'] ) : $defaults['content'];
		$clean['bg_color']                = isset( $input['bg_color'] ) ? sanitize_hex_color( $input['bg_color'] ) : $defaults['bg_color'];
		$clean['bg_image']                = isset( $input['bg_image'] ) ? esc_url_raw( $input['bg_image'] ) : '';
		$clean['text_color']              = isset( $input['text_color'] ) ? sanitize_hex_color( $input['text_color'] ) : $defaults['text_color'];
		$clean['accent_color']            = isset( $input['accent_color'] ) ? sanitize_hex_color( $input['accent_color'] ) : $defaults['accent_color'];
		$clean['custom_css']              = isset( $input['custom_css'] ) ? wp_strip_all_tags( $input['custom_css'] ) : '';
		$clean['countdown_end']           = isset( $input['countdown_end'] ) ? sanitize_text_field( $input['countdown_end'] ) : $defaults['countdown_end'];
		$clean['subscribe_text']          = isset( $input['subscribe_text'] ) ? sanitize_text_field( $input['subscribe_text'] ) : $defaults['subscribe_text'];
		$clean['social_facebook']         = isset( $input['social_facebook'] ) ? esc_url_raw( $input['social_facebook'] ) : '';
		$clean['social_twitter']          = isset( $input['social_twitter'] ) ? esc_url_raw( $input['social_twitter'] ) : '';
		$clean['social_instagram']        = isset( $input['social_instagram'] ) ? esc_url_raw( $input['social_instagram'] ) : '';
		$clean['social_linkedin']         = isset( $input['social_linkedin'] ) ? esc_url_raw( $input['social_linkedin'] ) : '';
		$clean['contact_email']           = isset( $input['contact_email'] ) ? sanitize_email( $input['contact_email'] ) : '';
		$clean['retry_after']             = isset( $input['retry_after'] ) ? absint( $input['retry_after'] ) : 3600;

		return $clean;
	}

	/**
	 * Top Admin Bar status indicator
	 */
	public function admin_bar_indicator( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = $this->get_settings();
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
		if ( ! isset( $_GET['pmf_action'] ) || 'export_csv' !== $_GET['pmf_action'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'pmf_export_csv_nonce' ) ) {
			wp_die( 'Unauthorized request.' );
		}

		global $wpdb;
		$results = $wpdb->get_results( "SELECT id, email, ip_address, created_at FROM {$this->table_name} ORDER BY created_at DESC", ARRAY_A );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=maintenance-subscribers-' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'ID', 'Email', 'IP Address', 'Date Subscribed' ) );

		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				fputcsv( $output, $row );
			}
		}
		fclose( $output );
		exit;
	}

	/**
	 * Delete All Subscribers
	 */
	public function handle_clear_subscribers() {
		if ( ! isset( $_GET['pmf_action'] ) || 'clear_subscribers' !== $_GET['pmf_action'] ) {
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
		$ip     = $this->get_client_ip();
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table_name} WHERE email = %s", $email ) );

		if ( $exists ) {
			wp_send_json_error( array( 'message' => 'You are already subscribed!' ) );
		}

		$inserted = $wpdb->insert(
			$this->table_name,
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
	 * Check if Visitor IP matches the Admin Dashboard Whitelist (Exact IP, Wildcard, or CIDR)
	 */
	private function is_ip_whitelisted( $client_ip, $whitelist_raw ) {
		if ( empty( $client_ip ) || empty( $whitelist_raw ) ) {
			return false;
		}

		$lines = preg_split( '/[\r\n]+/', $whitelist_raw );
		foreach ( $lines as $line ) {
			// Strip optional label/comment after #
			$rule = trim( preg_replace( '/#.*$/', '', $line ) );
			if ( empty( $rule ) ) {
				continue;
			}

			// 1. Exact IP match
			if ( strcasecmp( $client_ip, $rule ) === 0 ) {
				return true;
			}

			// 2. Wildcard match (e.g. 192.168.1.*)
			if ( false !== strpos( $rule, '*' ) ) {
				$regex = '/^' . str_replace( '\*', '[0-9a-fA-F:.]+', preg_quote( $rule, '/' ) ) . '$/i';
				if ( preg_match( $regex, $client_ip ) ) {
					return true;
				}
			}

			// 3. CIDR Subnet match (e.g. 203.0.113.0/24)
			if ( false !== strpos( $rule, '/' ) ) {
				list( $subnet, $bits ) = explode( '/', $rule, 2 );
				$subnet     = trim( $subnet );
				$bits       = (int) trim( $bits );
				$ip_bin     = @inet_pton( $client_ip );
				$subnet_bin = @inet_pton( $subnet );

				if ( false !== $ip_bin && false !== $subnet_bin && strlen( $ip_bin ) === strlen( $subnet_bin ) ) {
					$max_bits = strlen( $ip_bin ) * 8;
					if ( $bits >= 0 && $bits <= $max_bits ) {
						$byte_len = (int) floor( $bits / 8 );
						$rem_bits = $bits % 8;
						$mask     = str_repeat( "\xff", $byte_len );
						if ( $rem_bits > 0 ) {
							$mask .= chr( ( 0xff << ( 8 - $rem_bits ) ) & 0xff );
						}
						$mask = str_pad( $mask, strlen( $ip_bin ), "\x00" );
						if ( ( $ip_bin & $mask ) === ( $subnet_bin & $mask ) ) {
							return true;
						}
					}
				}
			}
		}

		return false;
	}

	/**
	 * Check if Request URI points to an allowed readable file extension or attachment
	 */
	private function is_allowed_file_request( $request_uri, $settings ) {
		if ( empty( $settings['allow_file_reading'] ) ) {
			return false;
		}

		if ( ! empty( $settings['allow_wp_attachments'] ) && function_exists( 'is_attachment' ) && is_attachment() ) {
			return true;
		}

		if ( empty( $request_uri ) || empty( $settings['allowed_file_extensions'] ) ) {
			return false;
		}

		$parsed_path = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( empty( $parsed_path ) ) {
			return false;
		}

		$ext = strtolower( pathinfo( $parsed_path, PATHINFO_EXTENSION ) );
		if ( empty( $ext ) ) {
			return false;
		}

		$allowed_exts = array_filter( array_map( function( $item ) {
			return strtolower( ltrim( trim( $item ), '.' ) );
		}, explode( ',', $settings['allowed_file_extensions'] ) ) );

		return in_array( $ext, $allowed_exts, true );
	}

	/**
	 * Determine if current visitor or file request should bypass maintenance mode
	 */
	private function should_bypass() {
		// Allow instant live preview for admins via ?pmf_preview=1
		if ( isset( $_GET['pmf_preview'] ) && current_user_can( 'manage_options' ) ) {
			return false;
		}

		$settings = $this->get_settings();

		// 1. Check if maintenance mode is disabled
		if ( empty( $settings['status'] ) ) {
			return true;
		}

		// 2. Check Auto-Disable on Countdown Expiration
		if ( ! empty( $settings['countdown_enabled'] ) && ! empty( $settings['auto_disable'] ) && ! empty( $settings['countdown_end'] ) ) {
			$end_timestamp = strtotime( $settings['countdown_end'] );
			if ( $end_timestamp && time() >= $end_timestamp ) {
				$settings['status'] = 0;
				update_option( $this->option_name, $settings );
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
			$roles = (array) $user->roles;
			if ( array_intersect( $roles, (array) $settings['bypass_roles'] ) ) {
				return true;
			}
		}

		// 5. Check External IP Addresses configured in Admin Dashboard
		$client_ip = $this->get_client_ip();
		if ( $this->is_ip_whitelisted( $client_ip, $settings['whitelisted_ips'] ) ) {
			return true;
		}

		// 6. Check Direct File Reading & Allowed File Extensions configured in Admin Dashboard
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( $this->is_allowed_file_request( $request_uri, $settings ) ) {
			return true;
		}

		// 7. Check Excluded URLs / Slugs / File Directories configured in Admin Dashboard
		if ( ! empty( $settings['exclude_urls'] ) && ! empty( $request_uri ) ) {
			$urls = array_filter( array_map( 'trim', explode( "\n", $settings['exclude_urls'] ) ) );
			foreach ( $urls as $excluded_path ) {
				if ( '/' !== $excluded_path && false !== stripos( $request_uri, $excluded_path ) ) {
					return true;
				}
			}
		}

		// 8. Check Search Engine Bots
		if ( ! empty( $settings['bypass_bots'] ) ) {
			$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
			$bots       = array( 'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider', 'yandexbot', 'facebot', 'ia_archiver' );
			foreach ( $bots as $bot ) {
				if ( false !== strpos( $user_agent, $bot ) ) {
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
		$settings = $this->get_settings();
		if ( ! empty( $settings['status'] ) && ! empty( $settings['disable_rest'] ) && ! $this->should_bypass() ) {
			$status_code = ( '503' === $settings['http_status'] ) ? 503 : 200;
			return new WP_Error(
				'rest_maintenance_mode',
				'The site is currently undergoing maintenance.',
				array( 'status' => $status_code )
			);
		}
		return $result;
	}

	/**
	 * Helper: Get Visitor IP (Supports Cloudflare, Reverse Proxy, and Direct Remote Addr)
	 */
	private function get_client_ip() {
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			return trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) );
		}
		if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			return trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) ) );
		}
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip_list = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			return trim( $ip_list[0] );
		}
		return isset( $_SERVER['REMOTE_ADDR'] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) : '';
	}

	/**
	 * Render Reusable Countdown Block
	 */
	private function render_countdown_html( $settings, $end_timestamp ) {
		if ( empty( $settings['countdown_enabled'] ) || $end_timestamp <= 0 ) {
			return;
		}
		?>
		<div class="pmf-countdown" id="pmf-countdown" data-end="<?php echo esc_attr( $end_timestamp ); ?>">
			<div class="pmf-time-box">
				<span class="pmf-time-val" id="pmf-days">00</span>
				<span class="pmf-time-label">Days</span>
			</div>
			<div class="pmf-time-box">
				<span class="pmf-time-val" id="pmf-hours">00</span>
				<span class="pmf-time-label">Hours</span>
			</div>
			<div class="pmf-time-box">
				<span class="pmf-time-val" id="pmf-mins">00</span>
				<span class="pmf-time-label">Minutes</span>
			</div>
			<div class="pmf-time-box">
				<span class="pmf-time-val" id="pmf-secs">00</span>
				<span class="pmf-time-label">Seconds</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Reusable Subscription Block
	 */
	private function render_subscribe_html( $settings ) {
		if ( empty( $settings['subscribe_enabled'] ) ) {
			return;
		}
		?>
		<div class="pmf-subscribe-wrap">
			<?php if ( ! empty( $settings['subscribe_text'] ) ) : ?>
				<p class="pmf-subscribe-title"><?php echo esc_html( $settings['subscribe_text'] ); ?></p>
			<?php endif; ?>
			<form class="pmf-form" id="pmf-subscribe-form">
				<input type="email" class="pmf-input" id="pmf-email" placeholder="Enter your email address..." required>
				<button type="submit" class="pmf-btn" id="pmf-submit">Notify Me</button>
			</form>
			<div class="pmf-msg" id="pmf-response"></div>
		</div>
		<?php
	}

	/**
	 * Render Reusable Social Links Block
	 */
	private function render_socials_html( $settings ) {
		$has_socials = ! empty( $settings['social_facebook'] ) || ! empty( $settings['social_twitter'] ) || ! empty( $settings['social_instagram'] ) || ! empty( $settings['social_linkedin'] ) || ! empty( $settings['contact_email'] );
		if ( ! $has_socials ) {
			return;
		}
		?>
		<div class="pmf-socials">
			<?php if ( ! empty( $settings['social_facebook'] ) ) : ?>
				<a href="<?php echo esc_url( $settings['social_facebook'] ); ?>" target="_blank" rel="noopener">Facebook</a>
			<?php endif; ?>
			<?php if ( ! empty( $settings['social_twitter'] ) ) : ?>
				<a href="<?php echo esc_url( $settings['social_twitter'] ); ?>" target="_blank" rel="noopener">X / Twitter</a>
			<?php endif; ?>
			<?php if ( ! empty( $settings['social_instagram'] ) ) : ?>
				<a href="<?php echo esc_url( $settings['social_instagram'] ); ?>" target="_blank" rel="noopener">Instagram</a>
			<?php endif; ?>
			<?php if ( ! empty( $settings['social_linkedin'] ) ) : ?>
				<a href="<?php echo esc_url( $settings['social_linkedin'] ); ?>" target="_blank" rel="noopener">LinkedIn</a>
			<?php endif; ?>
			<?php if ( ! empty( $settings['contact_email'] ) ) : ?>
				<a href="mailto:<?php echo esc_attr( $settings['contact_email'] ); ?>">Contact Us</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the Frontend Maintenance Page with Selected Template
	 */
	public function render_maintenance_page() {
		if ( $this->should_bypass() ) {
			return;
		}

		$settings = $this->get_settings();

		// Allow ?pmf_template=slug override when admin is previewing
		$template = $settings['template'];
		if ( isset( $_GET['pmf_preview'], $_GET['pmf_template'] ) && current_user_can( 'manage_options' ) ) {
			$preview_tpl = sanitize_text_field( wp_unslash( $_GET['pmf_template'] ) );
			if ( array_key_exists( $preview_tpl, $this->get_templates() ) ) {
				$template = $preview_tpl;
			}
		}

		// Send HTTP Status Header (200 OK Coming Soon vs 503 Maintenance)
		nocache_headers();
		$protocol = wp_get_server_protocol();
		if ( '503' === $settings['http_status'] && ! isset( $_GET['pmf_preview'] ) ) {
			$retry_after = max( 60, absint( $settings['retry_after'] ) );
			header( "$protocol 503 Service Unavailable", true, 503 );
			header( "Retry-After: $retry_after" );
		} else {
			status_header( 200 );
		}

		// Determine template palette or user custom overrides
		$palettes = array(
			'glass'     => array( 'bg' => '#0f172a', 'text' => '#f8fafc', 'accent' => '#3b82f6' ),
			'split'     => array( 'bg' => '#0b0f19', 'text' => '#f9fafb', 'accent' => '#10b981' ),
			'editorial' => array( 'bg' => '#f8fafc', 'text' => '#09090b', 'accent' => '#000000' ),
			'terminal'  => array( 'bg' => '#050505', 'text' => '#22c55e', 'accent' => '#22c55e' ),
			'neon'      => array( 'bg' => '#090014', 'text' => '#ffffff', 'accent' => '#f72585' ),
			'brutalist' => array( 'bg' => '#facc15', 'text' => '#000000', 'accent' => '#000000' ),
		);

		$active_palette = isset( $palettes[ $template ] ) ? $palettes[ $template ] : $palettes['glass'];
		$bg_color       = ! empty( $settings['use_custom_colors'] ) ? $settings['bg_color'] : $active_palette['bg'];
		$text_color     = ! empty( $settings['use_custom_colors'] ) ? $settings['text_color'] : $active_palette['text'];
		$accent_color   = ! empty( $settings['use_custom_colors'] ) ? $settings['accent_color'] : $active_palette['accent'];

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
					--bg-color: <?php echo esc_attr( $bg_color ); ?>;
					--text-color: <?php echo esc_attr( $text_color ); ?>;
					--accent-color: <?php echo esc_attr( $accent_color ); ?>;
				}
				* { box-sizing: border-box; margin: 0; padding: 0; }
				body {
					background-color: var(--bg-color);
					color: var(--text-color);
					min-height: 100vh;
					display: flex;
					align-items: center;
					justify-content: center;
					padding: 24px;
					line-height: 1.6;
					position: relative;
					overflow-x: hidden;
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
					<?php if ( ! empty( $settings['bg_image'] ) ) : ?>
					background-image: linear-gradient(rgba(10, 15, 30, 0.75), rgba(10, 15, 30, 0.75)), url('<?php echo esc_url( $settings['bg_image'] ); ?>');
					background-size: cover;
					background-position: center;
					background-attachment: fixed;
					<?php endif; ?>
				}

				.pmf-badge { display: inline-block; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; padding: 6px 14px; border-radius: 999px; margin-bottom: 20px; }
				h1 { font-size: clamp(2rem, 4.5vw, 3rem); font-weight: 800; margin-bottom: 18px; line-height: 1.15; }
				.pmf-description { font-size: 1.125rem; opacity: 0.9; margin-bottom: 32px; }
				.pmf-description p { margin-bottom: 12px; }
				.pmf-description p:last-child { margin-bottom: 0; }
				.pmf-countdown { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 34px; }
				.pmf-time-box { padding: 18px 10px; text-align: center; }
				.pmf-time-val { display: block; font-size: clamp(1.6rem, 3.5vw, 2.4rem); font-weight: 800; line-height: 1; margin-bottom: 6px; }
				.pmf-time-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.75; }
				.pmf-subscribe-wrap { margin-bottom: 28px; }
				.pmf-subscribe-title { font-size: 0.95rem; margin-bottom: 12px; font-weight: 600; }
				.pmf-form { display: flex; gap: 10px; width: 100%; }
				.pmf-input { flex: 1; padding: 13px 16px; font-size: 1rem; outline: none; }
				.pmf-btn { padding: 13px 26px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.2s ease; }
				.pmf-msg { margin-top: 10px; font-size: 0.9rem; min-height: 1.4em; }
				.pmf-socials { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 16px; }
				.pmf-socials a { color: var(--text-color); text-decoration: none; font-size: 0.88rem; padding: 7px 14px; transition: all 0.2s ease; }

				/* TEMPLATE 1: MODERN GLASSMORPHISM */
				body.tpl-glass {
					<?php if ( empty( $settings['bg_image'] ) && empty( $settings['use_custom_colors'] ) ) : ?>
					background: radial-gradient(circle at top left, #1e3a8a, #0f172a 55%, #020617);
					<?php endif; ?>
					text-align: center;
				}
				.tpl-glass .pmf-container { max-width: 680px; width: 100%; background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.14); border-radius: 24px; padding: 52px 40px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
				.tpl-glass .pmf-badge { background: rgba(59, 130, 246, 0.2); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.4); }
				.tpl-glass .pmf-time-box { background: rgba(0, 0, 0, 0.28); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 14px; }
				.tpl-glass .pmf-time-val { color: var(--accent-color); }
				.tpl-glass .pmf-form { max-width: 480px; margin: 0 auto; }
				.tpl-glass .pmf-input { border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.2); background: rgba(0, 0, 0, 0.35); color: var(--text-color); }
				.tpl-glass .pmf-btn { border-radius: 12px; border: none; background: var(--accent-color); color: #fff; }
				.tpl-glass .pmf-socials { justify-content: center; }
				.tpl-glass .pmf-socials a { border-radius: 8px; background: rgba(255, 255, 255, 0.07); }
				.tpl-glass .pmf-socials a:hover { background: var(--accent-color); color: #fff; }

				/* TEMPLATE 2: CORPORATE SPLIT-SCREEN */
				.tpl-split .pmf-container { max-width: 1120px; width: 100%; display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 48px; align-items: center; background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 24px; padding: 56px; box-shadow: 0 25px 60px rgba(0, 0, 0, 0.45); }
				.tpl-split .pmf-badge { background: rgba(16, 185, 129, 0.15); color: var(--accent-color); border: 1px solid var(--accent-color); }
				.tpl-split .pmf-right-panel { background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 18px; padding: 36px 30px; }
				.tpl-split .pmf-time-box { background: rgba(0, 0, 0, 0.35); border-radius: 10px; border-bottom: 3px solid var(--accent-color); }
				.tpl-split .pmf-time-val { color: var(--accent-color); }
				.tpl-split .pmf-form { flex-direction: column; }
				.tpl-split .pmf-input { border-radius: 10px; border: 1px solid rgba(255, 255, 255, 0.18); background: rgba(0, 0, 0, 0.4); color: var(--text-color); }
				.tpl-split .pmf-btn { border-radius: 10px; border: none; background: var(--accent-color); color: #052e16; }
				.tpl-split .pmf-socials a { border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 6px; }
				.tpl-split .pmf-socials a:hover { border-color: var(--accent-color); color: var(--accent-color); }

				/* TEMPLATE 3: MINIMALIST EDITORIAL (SERIF) */
				body.tpl-editorial { font-family: "Times New Roman", Times, Georgia, serif; text-align: center; }
				.tpl-editorial .pmf-container { max-width: 660px; width: 100%; background: #ffffff; color: #000000; border: 2px solid #000000; padding: 56px 44px; box-shadow: 0 12px 30px rgba(0, 0, 0, 0.06); }
				.tpl-editorial .pmf-badge { border-radius: 0; border: 1px solid #000000; background: #000000; color: #ffffff; font-family: -apple-system, sans-serif; font-size: 0.7rem; }
				.tpl-editorial h1 { font-weight: 700; letter-spacing: -0.01em; border-bottom: 1px solid #000000; padding-bottom: 20px; margin-bottom: 24px; }
				.tpl-editorial .pmf-description { font-size: 1.15rem; color: #18181b; }
				.tpl-editorial .pmf-time-box { border: 1px solid #000000; background: #fafafa; }
				.tpl-editorial .pmf-time-val { color: #000000; font-weight: 700; }
				.tpl-editorial .pmf-time-label { font-family: -apple-system, sans-serif; color: #3f3f46; }
				.tpl-editorial .pmf-form { max-width: 460px; margin: 0 auto; }
				.tpl-editorial .pmf-input { border: 1px solid #000000; background: #ffffff; color: #000000; font-family: inherit; }
				.tpl-editorial .pmf-btn { border: 1px solid #000000; background: #000000; color: #ffffff; font-family: -apple-system, sans-serif; text-transform: uppercase; letter-spacing: 0.08em; font-size: 0.85rem; }
				.tpl-editorial .pmf-btn:hover { background: #ffffff; color: #000000; }
				.tpl-editorial .pmf-socials { justify-content: center; border-top: 1px solid #e4e4e7; padding-top: 20px; }
				.tpl-editorial .pmf-socials a { color: #000000; text-decoration: underline; font-style: italic; }

				/* TEMPLATE 4: CYBER TERMINAL (MATRIX) */
				body.tpl-terminal { font-family: "Courier New", Courier, Consolas, monospace; }
				.tpl-terminal .pmf-container { max-width: 740px; width: 100%; background: rgba(8, 12, 8, 0.94); border: 1px solid var(--accent-color); border-radius: 8px; box-shadow: 0 0 35px rgba(34, 197, 94, 0.2); overflow: hidden; }
				.tpl-terminal .pmf-term-bar { background: #111a11; border-bottom: 1px solid var(--accent-color); padding: 10px 16px; display: flex; align-items: center; gap: 8px; }
				.tpl-terminal .pmf-term-dot { width: 11px; height: 11px; border-radius: 50%; display: inline-block; }
				.tpl-terminal .pmf-term-title { margin-left: 10px; font-size: 0.8rem; opacity: 0.8; }
				.tpl-terminal .pmf-term-body { padding: 36px 32px; }
				.tpl-terminal .pmf-badge { border: 1px dashed var(--accent-color); border-radius: 0; color: var(--accent-color); }
				.tpl-terminal h1::before { content: "> "; opacity: 0.7; }
				.tpl-terminal .pmf-time-box { border: 1px solid var(--accent-color); background: rgba(34, 197, 94, 0.05); }
				.tpl-terminal .pmf-time-val { color: var(--accent-color); text-shadow: 0 0 10px rgba(34, 197, 94, 0.6); }
				.tpl-terminal .pmf-input { background: #000000; border: 1px solid var(--accent-color); color: var(--accent-color); font-family: inherit; }
				.tpl-terminal .pmf-btn { background: var(--accent-color); color: #000000; border: 1px solid var(--accent-color); font-family: inherit; text-transform: uppercase; }
				.tpl-terminal .pmf-socials a { border: 1px solid rgba(34, 197, 94, 0.4); color: var(--accent-color); }
				.tpl-terminal .pmf-socials a:hover { background: var(--accent-color); color: #000000; }

				/* TEMPLATE 5: NEON SYNTHWAVE GRID */
				body.tpl-neon {
					text-align: center;
					<?php if ( empty( $settings['bg_image'] ) && empty( $settings['use_custom_colors'] ) ) : ?>
					background: radial-gradient(circle at center, #240046 0%, #090014 80%);
					<?php endif; ?>
				}
				.tpl-neon .pmf-cyber-grid { position: fixed; bottom: 0; left: -50%; width: 200%; height: 45vh; background-image: linear-gradient(to right, rgba(247, 37, 133, 0.35) 1px, transparent 1px), linear-gradient(to bottom, rgba(76, 201, 240, 0.35) 1px, transparent 1px); background-size: 40px 40px; transform: perspective(300px) rotateX(65deg); z-index: 0; pointer-events: none; }
				.tpl-neon .pmf-container { position: relative; z-index: 2; max-width: 700px; width: 100%; background: rgba(15, 3, 38, 0.85); border: 2px solid var(--accent-color); box-shadow: 0 0 30px rgba(247, 37, 133, 0.4), inset 0 0 20px rgba(76, 201, 240, 0.2); border-radius: 16px; padding: 48px 36px; }
				.tpl-neon .pmf-badge { background: transparent; border: 1px solid #4cc9f0; color: #4cc9f0; box-shadow: 0 0 12px rgba(76, 201, 240, 0.4); }
				.tpl-neon h1 { text-shadow: 0 0 18px var(--accent-color); }
				.tpl-neon .pmf-time-box { background: rgba(0, 0, 0, 0.55); border: 1px solid #4cc9f0; border-radius: 10px; box-shadow: 0 0 15px rgba(76, 201, 240, 0.25); }
				.tpl-neon .pmf-time-val { color: #4cc9f0; text-shadow: 0 0 10px #4cc9f0; }
				.tpl-neon .pmf-form { max-width: 480px; margin: 0 auto; }
				.tpl-neon .pmf-input { background: rgba(0, 0, 0, 0.6); border: 1px solid var(--accent-color); color: #ffffff; border-radius: 8px; }
				.tpl-neon .pmf-btn { background: var(--accent-color); color: #ffffff; border: none; border-radius: 8px; box-shadow: 0 0 18px var(--accent-color); text-transform: uppercase; }
				.tpl-neon .pmf-socials { justify-content: center; }
				.tpl-neon .pmf-socials a { border: 1px solid rgba(255, 255, 255, 0.25); border-radius: 6px; }
				.tpl-neon .pmf-socials a:hover { border-color: #4cc9f0; box-shadow: 0 0 12px #4cc9f0; }

				/* TEMPLATE 6: BOLD INDUSTRIAL BRUTALIST */
				.tpl-brutalist .pmf-container { max-width: 680px; width: 100%; background: #ffffff; color: #000000; border: 4px solid #000000; box-shadow: 12px 12px 0px #000000; padding: 48px 40px; text-align: left; }
				.tpl-brutalist .pmf-badge { background: #000000; color: #facc15; border-radius: 0; font-weight: 900; }
				.tpl-brutalist h1 { text-transform: uppercase; font-weight: 900; letter-spacing: -0.03em; }
				.tpl-brutalist .pmf-time-box { background: var(--bg-color); border: 3px solid #000000; box-shadow: 5px 5px 0px #000000; }
				.tpl-brutalist .pmf-time-val { color: #000000; font-weight: 900; }
				.tpl-brutalist .pmf-time-label { color: #000000; font-weight: 700; opacity: 1; }
				.tpl-brutalist .pmf-input { border: 3px solid #000000; background: #ffffff; color: #000000; font-weight: 600; }
				.tpl-brutalist .pmf-btn { background: #000000; color: #ffffff; border: 3px solid #000000; text-transform: uppercase; font-weight: 900; box-shadow: 5px 5px 0px #000000; }
				.tpl-brutalist .pmf-btn:hover { transform: translate(-2px, -2px); box-shadow: 7px 7px 0px #000000; }
				.tpl-brutalist .pmf-socials a { background: #ffffff; color: #000000; border: 2px solid #000000; font-weight: 700; box-shadow: 3px 3px 0px #000000; }

				@media (max-width: 860px) {
					.tpl-split .pmf-container { grid-template-columns: 1fr; padding: 36px 24px; gap: 28px; }
				}
				@media (max-width: 540px) {
					.pmf-container { padding: 32px 20px !important; }
					.pmf-form { flex-direction: column; }
					.pmf-countdown { grid-template-columns: repeat(2, 1fr); }
				}
				<?php echo $settings['custom_css']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</style>
		</head>
		<body class="tpl-<?php echo esc_attr( $template ); ?>">

			<?php if ( 'neon' === $template ) : ?>
				<div class="pmf-cyber-grid"></div>
			<?php endif; ?>

			<?php if ( 'split' === $template ) : ?>
				<main class="pmf-container">
					<div class="pmf-left-panel">
						<?php if ( ! empty( $settings['badge_text'] ) ) : ?>
							<span class="pmf-badge"><?php echo esc_html( $settings['badge_text'] ); ?></span>
						<?php endif; ?>
						<h1><?php echo esc_html( $settings['heading'] ); ?></h1>
						<div class="pmf-description">
							<?php echo wp_kses_post( wpautop( $settings['content'] ) ); ?>
						</div>
						<?php $this->render_socials_html( $settings ); ?>
					</div>
					<div class="pmf-right-panel">
						<?php $this->render_countdown_html( $settings, $end_timestamp ); ?>
						<?php $this->render_subscribe_html( $settings ); ?>
					</div>
				</main>

			<?php elseif ( 'terminal' === $template ) : ?>
				<main class="pmf-container">
					<div class="pmf-term-bar">
						<span class="pmf-term-dot" style="background:#ef4444;"></span>
						<span class="pmf-term-dot" style="background:#f59e0b;"></span>
						<span class="pmf-term-dot" style="background:#22c55e;"></span>
						<span class="pmf-term-title">root@<?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>: ~/maintenance_status.sh</span>
					</div>
					<div class="pmf-term-body">
						<?php if ( ! empty( $settings['badge_text'] ) ) : ?>
							<span class="pmf-badge">[<?php echo esc_html( $settings['badge_text'] ); ?>]</span>
						<?php endif; ?>
						<h1><?php echo esc_html( $settings['heading'] ); ?></h1>
						<div class="pmf-description">
							<?php echo wp_kses_post( wpautop( $settings['content'] ) ); ?>
						</div>
						<?php $this->render_countdown_html( $settings, $end_timestamp ); ?>
						<?php $this->render_subscribe_html( $settings ); ?>
						<?php $this->render_socials_html( $settings ); ?>
					</div>
				</main>

			<?php else : ?>
				<main class="pmf-container">
					<?php if ( ! empty( $settings['badge_text'] ) ) : ?>
						<span class="pmf-badge"><?php echo esc_html( $settings['badge_text'] ); ?></span>
					<?php endif; ?>
					<h1><?php echo esc_html( $settings['heading'] ); ?></h1>
					<div class="pmf-description">
						<?php echo wp_kses_post( wpautop( $settings['content'] ) ); ?>
					</div>
					<?php $this->render_countdown_html( $settings, $end_timestamp ); ?>
					<?php $this->render_subscribe_html( $settings ); ?>
					<?php $this->render_socials_html( $settings ); ?>
				</main>
			<?php endif; ?>

			<script>
				(function() {
					const countdownEl = document.getElementById('pmf-countdown');
					if (countdownEl) {
						const endTime = parseInt(countdownEl.getAttribute('data-end'), 10);
						const updateTimer = () => {
							const now = new Date().getTime();
							const diff = endTime - now;
							if (diff <= 0) {
								document.getElementById('pmf-days').textContent = '00';
								document.getElementById('pmf-hours').textContent = '00';
								document.getElementById('pmf-mins').textContent = '00';
								document.getElementById('pmf-secs').textContent = '00';
								<?php if ( ! empty( $settings['auto_disable'] ) && ! isset( $_GET['pmf_preview'] ) ) : ?>
								window.location.reload();
								<?php endif; ?>
								return;
							}
							const d = Math.floor(diff / (1000 * 60 * 60 * 24));
							const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
							const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
							const s = Math.floor((diff % (1000 * 60)) / 1000);

							document.getElementById('pmf-days').textContent = String(d).padStart(2, '0');
							document.getElementById('pmf-hours').textContent = String(h).padStart(2, '0');
							document.getElementById('pmf-mins').textContent = String(m).padStart(2, '0');
							document.getElementById('pmf-secs').textContent = String(s).padStart(2, '0');
						};
						updateTimer();
						setInterval(updateTimer, 1000);
					}

					const form = document.getElementById('pmf-subscribe-form');
					if (form) {
						form.addEventListener('submit', function(e) {
							e.preventDefault();
							const emailInput = document.getElementById('pmf-email');
							const submitBtn = document.getElementById('pmf-submit');
							const responseEl = document.getElementById('pmf-response');

							submitBtn.disabled = true;
							submitBtn.textContent = 'Saving...';
							responseEl.textContent = '';

							const formData = new URLSearchParams();
							formData.append('action', 'pmf_subscribe');
							formData.append('nonce', '<?php echo esc_js( wp_create_nonce( 'pmf_subscribe_nonce' ) ); ?>');
							formData.append('email', emailInput.value);

							fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
								method: 'POST',
								headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
								body: formData.toString()
							})
							.then(res => res.json())
							.then(data => {
								submitBtn.disabled = false;
								submitBtn.textContent = 'Notify Me';
								if (data.success) {
									responseEl.style.color = '#10b981';
									responseEl.textContent = data.data.message;
									emailInput.value = '';
								} else {
									responseEl.style.color = '#ef4444';
									responseEl.textContent = data.data ? data.data.message : 'An error occurred.';
								}
							})
							.catch(() => {
								submitBtn.disabled = false;
								submitBtn.textContent = 'Notify Me';
								responseEl.style.color = '#ef4444';
								responseEl.textContent = 'Connection error. Please try again.';
							});
						});
					}
				})();
			</script>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Render Admin Settings Dashboard
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$settings         = $this->get_settings();
		$templates        = $this->get_templates();
		$subscriber_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
		$editable_roles   = get_editable_roles();
		$preview_url      = add_query_arg( 'pmf_preview', '1', home_url( '/' ) );
		$current_ip       = $this->get_client_ip();
		?>
		<div class="wrap">
			<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
				<h1 style="margin:0;">Pro Maintenance Mode &mdash; Admin Dashboard</h1>
				<a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" class="button button-primary button-large">Preview Active Template &#8599;</a>
			</div>

			<?php if ( isset( $_GET['cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>All subscribers have been cleared.</p></div>
			<?php endif; ?>

			<style>
				.pmf-tpl-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; margin-top: 10px; }
				.pmf-tpl-card { border: 2px solid #dcdcde; border-radius: 10px; padding: 14px; background: #fff; cursor: pointer; transition: all 0.15s ease; display: flex; flex-direction: column; justify-content: space-between; }
				.pmf-tpl-card:hover { border-color: #2271b1; }
				.pmf-tpl-card.active { border-color: #2271b1; box-shadow: 0 0 0 2px rgba(34, 113, 177, 0.25); background: #f0f6fc; }
				.pmf-tpl-thumb { height: 90px; border-radius: 6px; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(0,0,0,0.1); position: relative; overflow: hidden; }
				.pmf-tpl-thumb-bar { width: 60%; height: 36px; border-radius: 4px; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.5); }
				.pmf-ip-manager-box { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px; max-width: 780px; }
				.pmf-ip-add-bar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 14px; }
				.pmf-ip-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; }
				.pmf-ip-table th, .pmf-ip-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
				.pmf-ip-table th { background: #f1f5f9; font-weight: 600; color: #334155; }
				.pmf-ip-remove { color: #dc2626; cursor: pointer; background: none; border: 1px solid #fca5a5; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
				.pmf-ip-remove:hover { background: #fef2f2; }
			</style>

			<form method="post" action="options.php">
				<?php settings_fields( 'pmf_settings_group' ); ?>

				<div style="background:#fff; padding:20px 25px; border:1px solid #ccd0d4; border-radius:6px; margin-top:20px;">
					<h2>1. Status & HTTP Response Mode</h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">Activate Maintenance Page</th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[status]" value="1" <?php checked( 1, $settings['status'] ); ?>>
									<strong>Enable Maintenance / Coming Soon Page</strong>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">HTTP Header Mode</th>
							<td>
								<fieldset>
									<label style="display:block; margin-bottom:8px;">
										<input type="radio" name="<?php echo esc_attr( $this->option_name ); ?>[http_status]" value="200" <?php checked( '200', $settings['http_status'] ); ?>>
										<strong>200 OK (Coming Soon / Custom Template Mode)</strong> — Renders your chosen template as a normal webpage without triggering proxy or browser 503 error screens.
									</label>
									<label style="display:block;">
										<input type="radio" name="<?php echo esc_attr( $this->option_name ); ?>[http_status]" value="503" <?php checked( '503', $settings['http_status'] ); ?>>
										<strong>503 Service Unavailable (Temporary SEO Maintenance)</strong> — Displays your chosen template while telling search engines to check back after <code><?php echo esc_html( $settings['retry_after'] ); ?></code> seconds.
									</label>
								</fieldset>
							</td>
						</tr>
					</table>

					<hr>
					<h2>2. External IP Bypass & Direct File Reading Manager</h2>
					<p class="description">Add external IP addresses below so they completely bypass Maintenance Mode, and configure which file types and folders can be read directly.</p>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">External IP Bypass Manager</th>
							<td>
								<div class="pmf-ip-manager-box">
									<div class="pmf-ip-add-bar">
										<input type="text" id="pmf_new_ip" placeholder="e.g. 203.0.113.50 or 198.51.100.0/24" class="regular-text code" style="width:260px;">
										<input type="text" id="pmf_new_ip_label" placeholder="Optional Label (e.g. Client Office / VPN)" class="regular-text" style="width:240px;">
										<button type="button" class="button button-primary" id="pmf_add_ip_btn">+ Add IP to List</button>
										<button type="button" class="button" id="pmf_add_current_ip_btn" data-ip="<?php echo esc_attr( $current_ip ); ?>">+ Add My Current IP (<?php echo esc_html( $current_ip ); ?>)</button>
									</div>

									<table class="pmf-ip-table" id="pmf_ip_table">
										<thead>
											<tr>
												<th style="width:45%;">External IP / Subnet / Wildcard</th>
												<th style="width:40%;">Label / Note</th>
												<th style="width:15%; text-align:right;">Action</th>
											</tr>
										</thead>
										<tbody id="pmf_ip_tbody">
											<!-- Populated dynamically via JS from saved settings -->
										</tbody>
									</table>

									<p style="margin-top:10px; font-size:12px; color:#475569;">
										<a href="#" id="pmf_toggle_raw_ips" style="text-decoration:none;">&#9881; Toggle Raw Bulk IP Editor</a>
									</p>
									<div id="pmf_raw_ip_wrap" style="display:none; margin-top:8px;">
										<textarea id="pmf_whitelisted_ips" name="<?php echo esc_attr( $this->option_name ); ?>[whitelisted_ips]" rows="4" class="large-text code" placeholder="One IP per line, e.g. 203.0.113.5 # Client"><?php echo esc_textarea( $settings['whitelisted_ips'] ); ?></textarea>
									</div>
								</div>
							</td>
						</tr>

						<tr>
							<th scope="row">Allow Direct File Reading</th>
							<td>
								<label style="display:block; margin-bottom:10px;">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[allow_file_reading]" value="1" <?php checked( 1, $settings['allow_file_reading'] ); ?>>
									<strong>Allow reading & downloading of files while Maintenance Mode is active</strong>
								</label>
								<p style="margin-bottom:4px; font-weight:600;">Readable File Extensions (comma-separated):</p>
								<input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[allowed_file_extensions]" value="<?php echo esc_attr( $settings['allowed_file_extensions'] ); ?>" class="large-text code">
								<p class="description">Any direct request for a file ending in these extensions (e.g., <code>.pdf</code>, <code>.zip</code>, <code>.json</code>, <code>.txt</code>, <code>.csv</code>, <code>.jpg</code>) will be readable without triggering the maintenance screen.</p>
								<label style="display:block; margin-top:10px;">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[allow_wp_attachments]" value="1" <?php checked( 1, $settings['allow_wp_attachments'] ); ?>>
									Also allow WordPress Media Library attachment pages & files to be read
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row">Excluded Folders & URL Paths</th>
							<td>
								<textarea name="<?php echo esc_attr( $this->option_name ); ?>[exclude_urls]" rows="4" class="large-text code"><?php echo esc_textarea( $settings['exclude_urls'] ); ?></textarea>
								<p class="description">One path or folder per line (e.g. <code>/wp-content/uploads/</code>, <code>/downloads/</code>, <code>/custom-folder/</code>). Any file or page inside these paths can be read freely.</p>
							</td>
						</tr>

						<tr>
							<th scope="row">Bypass User Roles</th>
							<td>
								<fieldset>
									<?php foreach ( $editable_roles as $role_key => $role_details ) : ?>
										<label style="display:inline-block; margin-right:16px; margin-bottom:6px;">
											<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[bypass_roles][]" value="<?php echo esc_attr( $role_key ); ?>"
												<?php checked( in_array( $role_key, (array) $settings['bypass_roles'], true ) ); ?>
												<?php disabled( 'administrator' === $role_key ); ?>>
											<?php echo esc_html( translate_user_role( $role_details['name'] ) ); ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
							</td>
						</tr>

						<tr>
							<th scope="row">Bot & REST API Options</th>
							<td>
								<label style="display:block; margin-bottom:8px;">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[bypass_bots]" value="1" <?php checked( 1, $settings['bypass_bots'] ); ?>>
									Allow Search Engine Bots (Googlebot, Bingbot) to bypass maintenance page
								</label>
								<label style="display:block;">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[disable_rest]" value="1" <?php checked( 1, $settings['disable_rest'] ); ?>>
									Block WordPress REST API for non-whitelisted visitors
								</label>
							</td>
						</tr>
					</table>

					<?php submit_button( 'Save All Maintenance Settings' ); ?>

					<hr>
					<h2>3. Select a Maintenance Template</h2>
					<p class="description">Choose from 6 pre-built visual layouts. Click "Quick Preview" on any card to inspect it live in a new tab.</p>

					<div class="pmf-tpl-grid">
						<?php foreach ( $templates as $tpl_key => $tpl_data ) : ?>
							<?php
							$is_active       = ( $settings['template'] === $tpl_key );
							$tpl_preview_url = add_query_arg(
								array(
									'pmf_preview'  => '1',
									'pmf_template' => $tpl_key,
								),
								home_url( '/' )
							);
							?>
							<label class="pmf-tpl-card <?php echo $is_active ? 'active' : ''; ?>">
								<div>
									<div class="pmf-tpl-thumb" style="background: <?php echo esc_attr( $tpl_data['preview_bg'] ); ?>;">
										<div class="pmf-tpl-thumb-bar" style="border-color: <?php echo esc_attr( $tpl_data['accent'] ); ?>;">
											<?php echo esc_html( strtoupper( $tpl_key ) ); ?>
										</div>
									</div>
									<div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
										<input type="radio" class="pmf-tpl-radio" name="<?php echo esc_attr( $this->option_name ); ?>[template]" value="<?php echo esc_attr( $tpl_key ); ?>" <?php checked( $settings['template'], $tpl_key ); ?>>
										<strong style="font-size:14px;"><?php echo esc_html( $tpl_data['name'] ); ?></strong>
									</div>
									<p style="font-size:12px; color:#50575e; margin:0 0 12px 0;"><?php echo esc_html( $tpl_data['description'] ); ?></p>
								</div>
								<div>
									<a href="<?php echo esc_url( $tpl_preview_url ); ?>" target="_blank" class="button button-small">Quick Preview &#8599;</a>
								</div>
							</label>
						<?php endforeach; ?>
					</div>

					<hr style="margin-top:28px;">
					<h2>4. Content & Custom Styling</h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">Browser Page Title</th>
							<td>
								<input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[page_title]" value="<?php echo esc_attr( $settings['page_title'] ); ?>" class="regular-text">
							</td>
						</tr>
						<tr>
							<th scope="row">Status Badge Text</th>
							<td>
								<input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[badge_text]" value="<?php echo esc_attr( $settings['badge_text'] ); ?>" class="regular-text" placeholder="SCHEDULED UPDATE">
							</td>
						</tr>
						<tr>
							<th scope="row">Main Heading</th>
							<td>
								<input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[heading]" value="<?php echo esc_attr( $settings['heading'] ); ?>" class="large-text">
							</td>
						</tr>
						<tr>
							<th scope="row">Message Content</th>
							<td>
								<?php
								wp_editor(
									$settings['content'],
									'pmf_content_editor',
									array(
										'textarea_name' => $this->option_name . '[content]',
										'textarea_rows' => 5,
										'media_buttons' => true,
									)
								);
								?>
							</td>
						</tr>
						<tr>
							<th scope="row">Template Color Override</th>
							<td>
								<label style="display:block; margin-bottom:14px;">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[use_custom_colors]" value="1" <?php checked( 1, $settings['use_custom_colors'] ); ?>>
									Override the selected template's default color palette with custom colors below
								</label>
								<div style="display:flex; gap:24px; flex-wrap:wrap;">
									<div>
										<label style="display:block; margin-bottom:4px; font-weight:600;">Background Color</label>
										<input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[bg_color]" value="<?php echo esc_attr( $settings['bg_color'] ); ?>" class="pmf-color-picker">
									</div>
									<div>
										<label style="display:block; margin-bottom:4px; font-weight:600;">Text Color</label>
										<input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[text_color]" value="<?php echo esc_attr( $settings['text_color'] ); ?>" class="pmf-color-picker">
									</div>
									<div>
										<label style="display:block; margin-bottom:4px; font-weight:600;">Accent / Button Color</label>
										<input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[accent_color]" value="<?php echo esc_attr( $settings['accent_color'] ); ?>" class="pmf-color-picker">
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">Custom Background Image</th>
							<td>
								<input type="url" id="pmf_bg_image" name="<?php echo esc_attr( $this->option_name ); ?>[bg_image]" value="<?php echo esc_url( $settings['bg_image'] ); ?>" class="regular-text">
								<button type="button" class="button" id="pmf_upload_btn">Select Image</button>
								<p class="description">Optional fullscreen background image (applies a dark overlay automatically).</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Custom CSS</th>
							<td>
								<textarea name="<?php echo esc_attr( $this->option_name ); ?>[custom_css]" rows="3" class="large-text code" placeholder=".pmf-container { border-radius: 0; }"><?php echo esc_textarea( $settings['custom_css'] ); ?></textarea>
							</td>
						</tr>
					</table>

					<hr>
					<h2>5. Countdown, Subscriptions & Social Links</h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">Countdown Timer</th>
							<td>
								<label style="display:block; margin-bottom:10px;">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[countdown_enabled]" value="1" <?php checked( 1, $settings['countdown_enabled'] ); ?>>
									Show Countdown Timer
								</label>
								<label style="display:block; margin-bottom:10px;">
									Launch Date & Time:
									<input type="datetime-local" name="<?php echo esc_attr( $this->option_name ); ?>[countdown_end]" value="<?php echo esc_attr( $settings['countdown_end'] ); ?>">
								</label>
								<label style="display:block;">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[auto_disable]" value="1" <?php checked( 1, $settings['auto_disable'] ); ?>>
									Automatically disable Maintenance Mode when countdown reaches zero
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">Email Lead Capture</th>
							<td>
								<label style="display:block; margin-bottom:10px;">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[subscribe_enabled]" value="1" <?php checked( 1, $settings['subscribe_enabled'] ); ?>>
									Enable Email Subscription Form
								</label>
								<input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[subscribe_text]" value="<?php echo esc_attr( $settings['subscribe_text'] ); ?>" class="regular-text" placeholder="Get notified when we go live:">
								<div style="margin-top:12px; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; display:inline-block;">
									<strong>Total Subscribers: <?php echo esc_html( $subscriber_count ); ?></strong>
									<?php if ( $subscriber_count > 0 ) : ?>
										&nbsp;|&nbsp;
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'options-general.php?page=pro-maintenance-free&pmf_action=export_csv' ), 'pmf_export_csv_nonce' ) ); ?>" class="button button-small">Export CSV</a>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'options-general.php?page=pro-maintenance-free&pmf_action=clear_subscribers' ), 'pmf_clear_subscribers_nonce' ) ); ?>" class="button button-small" onclick="return confirm('Delete all subscribers permanently?');" style="color:#b91c1c;">Clear List</a>
									<?php endif; ?>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">Social & Contact Links</th>
							<td>
								<p style="margin-bottom:8px;"><input type="url" name="<?php echo esc_attr( $this->option_name ); ?>[social_facebook]" value="<?php echo esc_url( $settings['social_facebook'] ); ?>" class="regular-text" placeholder="https://facebook.com/..."></p>
								<p style="margin-bottom:8px;"><input type="url" name="<?php echo esc_attr( $this->option_name ); ?>[social_twitter]" value="<?php echo esc_url( $settings['social_twitter'] ); ?>" class="regular-text" placeholder="https://x.com/..."></p>
								<p style="margin-bottom:8px;"><input type="url" name="<?php echo esc_attr( $this->option_name ); ?>[social_instagram]" value="<?php echo esc_url( $settings['social_instagram'] ); ?>" class="regular-text" placeholder="https://instagram.com/..."></p>
								<p style="margin-bottom:8px;"><input type="url" name="<?php echo esc_attr( $this->option_name ); ?>[social_linkedin]" value="<?php echo esc_url( $settings['social_linkedin'] ); ?>" class="regular-text" placeholder="https://linkedin.com/..."></p>
								<p><input type="email" name="<?php echo esc_attr( $this->option_name ); ?>[contact_email]" value="<?php echo esc_attr( $settings['contact_email'] ); ?>" class="regular-text" placeholder="contact@yourdomain.com"></p>
							</td>
						</tr>
						<tr>
							<th scope="row">SEO Retry-After Header (Seconds)</th>
							<td>
								<input type="number" name="<?php echo esc_attr( $this->option_name ); ?>[retry_after]" value="<?php echo esc_attr( $settings['retry_after'] ); ?>" class="small-text" min="60">
							</td>
						</tr>
					</table>

					<?php submit_button( 'Save All Maintenance Settings' ); ?>
				</div>
			</form>
		</div>

		<script>
			jQuery(document).ready(function($) {
				$('.pmf-color-picker').wpColorPicker();

				$('.pmf-tpl-radio').on('change', function() {
					$('.pmf-tpl-card').removeClass('active');
					$(this).closest('.pmf-tpl-card').addClass('active');
				});

				// Interactive External IP Table Manager synced with #pmf_whitelisted_ips
				var $rawTextarea = $('#pmf_whitelisted_ips');
				var $tbody = $('#pmf_ip_tbody');

				function renderIpTable() {
					$tbody.empty();
					var lines = $rawTextarea.val().split(/\r?\n/);
					var hasRows = false;

					lines.forEach(function(line, index) {
						var trimmed = line.trim();
						if (!trimmed) return;
						hasRows = true;

						var parts = trimmed.split('#');
						var ipPart = parts[0].trim();
						var labelPart = parts.slice(1).join('#').trim();

						var $tr = $('<tr>');
						$tr.append($('<td>').html('<code>' + $('<div>').text(ipPart).html() + '</code>'));
						$tr.append($('<td>').text(labelPart || '—'));
						var $removeBtn = $('<button type="button" class="pmf-ip-remove">Remove</button>').on('click', function() {
							removeIpLine(index);
						});
						$tr.append($('<td style="text-align:right;">').append($removeBtn));
						$tbody.append($tr);
					});

					if (!hasRows) {
						$tbody.append('<tr><td colspan="3" style="text-align:center; color:#64748b;">No external IPs whitelisted yet. Add an IP above.</td></tr>');
					}
				}

				function removeIpLine(lineIndex) {
					var lines = $rawTextarea.val().split(/\r?\n/);
					lines.splice(lineIndex, 1);
					$rawTextarea.val(lines.filter(Boolean).join("\n"));
					renderIpTable();
				}

				function addIpEntry(ip, label) {
					ip = ip.trim();
					label = label.trim();
					if (!ip) return;

					var entry = label ? (ip + ' # ' + label) : ip;
					var current = $rawTextarea.val().trim();
					$rawTextarea.val(current ? (current + "\n" + entry) : entry);
					renderIpTable();
				}

				$('#pmf_add_ip_btn').on('click', function(e) {
					e.preventDefault();
					var ip = $('#pmf_new_ip').val();
					var label = $('#pmf_new_ip_label').val();
					if (!ip.trim()) {
						$('#pmf_new_ip').focus();
						return;
					}
					addIpEntry(ip, label);
					$('#pmf_new_ip').val('');
					$('#pmf_new_ip_label').val('');
				});

				$('#pmf_add_current_ip_btn').on('click', function(e) {
					e.preventDefault();
					var myIp = $(this).data('ip');
					if ($rawTextarea.val().indexOf(myIp) === -1) {
						addIpEntry(myIp, 'My Admin IP');
					}
				});

				$('#pmf_toggle_raw_ips').on('click', function(e) {
					e.preventDefault();
					$('#pmf_raw_ip_wrap').slideToggle(150);
				});

				$rawTextarea.on('input', function() {
					renderIpTable();
				});

				renderIpTable();

				// WP Media Uploader
				var mediaUploader;
				$('#pmf_upload_btn').on('click', function(e) {
					e.preventDefault();
					if (mediaUploader) {
						mediaUploader.open();
						return;
					}
					mediaUploader = wp.media({
						title: 'Select Background Image',
						button: { text: 'Use this image' },
						multiple: false
					});
					mediaUploader.on('select', function() {
						var attachment = mediaUploader.state().get('selection').first().toJSON();
						$('#pmf_bg_image').val(attachment.url);
					});
					mediaUploader.open();
				});
			});
		</script>
		<?php
	}
}

new Pro_Maintenance_Free();

<?php
/**
 * Plugin Name:       Monster Maintenance Mode
 * Plugin URI:        https://github.com/MonsterITServices/Monster-Maintenance-Mode
 * Description:       Full-featured maintenance and coming soon plugin. Features an interactive External IP Bypass Manager, direct file reading controls, 6 visual templates, 200 OK / 503 HTTP modes, live preview, countdown timer, and email capture with CSV export.
 * Version:           2.2.0
 * Requires at least: 5.8
 * Tested up to:      6.7
 * Requires PHP:      7.4
 * Author:            monster-it
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       monster-maintenance-mode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Monster_Maintenance_Mode {

	/**
	 * Option name in wp_options.
	 *
	 * @var string
	 */
	private $option_name = 'mmmode_settings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Lifecycle hooks.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );

		// Admin hooks.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_csv_export' ) );
		add_action( 'admin_init', array( $this, 'handle_clear_subscribers' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_admin_bar_styles' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_indicator' ), 100 );

		// Frontend & AJAX hooks.
		add_action( 'template_redirect', array( $this, 'render_maintenance_page' ), 1 );
		add_action( 'wp_ajax_mmmode_subscribe', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_nopriv_mmmode_subscribe', array( $this, 'ajax_subscribe' ) );

		// REST API protection.
		add_filter( 'rest_authentication_errors', array( $this, 'restrict_rest_api' ) );
	}

	/**
	 * Available visual templates.
	 *
	 * @return array
	 */
	public function get_templates() {
		return array(
			'glass'     => array(
				'name'        => __( 'Modern Glassmorphism', 'monster-maintenance-mode' ),
				'description' => __( 'Frosted glass card with smooth blur effects and glowing accents.', 'monster-maintenance-mode' ),
				'preview_bg'  => 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)',
				'accent'      => '#3b82f6',
			),
			'split'     => array(
				'name'        => __( 'Corporate Split-Screen', 'monster-maintenance-mode' ),
				'description' => __( 'Two-column agency layout separating your message from the countdown & lead form.', 'monster-maintenance-mode' ),
				'preview_bg'  => 'linear-gradient(90deg, #111827 50%, #1f2937 50%)',
				'accent'      => '#10b981',
			),
			'editorial' => array(
				'name'        => __( 'Minimalist Editorial', 'monster-maintenance-mode' ),
				'description' => __( 'Classic serif typography with clean lines, ideal for publishers and authors.', 'monster-maintenance-mode' ),
				'preview_bg'  => '#f8fafc',
				'accent'      => '#000000',
			),
			'terminal'  => array(
				'name'        => __( 'Cyber Terminal', 'monster-maintenance-mode' ),
				'description' => __( 'Matrix-inspired dark command prompt window with phosphor green monospace text.', 'monster-maintenance-mode' ),
				'preview_bg'  => '#050505',
				'accent'      => '#22c55e',
			),
			'neon'      => array(
				'name'        => __( 'Neon Synthwave Grid', 'monster-maintenance-mode' ),
				'description' => __( 'Animated 3D perspective horizon grid with cyberpunk magenta and cyan glow.', 'monster-maintenance-mode' ),
				'preview_bg'  => 'linear-gradient(180deg, #090014 0%, #240046 100%)',
				'accent'      => '#f72585',
			),
			'brutalist' => array(
				'name'        => __( 'Bold Industrial', 'monster-maintenance-mode' ),
				'description' => __( 'High-contrast brutalist card with heavy black borders and hard offset shadows.', 'monster-maintenance-mode' ),
				'preview_bg'  => '#facc15',
				'accent'      => '#000000',
			),
		);
	}

	/**
	 * Default plugin options.
	 *
	 * @return array
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
			'page_title'              => get_bloginfo( 'name' ) . ' - ' . __( 'Coming Soon', 'monster-maintenance-mode' ),
			'badge_text'              => __( 'SCHEDULED UPDATE', 'monster-maintenance-mode' ),
			'heading'                 => __( 'We Are Currently Working on Something Awesome', 'monster-maintenance-mode' ),
			'content'                 => __( 'Our website is undergoing scheduled maintenance and upgrades. Thank you for your patience—please check back soon or subscribe below for updates!', 'monster-maintenance-mode' ),
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
			'subscribe_text'          => __( 'Get notified the moment we go live:', 'monster-maintenance-mode' ),
			'social_facebook'         => '',
			'social_twitter'          => '',
			'social_instagram'        => '',
			'social_linkedin'         => '',
			'contact_email'           => get_option( 'admin_email' ),
			'retry_after'             => 3600,
		);
	}

	/**
	 * Get merged settings (also migrates legacy pmf_settings if present).
	 *
	 * @return array
	 */
	public function get_settings() {
		$saved = get_option( $this->option_name, false );
		if ( false === $saved ) {
			$legacy = get_option( 'pmf_settings', array() );
			$saved  = is_array( $legacy ) ? $legacy : array();
		}
		return wp_parse_args( $saved, $this->get_defaults() );
	}

	/**
	 * Create subscribers table and initialize options on activation.
	 */
	public function activate() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'mmmode_subscribers';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
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
			$legacy = get_option( 'pmf_settings', false );
			if ( false !== $legacy && is_array( $legacy ) ) {
				update_option( $this->option_name, wp_parse_args( $legacy, $this->get_defaults() ) );
			} else {
				update_option( $this->option_name, $this->get_defaults() );
			}
		}
	}

	/**
	 * Get CSS for the top admin bar indicator.
	 *
	 * @return string
	 */
	private function get_admin_bar_css() {
		return '#wpadminbar .mmmode-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:6px; }
		#wpadminbar .mmmode-active { background:#ef4444; box-shadow:0 0 6px #ef4444; }
		#wpadminbar .mmmode-inactive { background:#10b981; }
		#wpadminbar .mmmode-bar-on > a { background:rgba(239, 68, 68, 0.25) !important; font-weight:600 !important; }';
	}

	/**
	 * Enqueue Admin Bar CSS on Frontend.
	 */
	public function enqueue_admin_bar_styles() {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_inline_style( 'admin-bar', $this->get_admin_bar_css() );
	}

	/**
	 * Enqueue WP Media Uploader, Color Picker, and Inline Scripts/Styles in Admin.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( is_admin_bar_showing() && current_user_can( 'manage_options' ) ) {
			wp_add_inline_style( 'admin-bar', $this->get_admin_bar_css() );
		}

		if ( 'settings_page_monster-maintenance-mode' !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		$admin_css = '
			.mmmode-tpl-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; margin-top: 10px; }
			.mmmode-tpl-card { border: 2px solid #dcdcde; border-radius: 10px; padding: 14px; background: #fff; cursor: pointer; transition: all 0.15s ease; display: flex; flex-direction: column; justify-content: space-between; }
			.mmmode-tpl-card:hover { border-color: #2271b1; }
			.mmmode-tpl-card.active { border-color: #2271b1; box-shadow: 0 0 0 2px rgba(34, 113, 177, 0.25); background: #f0f6fc; }
			.mmmode-tpl-thumb { height: 90px; border-radius: 6px; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(0,0,0,0.1); position: relative; overflow: hidden; }
			.mmmode-tpl-thumb-bar { width: 60%; height: 36px; border-radius: 4px; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.5); }
			.mmmode-ip-manager-box { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px; max-width: 780px; }
			.mmmode-ip-add-bar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 14px; }
			.mmmode-ip-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; }
			.mmmode-ip-table th, .mmmode-ip-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
			.mmmode-ip-table th { background: #f1f5f9; font-weight: 600; color: #334155; }
			.mmmode-ip-remove { color: #dc2626; cursor: pointer; background: none; border: 1px solid #fca5a5; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
			.mmmode-ip-remove:hover { background: #fef2f2; }
		';
		wp_add_inline_style( 'wp-color-picker', $admin_css );

		$admin_js = '
			jQuery(document).ready(function($) {
				$(".mmmode-color-picker").wpColorPicker();

				$(".mmmode-tpl-radio").on("change", function() {
					$(".mmmode-tpl-card").removeClass("active");
					$(this).closest(".mmmode-tpl-card").addClass("active");
				});

				var $rawTextarea = $("#mmmode_whitelisted_ips");
				var $tbody = $("#mmmode_ip_tbody");

				function renderIpTable() {
					$tbody.empty();
					if (!$rawTextarea.length) return;
					var lines = $rawTextarea.val().split(/\r?\n/);
					var hasRows = false;

					lines.forEach(function(line, index) {
						var trimmed = line.trim();
						if (!trimmed) return;
						hasRows = true;

						var parts = trimmed.split("#");
						var ipPart = parts[0].trim();
						var labelPart = parts.slice(1).join("#").trim();

						var $tr = $("<tr>");
						$tr.append($("<td>").html("<code>" + $("<div>").text(ipPart).html() + "</code>"));
						$tr.append($("<td>").text(labelPart || "—"));
						var $removeBtn = $("<button type=\"button\" class=\"mmmode-ip-remove\">Remove</button>").on("click", function() {
							removeIpLine(index);
						});
						$tr.append($("<td style=\"text-align:right;\">").append($removeBtn));
						$tbody.append($tr);
					});

					if (!hasRows) {
						$tbody.append("<tr><td colspan=\"3\" style=\"text-align:center; color:#64748b;\">No external IPs whitelisted yet. Add an IP above.</td></tr>");
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

					var entry = label ? (ip + " # " + label) : ip;
					var current = $rawTextarea.val().trim();
					$rawTextarea.val(current ? (current + "\n" + entry) : entry);
					renderIpTable();
				}

				$("#mmmode_add_ip_btn").on("click", function(e) {
					e.preventDefault();
					var ip = $("#mmmode_new_ip").val();
					var label = $("#mmmode_new_ip_label").val();
					if (!ip.trim()) {
						$("#mmmode_new_ip").focus();
						return;
					}
					addIpEntry(ip, label);
					$("#mmmode_new_ip").val("");
					$("#mmmode_new_ip_label").val("");
				});

				$("#mmmode_add_current_ip_btn").on("click", function(e) {
					e.preventDefault();
					var myIp = $(this).data("ip");
					if ($rawTextarea.val().indexOf(myIp) === -1) {
						addIpEntry(myIp, "My Admin IP");
					}
				});

				$("#mmmode_toggle_raw_ips").on("click", function(e) {
					e.preventDefault();
					$("#mmmode_raw_ip_wrap").slideToggle(150);
				});

				$rawTextarea.on("input", function() {
					renderIpTable();
				});

				renderIpTable();

				var mediaUploader;
				$("#mmmode_upload_btn").on("click", function(e) {
					e.preventDefault();
					if (mediaUploader) {
						mediaUploader.open();
						return;
					}
					mediaUploader = wp.media({
						title: "Select Background Image",
						button: { text: "Use this image" },
						multiple: false
					});
					mediaUploader.on("select", function() {
						var attachment = mediaUploader.state().get("selection").first().toJSON();
						$("#mmmode_bg_image").val(attachment.url);
					});
					mediaUploader.open();
				});
			});
		';
		wp_add_inline_script( 'wp-color-picker', $admin_js );
	}

	/**
	 * Add Settings Page.
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'Monster Maintenance Mode', 'monster-maintenance-mode' ),
			__( 'Maintenance Mode', 'monster-maintenance-mode' ),
			'manage_options',
			'monster-maintenance-mode',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Register and sanitize settings.
	 */
	public function register_settings() {
		register_setting(
			'mmmode_settings_group',
			$this->option_name,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize plugin settings array.
	 *
	 * @param array $input Raw input array.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		if ( ! is_array( $input ) ) {
			return $this->get_defaults();
		}

		$defaults  = $this->get_defaults();
		$templates = array_keys( $this->get_templates() );
		$clean     = array();

		$clean['status']               = ! empty( $input['status'] ) ? 1 : 0;
		$clean['http_status']          = ( isset( $input['http_status'] ) && '503' === $input['http_status'] ) ? '503' : '200';
		$clean['template']             = ( isset( $input['template'] ) && in_array( $input['template'], $templates, true ) ) ? sanitize_key( $input['template'] ) : 'glass';
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
		$clean['bg_color']                = ! empty( $input['bg_color'] ) ? sanitize_hex_color( $input['bg_color'] ) : $defaults['bg_color'];
		$clean['bg_image']                = isset( $input['bg_image'] ) ? esc_url_raw( $input['bg_image'] ) : '';
		$clean['text_color']              = ! empty( $input['text_color'] ) ? sanitize_hex_color( $input['text_color'] ) : $defaults['text_color'];
		$clean['accent_color']            = ! empty( $input['accent_color'] ) ? sanitize_hex_color( $input['accent_color'] ) : $defaults['accent_color'];
		$clean['custom_css']              = isset( $input['custom_css'] ) ? sanitize_textarea_field( wp_strip_all_tags( $input['custom_css'], false ) ) : '';
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
	 * Top Admin Bar status indicator.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance.
	 */
	public function admin_bar_indicator( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = $this->get_settings();
		$active   = ! empty( $settings['status'] );

		$wp_admin_bar->add_node(
			array(
				'id'    => 'mmmode-status',
				'title' => $active
					? '<span class="mmmode-dot mmmode-active"></span> ' . esc_html__( 'Maintenance: ON', 'monster-maintenance-mode' ) . ' (' . esc_html( strtoupper( $settings['template'] ) ) . ')'
					: '<span class="mmmode-dot mmmode-inactive"></span> ' . esc_html__( 'Maintenance: OFF', 'monster-maintenance-mode' ),
				'href'  => admin_url( 'options-general.php?page=monster-maintenance-mode' ),
				'meta'  => array(
					'class' => $active ? 'mmmode-bar-on' : 'mmmode-bar-off',
				),
			)
		);
	}

	/**
	 * Format a single CSV line safely without direct filesystem calls.
	 *
	 * @param array $fields Row values.
	 * @return string
	 */
	private function format_csv_row( $fields ) {
		$escaped = array();
		foreach ( $fields as $field ) {
			$val       = (string) $field;
			$escaped[] = '"' . str_replace( '"', '""', $val ) . '"';
		}
		return implode( ',', $escaped ) . "\r\n";
	}

	/**
	 * Export Subscribers to CSV.
	 */
	public function handle_csv_export() {
		if ( ! isset( $_GET['mmmode_action'] ) || 'export_csv' !== sanitize_text_field( wp_unslash( $_GET['mmmode_action'] ) ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mmmode_export_csv_nonce' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'monster-maintenance-mode' ) );
		}

		global $wpdb;
		$results = $wpdb->get_results(
			"SELECT id, email, ip_address, created_at FROM {$wpdb->prefix}mmmode_subscribers ORDER BY created_at DESC",
			ARRAY_A
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=maintenance-subscribers-' . gmdate( 'Y-m-d' ) . '.csv' );

		echo wp_kses_post( $this->format_csv_row( array( 'ID', 'Email', 'IP Address', 'Date Subscribed' ) ) );

		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				echo wp_kses_post(
					$this->format_csv_row(
						array(
							absint( $row['id'] ),
							sanitize_email( $row['email'] ),
							sanitize_text_field( $row['ip_address'] ),
							sanitize_text_field( $row['created_at'] ),
						)
					)
				);
			}
		}
		exit;
	}

	/**
	 * Delete All Subscribers.
	 */
	public function handle_clear_subscribers() {
		if ( ! isset( $_GET['mmmode_action'] ) || 'clear_subscribers' !== sanitize_text_field( wp_unslash( $_GET['mmmode_action'] ) ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mmmode_clear_subscribers_nonce' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'monster-maintenance-mode' ) );
		}

		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}mmmode_subscribers" );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => 'monster-maintenance-mode',
					'mmmode_cleared'   => '1',
					'_mmmode_notice'   => wp_create_nonce( 'mmmode_cleared_notice' ),
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Handle Frontend AJAX Email Subscription.
	 */
	public function ajax_subscribe() {
		check_ajax_referer( 'mmmode_subscribe_nonce', 'nonce' );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'monster-maintenance-mode' ) ) );
		}

		global $wpdb;
		$ip     = $this->get_client_ip();
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}mmmode_subscribers WHERE email = %s",
				$email
			)
		);

		if ( $exists ) {
			wp_send_json_error( array( 'message' => __( 'You are already subscribed!', 'monster-maintenance-mode' ) ) );
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'mmmode_subscribers',
			array(
				'email'      => $email,
				'ip_address' => $ip,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);

		if ( $inserted ) {
			wp_send_json_success( array( 'message' => __( 'Thank you! We will notify you when we launch.', 'monster-maintenance-mode' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Could not save your email. Please try again.', 'monster-maintenance-mode' ) ) );
		}
	}

	/**
	 * Check if Visitor IP matches the Admin Dashboard Whitelist (Exact IP, Wildcard, or CIDR).
	 *
	 * @param string $client_ip     Visitor IP address.
	 * @param string $whitelist_raw Newline-separated IP rules.
	 * @return bool
	 */
	private function is_ip_whitelisted( $client_ip, $whitelist_raw ) {
		if ( empty( $client_ip ) || empty( $whitelist_raw ) ) {
			return false;
		}

		$lines = preg_split( '/[\r\n]+/', $whitelist_raw );
		foreach ( $lines as $line ) {
			$rule = trim( preg_replace( '/#.*$/', '', $line ) );
			if ( empty( $rule ) ) {
				continue;
			}

			// 1. Exact IP match.
			if ( 0 === strcasecmp( $client_ip, $rule ) ) {
				return true;
			}

			// 2. Wildcard match (e.g. 192.168.1.*).
			if ( false !== strpos( $rule, '*' ) ) {
				$regex = '/^' . str_replace( '\*', '[0-9a-fA-F:.]+', preg_quote( $rule, '/' ) ) . '$/i';
				if ( preg_match( $regex, $client_ip ) ) {
					return true;
				}
			}

			// 3. CIDR Subnet match (e.g. 203.0.113.0/24).
			if ( false !== strpos( $rule, '/' ) ) {
				list( $subnet, $bits ) = explode( '/', $rule, 2 );
				$subnet                = trim( $subnet );
				$bits                  = (int) trim( $bits );

				if ( filter_var( $client_ip, FILTER_VALIDATE_IP ) && filter_var( $subnet, FILTER_VALIDATE_IP ) ) {
					$ip_bin     = inet_pton( $client_ip );
					$subnet_bin = inet_pton( $subnet );

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
		}

		return false;
	}

	/**
	 * Check if Request URI points to an allowed readable file extension or attachment.
	 *
	 * @param string $request_uri Current request URI.
	 * @param array  $settings    Plugin settings.
	 * @return bool
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

		$allowed_exts = array_filter(
			array_map(
				function ( $item ) {
					return strtolower( ltrim( trim( $item ), '.' ) );
				},
				explode( ',', $settings['allowed_file_extensions'] )
			)
		);

		return in_array( $ext, $allowed_exts, true );
	}

	/**
	 * Determine if current visitor or file request should bypass maintenance mode.
	 *
	 * @return bool
	 */
	private function should_bypass() {
		// Allow instant live preview for admins via ?mmmode_preview=1.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['mmmode_preview'] ) && current_user_can( 'manage_options' ) ) {
			return false;
		}

		$settings = $this->get_settings();

		// 1. Check if maintenance mode is disabled.
		if ( empty( $settings['status'] ) ) {
			return true;
		}

		// 2. Check Auto-Disable on Countdown Expiration.
		if ( ! empty( $settings['countdown_enabled'] ) && ! empty( $settings['auto_disable'] ) && ! empty( $settings['countdown_end'] ) ) {
			$end_timestamp = strtotime( $settings['countdown_end'] );
			if ( $end_timestamp && time() >= $end_timestamp ) {
				$settings['status'] = 0;
				update_option( $this->option_name, $settings );
				return true;
			}
		}

		// 3. Always allow WP-CLI, Cron, Admin, Login, and AJAX.
		if ( defined( 'WP_CLI' ) || wp_doing_cron() || is_admin() || wp_doing_ajax() ) {
			return true;
		}

		$script_name = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
		if ( in_array( $script_name, array( 'wp-login.php', 'wp-register.php' ), true ) ) {
			return true;
		}

		// 4. Check Logged-in User Roles.
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

		// 5. Check External IP Addresses configured in Admin Dashboard.
		$client_ip = $this->get_client_ip();
		if ( $this->is_ip_whitelisted( $client_ip, $settings['whitelisted_ips'] ) ) {
			return true;
		}

		// 6. Check Direct File Reading & Allowed File Extensions configured in Admin Dashboard.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( $this->is_allowed_file_request( $request_uri, $settings ) ) {
			return true;
		}

		// 7. Check Excluded URLs / Slugs / File Directories configured in Admin Dashboard.
		if ( ! empty( $settings['exclude_urls'] ) && ! empty( $request_uri ) ) {
			$urls = array_filter( array_map( 'trim', explode( "\n", $settings['exclude_urls'] ) ) );
			foreach ( $urls as $excluded_path ) {
				if ( '/' !== $excluded_path && false !== stripos( $request_uri, $excluded_path ) ) {
					return true;
				}
			}
		}

		// 8. Check Search Engine Bots.
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
	 * Block REST API if configured.
	 *
	 * @param WP_Error|null|bool $result Error from another authentication handler, null if we should handle it, or another value if not.
	 * @return WP_Error|null|bool
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
				__( 'The site is currently undergoing maintenance.', 'monster-maintenance-mode' ),
				array( 'status' => $status_code )
			);
		}
		return $result;
	}

	/**
	 * Helper: Get Visitor IP (Supports Cloudflare, Reverse Proxy, and Direct Remote Addr).
	 *
	 * @return string
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
	 * Render Reusable Countdown Block.
	 *
	 * @param array $settings      Plugin settings.
	 * @param int   $end_timestamp End timestamp in milliseconds.
	 */
	private function render_countdown_html( $settings, $end_timestamp ) {
		if ( empty( $settings['countdown_enabled'] ) || $end_timestamp <= 0 ) {
			return;
		}
		?>
		<div class="mmmode-countdown" id="mmmode-countdown" data-end="<?php echo esc_attr( $end_timestamp ); ?>">
			<div class="mmmode-time-box">
				<span class="mmmode-time-val" id="mmmode-days">00</span>
				<span class="mmmode-time-label"><?php esc_html_e( 'Days', 'monster-maintenance-mode' ); ?></span>
			</div>
			<div class="mmmode-time-box">
				<span class="mmmode-time-val" id="mmmode-hours">00</span>
				<span class="mmmode-time-label"><?php esc_html_e( 'Hours', 'monster-maintenance-mode' ); ?></span>
			</div>
			<div class="mmmode-time-box">
				<span class="mmmode-time-val" id="mmmode-mins">00</span>
				<span class="mmmode-time-label"><?php esc_html_e( 'Minutes', 'monster-maintenance-mode' ); ?></span>
			</div>
			<div class="mmmode-time-box">
				<span class="mmmode-time-val" id="mmmode-secs">00</span>
				<span class="mmmode-time-label"><?php esc_html_e( 'Seconds', 'monster-maintenance-mode' ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Reusable Subscription Block.
	 *
	 * @param array $settings Plugin settings.
	 */
	private function render_subscribe_html( $settings ) {
		if ( empty( $settings['subscribe_enabled'] ) ) {
			return;
		}
		?>
		<div class="mmmode-subscribe-wrap">
			<?php if ( ! empty( $settings['subscribe_text'] ) ) : ?>
				<p class="mmmode-subscribe-title"><?php echo esc_html( $settings['subscribe_text'] ); ?></p>
			<?php endif; ?>
			<form class="mmmode-form" id="mmmode-subscribe-form">
				<input type="email" class="mmmode-input" id="mmmode-email" placeholder="<?php esc_attr_e( 'Enter your email address...', 'monster-maintenance-mode' ); ?>" required>
				<button type="submit" class="mmmode-btn" id="mmmode-submit"><?php esc_html_e( 'Notify Me', 'monster-maintenance-mode' ); ?></button>
			</form>
			<div class="mmmode-msg" id="mmmode-response"></div>
		</div>
		<?php
	}

	/**
	 * Render Reusable Social Links Block.
	 *
	 * @param array $settings Plugin settings.
	 */
	private function render_socials_html( $settings ) {
		$has_socials = ! empty( $settings['social_facebook'] ) || ! empty( $settings['social_twitter'] ) || ! empty( $settings['social_instagram'] ) || ! empty( $settings['social_linkedin'] ) || ! empty( $settings['contact_email'] );
		if ( ! $has_socials ) {
			return;
		}
		?>
		<div class="mmmode-socials">
			<?php if ( ! empty( $settings['social_facebook'] ) ) : ?>
				<a href="<?php echo esc_url( $settings['social_facebook'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Facebook', 'monster-maintenance-mode' ); ?></a>
			<?php endif; ?>
			<?php if ( ! empty( $settings['social_twitter'] ) ) : ?>
				<a href="<?php echo esc_url( $settings['social_twitter'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'X / Twitter', 'monster-maintenance-mode' ); ?></a>
			<?php endif; ?>
			<?php if ( ! empty( $settings['social_instagram'] ) ) : ?>
				<a href="<?php echo esc_url( $settings['social_instagram'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Instagram', 'monster-maintenance-mode' ); ?></a>
			<?php endif; ?>
			<?php if ( ! empty( $settings['social_linkedin'] ) ) : ?>
				<a href="<?php echo esc_url( $settings['social_linkedin'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'LinkedIn', 'monster-maintenance-mode' ); ?></a>
			<?php endif; ?>
			<?php if ( ! empty( $settings['contact_email'] ) ) : ?>
				<a href="mailto:<?php echo esc_attr( $settings['contact_email'] ); ?>"><?php esc_html_e( 'Contact Us', 'monster-maintenance-mode' ); ?></a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the Frontend Maintenance Page with Selected Template.
	 */
	public function render_maintenance_page() {
		if ( $this->should_bypass() ) {
			return;
		}

		$settings = $this->get_settings();

		// Allow ?mmmode_template=slug override when admin is previewing.
		$template   = $settings['template'];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_preview = isset( $_GET['mmmode_preview'] ) && current_user_can( 'manage_options' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $is_preview && isset( $_GET['mmmode_template'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$preview_tpl = sanitize_key( wp_unslash( $_GET['mmmode_template'] ) );
			if ( array_key_exists( $preview_tpl, $this->get_templates() ) ) {
				$template = $preview_tpl;
			}
		}

		// Send HTTP Status Header (200 OK Coming Soon vs 503 Maintenance).
		nocache_headers();
		$protocol = wp_get_server_protocol();
		if ( '503' === $settings['http_status'] && ! $is_preview ) {
			$retry_after = max( 60, absint( $settings['retry_after'] ) );
			header( "$protocol 503 Service Unavailable", true, 503 );
			header( "Retry-After: $retry_after" );
		} else {
			status_header( 200 );
		}

		// Determine template palette or user custom overrides.
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

				.mmmode-badge { display: inline-block; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; padding: 6px 14px; border-radius: 999px; margin-bottom: 20px; }
				h1 { font-size: clamp(2rem, 4.5vw, 3rem); font-weight: 800; margin-bottom: 18px; line-height: 1.15; }
				.mmmode-description { font-size: 1.125rem; opacity: 0.9; margin-bottom: 32px; }
				.mmmode-description p { margin-bottom: 12px; }
				.mmmode-description p:last-child { margin-bottom: 0; }
				.mmmode-countdown { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 34px; }
				.mmmode-time-box { padding: 18px 10px; text-align: center; }
				.mmmode-time-val { display: block; font-size: clamp(1.6rem, 3.5vw, 2.4rem); font-weight: 800; line-height: 1; margin-bottom: 6px; }
				.mmmode-time-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.75; }
				.mmmode-subscribe-wrap { margin-bottom: 28px; }
				.mmmode-subscribe-title { font-size: 0.95rem; margin-bottom: 12px; font-weight: 600; }
				.mmmode-form { display: flex; gap: 10px; width: 100%; }
				.mmmode-input { flex: 1; padding: 13px 16px; font-size: 1rem; outline: none; }
				.mmmode-btn { padding: 13px 26px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.2s ease; }
				.mmmode-msg { margin-top: 10px; font-size: 0.9rem; min-height: 1.4em; }
				.mmmode-socials { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 16px; }
				.mmmode-socials a { color: var(--text-color); text-decoration: none; font-size: 0.88rem; padding: 7px 14px; transition: all 0.2s ease; }

				/* TEMPLATE 1: MODERN GLASSMORPHISM */
				body.tpl-glass {
					<?php if ( empty( $settings['bg_image'] ) && empty( $settings['use_custom_colors'] ) ) : ?>
					background: radial-gradient(circle at top left, #1e3a8a, #0f172a 55%, #020617);
					<?php endif; ?>
					text-align: center;
				}
				.tpl-glass .mmmode-container { max-width: 680px; width: 100%; background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.14); border-radius: 24px; padding: 52px 40px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
				.tpl-glass .mmmode-badge { background: rgba(59, 130, 246, 0.2); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.4); }
				.tpl-glass .mmmode-time-box { background: rgba(0, 0, 0, 0.28); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 14px; }
				.tpl-glass .mmmode-time-val { color: var(--accent-color); }
				.tpl-glass .mmmode-form { max-width: 480px; margin: 0 auto; }
				.tpl-glass .mmmode-input { border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.2); background: rgba(0, 0, 0, 0.35); color: var(--text-color); }
				.tpl-glass .mmmode-btn { border-radius: 12px; border: none; background: var(--accent-color); color: #fff; }
				.tpl-glass .mmmode-socials { justify-content: center; }
				.tpl-glass .mmmode-socials a { border-radius: 8px; background: rgba(255, 255, 255, 0.07); }
				.tpl-glass .mmmode-socials a:hover { background: var(--accent-color); color: #fff; }

				/* TEMPLATE 2: CORPORATE SPLIT-SCREEN */
				.tpl-split .mmmode-container { max-width: 1120px; width: 100%; display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 48px; align-items: center; background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 24px; padding: 56px; box-shadow: 0 25px 60px rgba(0, 0, 0, 0.45); }
				.tpl-split .mmmode-badge { background: rgba(16, 185, 129, 0.15); color: var(--accent-color); border: 1px solid var(--accent-color); }
				.tpl-split .mmmode-right-panel { background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 18px; padding: 36px 30px; }
				.tpl-split .mmmode-time-box { background: rgba(0, 0, 0, 0.35); border-radius: 10px; border-bottom: 3px solid var(--accent-color); }
				.tpl-split .mmmode-time-val { color: var(--accent-color); }
				.tpl-split .mmmode-form { flex-direction: column; }
				.tpl-split .mmmode-input { border-radius: 10px; border: 1px solid rgba(255, 255, 255, 0.18); background: rgba(0, 0, 0, 0.4); color: var(--text-color); }
				.tpl-split .mmmode-btn { border-radius: 10px; border: none; background: var(--accent-color); color: #052e16; }
				.tpl-split .mmmode-socials a { border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 6px; }
				.tpl-split .mmmode-socials a:hover { border-color: var(--accent-color); color: var(--accent-color); }

				/* TEMPLATE 3: MINIMALIST EDITORIAL (SERIF) */
				body.tpl-editorial { font-family: "Times New Roman", Times, Georgia, serif; text-align: center; }
				.tpl-editorial .mmmode-container { max-width: 660px; width: 100%; background: #ffffff; color: #000000; border: 2px solid #000000; padding: 56px 44px; box-shadow: 0 12px 30px rgba(0, 0, 0, 0.06); }
				.tpl-editorial .mmmode-badge { border-radius: 0; border: 1px solid #000000; background: #000000; color: #ffffff; font-family: -apple-system, sans-serif; font-size: 0.7rem; }
				.tpl-editorial h1 { font-weight: 700; letter-spacing: -0.01em; border-bottom: 1px solid #000000; padding-bottom: 20px; margin-bottom: 24px; }
				.tpl-editorial .mmmode-description { font-size: 1.15rem; color: #18181b; }
				.tpl-editorial .mmmode-time-box { border: 1px solid #000000; background: #fafafa; }
				.tpl-editorial .mmmode-time-val { color: #000000; font-weight: 700; }
				.tpl-editorial .mmmode-time-label { font-family: -apple-system, sans-serif; color: #3f3f46; }
				.tpl-editorial .mmmode-form { max-width: 460px; margin: 0 auto; }
				.tpl-editorial .mmmode-input { border: 1px solid #000000; background: #ffffff; color: #000000; font-family: inherit; }
				.tpl-editorial .mmmode-btn { border: 1px solid #000000; background: #000000; color: #ffffff; font-family: -apple-system, sans-serif; text-transform: uppercase; letter-spacing: 0.08em; font-size: 0.85rem; }
				.tpl-editorial .mmmode-btn:hover { background: #ffffff; color: #000000; }
				.tpl-editorial .mmmode-socials { justify-content: center; border-top: 1px solid #e4e4e7; padding-top: 20px; }
				.tpl-editorial .mmmode-socials a { color: #000000; text-decoration: underline; font-style: italic; }

				/* TEMPLATE 4: CYBER TERMINAL (MATRIX) */
				body.tpl-terminal { font-family: "Courier New", Courier, Consolas, monospace; }
				.tpl-terminal .mmmode-container { max-width: 740px; width: 100%; background: rgba(8, 12, 8, 0.94); border: 1px solid var(--accent-color); border-radius: 8px; box-shadow: 0 0 35px rgba(34, 197, 94, 0.2); overflow: hidden; }
				.tpl-terminal .mmmode-term-bar { background: #111a11; border-bottom: 1px solid var(--accent-color); padding: 10px 16px; display: flex; align-items: center; gap: 8px; }
				.tpl-terminal .mmmode-term-dot { width: 11px; height: 11px; border-radius: 50%; display: inline-block; }
				.tpl-terminal .mmmode-term-title { margin-left: 10px; font-size: 0.8rem; opacity: 0.8; }
				.tpl-terminal .mmmode-term-body { padding: 36px 32px; }
				.tpl-terminal .mmmode-badge { border: 1px dashed var(--accent-color); border-radius: 0; color: var(--accent-color); }
				.tpl-terminal h1::before { content: "> "; opacity: 0.7; }
				.tpl-terminal .mmmode-time-box { border: 1px solid var(--accent-color); background: rgba(34, 197, 94, 0.05); }
				.tpl-terminal .mmmode-time-val { color: var(--accent-color); text-shadow: 0 0 10px rgba(34, 197, 94, 0.6); }
				.tpl-terminal .mmmode-input { background: #000000; border: 1px solid var(--accent-color); color: var(--accent-color); font-family: inherit; }
				.tpl-terminal .mmmode-btn { background: var(--accent-color); color: #000000; border: 1px solid var(--accent-color); font-family: inherit; text-transform: uppercase; }
				.tpl-terminal .mmmode-socials a { border: 1px solid rgba(34, 197, 94, 0.4); color: var(--accent-color); }
				.tpl-terminal .mmmode-socials a:hover { background: var(--accent-color); color: #000000; }

				/* TEMPLATE 5: NEON SYNTHWAVE GRID */
				body.tpl-neon {
					text-align: center;
					<?php if ( empty( $settings['bg_image'] ) && empty( $settings['use_custom_colors'] ) ) : ?>
					background: radial-gradient(circle at center, #240046 0%, #090014 80%);
					<?php endif; ?>
				}
				.tpl-neon .mmmode-cyber-grid { position: fixed; bottom: 0; left: -50%; width: 200%; height: 45vh; background-image: linear-gradient(to right, rgba(247, 37, 133, 0.35) 1px, transparent 1px), linear-gradient(to bottom, rgba(76, 201, 240, 0.35) 1px, transparent 1px); background-size: 40px 40px; transform: perspective(300px) rotateX(65deg); z-index: 0; pointer-events: none; }
				.tpl-neon .mmmode-container { position: relative; z-index: 2; max-width: 700px; width: 100%; background: rgba(15, 3, 38, 0.85); border: 2px solid var(--accent-color); box-shadow: 0 0 30px rgba(247, 37, 133, 0.4), inset 0 0 20px rgba(76, 201, 240, 0.2); border-radius: 16px; padding: 48px 36px; }
				.tpl-neon .mmmode-badge { background: transparent; border: 1px solid #4cc9f0; color: #4cc9f0; box-shadow: 0 0 12px rgba(76, 201, 240, 0.4); }
				.tpl-neon h1 { text-shadow: 0 0 18px var(--accent-color); }
				.tpl-neon .mmmode-time-box { background: rgba(0, 0, 0, 0.55); border: 1px solid #4cc9f0; border-radius: 10px; box-shadow: 0 0 15px rgba(76, 201, 240, 0.25); }
				.tpl-neon .mmmode-time-val { color: #4cc9f0; text-shadow: 0 0 10px #4cc9f0; }
				.tpl-neon .mmmode-form { max-width: 480px; margin: 0 auto; }
				.tpl-neon .mmmode-input { background: rgba(0, 0, 0, 0.6); border: 1px solid var(--accent-color); color: #ffffff; border-radius: 8px; }
				.tpl-neon .mmmode-btn { background: var(--accent-color); color: #ffffff; border: none; border-radius: 8px; box-shadow: 0 0 18px var(--accent-color); text-transform: uppercase; }
				.tpl-neon .mmmode-socials { justify-content: center; }
				.tpl-neon .mmmode-socials a { border: 1px solid rgba(255, 255, 255, 0.25); border-radius: 6px; }
				.tpl-neon .mmmode-socials a:hover { border-color: #4cc9f0; box-shadow: 0 0 12px #4cc9f0; }

				/* TEMPLATE 6: BOLD INDUSTRIAL BRUTALIST */
				.tpl-brutalist .mmmode-container { max-width: 680px; width: 100%; background: #ffffff; color: #000000; border: 4px solid #000000; box-shadow: 12px 12px 0px #000000; padding: 48px 40px; text-align: left; }
				.tpl-brutalist .mmmode-badge { background: #000000; color: #facc15; border-radius: 0; font-weight: 900; }
				.tpl-brutalist h1 { text-transform: uppercase; font-weight: 900; letter-spacing: -0.03em; }
				.tpl-brutalist .mmmode-time-box { background: var(--bg-color); border: 3px solid #000000; box-shadow: 5px 5px 0px #000000; }
				.tpl-brutalist .mmmode-time-val { color: #000000; font-weight: 900; }
				.tpl-brutalist .mmmode-time-label { color: #000000; font-weight: 700; opacity: 1; }
				.tpl-brutalist .mmmode-input { border: 3px solid #000000; background: #ffffff; color: #000000; font-weight: 600; }
				.tpl-brutalist .mmmode-btn { background: #000000; color: #ffffff; border: 3px solid #000000; text-transform: uppercase; font-weight: 900; box-shadow: 5px 5px 0px #000000; }
				.tpl-brutalist .mmmode-btn:hover { transform: translate(-2px, -2px); box-shadow: 7px 7px 0px #000000; }
				.tpl-brutalist .mmmode-socials a { background: #ffffff; color: #000000; border: 2px solid #000000; font-weight: 700; box-shadow: 3px 3px 0px #000000; }

				@media (max-width: 860px) {
					.tpl-split .mmmode-container { grid-template-columns: 1fr; padding: 36px 24px; gap: 28px; }
				}
				@media (max-width: 540px) {
					.mmmode-container { padding: 32px 20px !important; }
					.mmmode-form { flex-direction: column; }
					.mmmode-countdown { grid-template-columns: repeat(2, 1fr); }
				}
				<?php echo esc_html( wp_strip_all_tags( $settings['custom_css'] ) ); ?>
			</style>
		</head>
		<body class="tpl-<?php echo esc_attr( $template ); ?>">

			<?php if ( 'neon' === $template ) : ?>
				<div class="mmmode-cyber-grid"></div>
			<?php endif; ?>

			<?php if ( 'split' === $template ) : ?>
				<main class="mmmode-container">
					<div class="mmmode-left-panel">
						<?php if ( ! empty( $settings['badge_text'] ) ) : ?>
							<span class="mmmode-badge"><?php echo esc_html( $settings['badge_text'] ); ?></span>
						<?php endif; ?>
						<h1><?php echo esc_html( $settings['heading'] ); ?></h1>
						<div class="mmmode-description">
							<?php echo wp_kses_post( wpautop( $settings['content'] ) ); ?>
						</div>
						<?php $this->render_socials_html( $settings ); ?>
					</div>
					<div class="mmmode-right-panel">
						<?php $this->render_countdown_html( $settings, $end_timestamp ); ?>
						<?php $this->render_subscribe_html( $settings ); ?>
					</div>
				</main>

			<?php elseif ( 'terminal' === $template ) : ?>
				<main class="mmmode-container">
					<div class="mmmode-term-bar">
						<span class="mmmode-term-dot" style="background:#ef4444;"></span>
						<span class="mmmode-term-dot" style="background:#f59e0b;"></span>
						<span class="mmmode-term-dot" style="background:#22c55e;"></span>
						<span class="mmmode-term-title">root@<?php echo esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>: ~/maintenance_status.sh</span>
					</div>
					<div class="mmmode-term-body">
						<?php if ( ! empty( $settings['badge_text'] ) ) : ?>
							<span class="mmmode-badge">[<?php echo esc_html( $settings['badge_text'] ); ?>]</span>
						<?php endif; ?>
						<h1><?php echo esc_html( $settings['heading'] ); ?></h1>
						<div class="mmmode-description">
							<?php echo wp_kses_post( wpautop( $settings['content'] ) ); ?>
						</div>
						<?php $this->render_countdown_html( $settings, $end_timestamp ); ?>
						<?php $this->render_subscribe_html( $settings ); ?>
						<?php $this->render_socials_html( $settings ); ?>
					</div>
				</main>

			<?php else : ?>
				<main class="mmmode-container">
					<?php if ( ! empty( $settings['badge_text'] ) ) : ?>
						<span class="mmmode-badge"><?php echo esc_html( $settings['badge_text'] ); ?></span>
					<?php endif; ?>
					<h1><?php echo esc_html( $settings['heading'] ); ?></h1>
					<div class="mmmode-description">
						<?php echo wp_kses_post( wpautop( $settings['content'] ) ); ?>
					</div>
					<?php $this->render_countdown_html( $settings, $end_timestamp ); ?>
					<?php $this->render_subscribe_html( $settings ); ?>
					<?php $this->render_socials_html( $settings ); ?>
				</main>
			<?php endif; ?>

			<script>
				(function() {
					const countdownEl = document.getElementById('mmmode-countdown');
					if (countdownEl) {
						const endTime = parseInt(countdownEl.getAttribute('data-end'), 10);
						const updateTimer = () => {
							const now = new Date().getTime();
							const diff = endTime - now;
							if (diff <= 0) {
								document.getElementById('mmmode-days').textContent = '00';
								document.getElementById('mmmode-hours').textContent = '00';
								document.getElementById('mmmode-mins').textContent = '00';
								document.getElementById('mmmode-secs').textContent = '00';
								<?php if ( ! empty( $settings['auto_disable'] ) && ! $is_preview ) : ?>
								window.location.reload();
								<?php endif; ?>
								return;
							}
							const d = Math.floor(diff / (1000 * 60 * 60 * 24));
							const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
							const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
							const s = Math.floor((diff % (1000 * 60)) / 1000);

							document.getElementById('mmmode-days').textContent = String(d).padStart(2, '0');
							document.getElementById('mmmode-hours').textContent = String(h).padStart(2, '0');
							document.getElementById('mmmode-mins').textContent = String(m).padStart(2, '0');
							document.getElementById('mmmode-secs').textContent = String(s).padStart(2, '0');
						};
						updateTimer();
						setInterval(updateTimer, 1000);
					}

					const form = document.getElementById('mmmode-subscribe-form');
					if (form) {
						form.addEventListener('submit', function(e) {
							e.preventDefault();
							const emailInput = document.getElementById('mmmode-email');
							const submitBtn = document.getElementById('mmmode-submit');
							const responseEl = document.getElementById('mmmode-response');

							submitBtn.disabled = true;
							submitBtn.textContent = '<?php echo esc_js( __( 'Saving...', 'monster-maintenance-mode' ) ); ?>';
							responseEl.textContent = '';

							const formData = new URLSearchParams();
							formData.append('action', 'mmmode_subscribe');
							formData.append('nonce', '<?php echo esc_js( wp_create_nonce( 'mmmode_subscribe_nonce' ) ); ?>');
							formData.append('email', emailInput.value);

							fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
								method: 'POST',
								headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
								body: formData.toString()
							})
							.then(res => res.json())
							.then(data => {
								submitBtn.disabled = false;
								submitBtn.textContent = '<?php echo esc_js( __( 'Notify Me', 'monster-maintenance-mode' ) ); ?>';
								if (data.success) {
									responseEl.style.color = '#10b981';
									responseEl.textContent = data.data.message;
									emailInput.value = '';
								} else {
									responseEl.style.color = '#ef4444';
									responseEl.textContent = data.data ? data.data.message : '<?php echo esc_js( __( 'An error occurred.', 'monster-maintenance-mode' ) ); ?>';
								}
							})
							.catch(() => {
								submitBtn.disabled = false;
								submitBtn.textContent = '<?php echo esc_js( __( 'Notify Me', 'monster-maintenance-mode' ) ); ?>';
								responseEl.style.color = '#ef4444';
								responseEl.textContent = '<?php echo esc_js( __( 'Connection error. Please try again.', 'monster-maintenance-mode' ) ); ?>';
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
	 * Render Admin Settings Dashboard.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$settings         = $this->get_settings();
		$templates        = $this->get_templates();
		$subscriber_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mmmode_subscribers" );
		$editable_roles   = get_editable_roles();
		$preview_url      = add_query_arg( 'mmmode_preview', '1', home_url( '/' ) );
		$current_ip       = $this->get_client_ip();
		?>
		<div class="wrap">
			<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
				<h1 style="margin:0;"><?php esc_html_e( 'Monster Maintenance Mode — Admin Dashboard', 'monster-maintenance-mode' ); ?></h1>
				<a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" class="button button-primary button-large"><?php esc_html_e( 'Preview Active Template', 'monster-maintenance-mode' ); ?> &#8599;</a>
			</div>

			<?php
			if ( isset( $_GET['mmmode_cleared'], $_GET['_mmmode_notice'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_mmmode_notice'] ) ), 'mmmode_cleared_notice' ) ) :
				?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'All subscribers have been cleared.', 'monster-maintenance-mode' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'mmmode_settings_group' ); ?>

				<div style="background:#fff; padding:20px 25px; border:1px solid #ccd0d4; border-radius:6px; margin-top:20px;">
					<h2><?php esc_html_e( '1. Status & HTTP Response Mode', 'monster-maintenance-mode' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Activate Maintenance Page', 'monster-maintenance-mode' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[status]" value="1" <?php checked( 1, $settings['status'] ); ?>>
									<strong><?php esc_html_e( 'Enable Maintenance / Coming Soon Page', 'monster-maintenance-mode' ); ?></strong>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'HTTP Header Mode', 'monster-maintenance-mode' ); ?></th>
							<td>
								<fieldset>
									<label style="display:block; margin-bottom:8px;">
										<input type="radio" name="<?php echo esc_attr( $this->option_name ); ?>[http_status]" value="200" <?php checked( '200', $settings['http_status'] ); ?>>
										<strong><?php esc_html_e( '200 OK (Coming Soon / Custom Template Mode)', 'monster-maintenance-mode' ); ?></strong> &mdash; <?php esc_html_e( 'Renders your chosen template as a normal webpage without triggering proxy or browser 503 error screens.', 'monster-maintenance-mode' ); ?>
									</label>
									<label style="display:block;">
										<input type="radio" name="<?php echo esc_attr( $this->option_name ); ?>[http_status]" value="503" <?php checked( '503', $settings['http_status'] ); ?>>
										<strong><?php esc_html_e( '503 Service Unavailable (Temporary SEO Maintenance)', 'monster-maintenance-mode' ); ?></strong> &mdash; <?php esc_html_e( 'Displays your chosen template while telling search engines to check back after the configured Retry-After interval.', 'monster-maintenance-mode' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>
					</table>

					<hr>
					<h2><?php esc_html_e( '2. External IP Bypass & Direct File Reading Manager', 'monster-maintenance-mode' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Add external IP addresses below so they completely bypass Maintenance Mode, and configure which file types and folders can be read directly.', 'monster-maintenance-mode' ); ?></p>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'External IP Bypass Manager', 'monster-maintenance-mode' ); ?></th>
							<td>
								<div class="mmmode-ip-manager-box">
									<div class="mmmode-ip-add-bar">
										<input type="text" id="mmmode_new_ip" placeholder="e.g. 203.0.113.50 or 198.51.100.0/24" class="regular-text code" style="width:260px;">
										<input type="text" id="mmmode_new_ip_label" placeholder="<?php esc_attr_e( 'Optional Label (e.g. Client Office / VPN)', 'monster-maintenance-mode' ); ?>" class="regular-text" style="width:240px;">
										<button type="button" class="button button-primary" id="mmmode_add_ip_btn"><?php esc_html_e( '+ Add IP to List', 'monster-maintenance-mode' ); ?></button>
										<button type="button" class="button" id="mmmode_add_current_ip_btn" data-ip="<?php echo esc_attr( $current_ip ); ?>"><?php esc_html_e( '+ Add My Current IP', 'monster-maintenance-mode' ); ?> (<?php echo esc_html( $current_ip ); ?>)</button>
									</div>

									<table class="mmmode-ip-table" id="mmmode_ip_table">
										<thead>
											<tr>
												<th style="width:45%;"><?php esc_html_e( 'External IP / Subnet / Wildcard', 'monster-maintenance-mode' ); ?></th>
												<th style="width:40%;"><?php esc_html_e( 'Label / Note', 'monster-maintenance-mode' ); ?></th>
												<th style="width:15%; text-align:right;"><?php esc_html_e( 'Action', 'monster-maintenance-mode' ); ?></th>
											</tr>
										</thead>
										<tbody id="mmmode_ip_tbody"></tbody>
									</table>

									<p style="margin-top:10px; font-size:12px; color:#475569;">
										<a href="#" id="mmmode_toggle_raw_ips" style="text-decoration:none;">&#9881; <?php esc_html_e( 'Toggle Raw Bulk IP Editor', 'monster-maintenance-mode' ); ?></a>
									</p>
									<div id="mmmode_raw_ip_wrap" style="display:none; margin-top:8px;">
										<textarea id="mmmode_whitelisted_ips" name="<?php echo esc_attr( $this->option_name ); ?>[whitelisted_ips]" rows="4" class="large-text code" placeholder="One IP per line, e.g. 203.0.113.5 # Client"><?php echo esc_textarea( $settings['whitelisted_ips'] ); ?></textarea>
									</div>
								</div>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Allow Direct File Reading', 'monster-maintenance-mode' ); ?></th>
							<td>
								<label style="display:block; margin-bottom:10px;">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[allow_file_reading]" value="1" <?php checked( 1, $settings['allow_file_reading'] ); ?>>
									<strong><?php esc_html_e( 'Allow reading & downloading of files while Maintenance Mode is active', 'monster-maintenance-mode' ); ?></strong>
								</label>
								<p style="margin-bottom:4px; font-weight:600;"><?php esc_html_e( 'Readable File Extensions (comma-separated):', 'monster-maintenance-mode' ); ?></p>
								<input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[allowed_file_extensions]" value="<?php echo esc_attr( $settings['allowed_file_extensions'] ); ?>" class="large-text code">
								<p class="description"><?php esc_html_e( 'Any direct request for a file ending in these extensions (e.g., .pdf, .zip, .json, .txt, .csv, .jpg) will be readable without triggering the maintenance screen.', 'monster-maintenance-mode' ); ?></p>
								<label style="display:block; margin-top:10px;">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[allow_wp_attachments]" value="1" <?php checked( 1, $settings['allow_wp_attachments'] ); ?>>
									<?php esc_html_e( 'Also allow WordPress Media Library attachment pages & files to be read', 'monster-maintenance-mode' ); ?>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Excluded Folders & URL Paths', 'monster-maintenance-mode' ); ?></th>
							<td>
								<textarea name="<?php echo esc_attr( $this->option_name ); ?>[exclude_urls]" rows="4" class="large-text code"><?php echo esc_textarea( $settings['exclude_urls'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'One path or folder per line (e.g. /wp-content/uploads/, /downloads/, /custom-folder/). Any file or page inside these paths can be read freely.', 'monster-maintenance-mode' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Bypass User Roles', 'monster-maintenance-mode' ); ?></th>
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
							<th scope="row"><?php esc_html_e( 'Bot & REST API Options', 'monster-maintenance-mode' ); ?></th>
							<td>
								<label style="display:block; margin-bottom:8px;">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[bypass_bots]" value="1" <?php checked( 1, $settings['bypass_bots'] ); ?>>
									<?php esc_html_e( 'Allow Search Engine Bots (Googlebot, Bingbot) to bypass maintenance page', 'monster-maintenance-mode' ); ?>
								</label>
								<label style="display:block;">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[disable_rest]" value="1" <?php checked( 1, $settings['disable_rest'] ); ?>>
									<?php esc_html_e( 'Block WordPress REST API for non-whitelisted visitors', 'monster-maintenance-mode' ); ?>
								</label>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save All Maintenance Settings', 'monster-maintenance-mode' ) ); ?>

					<hr>
					<h2><?php esc_html_e( '3. Select a Maintenance Template', 'monster-maintenance-mode' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Choose from 6 pre-built visual layouts. Click "Quick Preview" on any card to inspect it live in a new tab.', 'monster-maintenance-mode' ); ?></p>

					<div class="mmmode-tpl-grid">
						<?php foreach ( $templates as $tpl_key => $tpl_data ) : ?>
							<?php
							$is_active       = ( $settings['template'] === $tpl_key );
							$tpl_preview_url = add_query_arg(
								array(
									'mmmode_preview'  => '1',
									'mmmode_template' => $tpl_key,
								),
								home_url( '/' )
							);
							?>
							<label class="mmmode-tpl-card <?php echo $is_active ? 'active' : ''; ?>">
								<div>
									<div class="mmmode-tpl-thumb" style="background: <?php echo esc_attr( $tpl_data['preview_bg'] ); ?>;">
										<div class="mmmode-tpl-thumb-bar" style="border-color: <?php echo esc_attr( $tpl_data['accent'] ); ?>;">
											<?php echo esc_html( strtoupper( $tpl_key ) ); ?>
										</div>
									</div>
									<div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
										<input type="radio" class="mmmode-tpl-radio" name="<?php echo esc_attr( $this->option_name ); ?>[template]" value="<?php echo esc_attr( $tpl_key ); ?>" <?php checked( $settings['template'], $tpl_key ); ?>>
										<strong style="font-size:14px;"><?php echo esc_html( $tpl_data['name'] ); ?></strong>
									</div>
									<p style="font-size:12px; color:#50575e; margin:0 0 12px 0;"><?php echo esc_html( $tpl_data['description'] ); ?></p>
								</div>
								<div>
									<a href="<?php echo esc_url( $tpl_preview_url ); ?>" target="_blank" class="button button-small"><?php esc_html_e( 'Quick Preview', 'monster-maintenance-mode' ); ?> &#8599;</a>
								</div>
							</label>
						<?php endforeach; ?>
					</div>

					<hr style="margin-top:28px;">
					<h2><?php esc_html_e( '4. Content & Custom Styling', 'monster-maintenance-mode' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Browser Page Title', 'monster-maintenance-mode' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[page_title]" value="<?php echo esc_attr( $settings['page_title'] ); ?>" class="regular-text">
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Status Badge Text', 'monster-maintenance-mode' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[badge_text]" value="<?php echo esc_attr( $settings['badge_text'] ); ?>" class="regular-text" placeholder="SCHEDULED UPDATE">
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Main Heading', 'monster-maintenance-mode' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[heading]" value="<?php echo esc_attr( $settings['heading'] ); ?>" class="large-text">
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Message Content', 'monster-maintenance-mode' ); ?></th>
							<td>
								<?php
								wp_editor(
									$settings['content'],
									'mmmode_content_editor',
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
							<th scope="row"><?php esc_html_e( 'Template Color Override', 'monster-maintenance-mode' ); ?></th>
							<td>
								<label style="display:block; margin-bottom:14px;">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[use_custom_colors]" value="1" <?php checked( 1, $settings['use_custom_colors'] ); ?>>
									<?php esc_html_e( 'Override the selected template\'s default color palette with custom colors below', 'monster-maintenance-mode' ); ?>
								</label>
								<div style="display:flex; gap:24px; flex-wrap:wrap;">
									<div>
										<label style="display:block; margin-bottom:4px; font-weight:600;"><?php esc_html_e( 'Background Color', 'monster-maintenance-mode' ); ?></label>
										<input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[bg_color]" value="<?php echo esc_attr( $settings['bg_color'] ); ?>" class="mmmode-color-picker">
									</div>
									<div>
										<label style="display:block; margin-bottom:4px; font-weight:600;"><?php esc_html_e( 'Text Color', 'monster-maintenance-mode' ); ?></label>
										<input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[text_color]" value="<?php echo esc_attr( $settings['text_color'] ); ?>" class="mmmode-color-picker">
									</div>
									<div>
										<label style="display:block; margin-bottom:4px; font-weight:600;"><?php esc_html_e( 'Accent / Button Color', 'monster-maintenance-mode' ); ?></label>
										<input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[accent_color]" value="<?php echo esc_attr( $settings['accent_color'] ); ?>" class="mmmode-color-picker">
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Custom Background Image', 'monster-maintenance-mode' ); ?></th>
							<td>
								<input type="url" id="mmmode_bg_image" name="<?php echo esc_attr( $this->option_name ); ?>[bg_image]" value="<?php echo esc_url( $settings['bg_image'] ); ?>" class="regular-text">
								<button type="button" class="button" id="mmmode_upload_btn"><?php esc_html_e( 'Select Image', 'monster-maintenance-mode' ); ?></button>
								<p class="description"><?php esc_html_e( 'Optional fullscreen background image (applies a dark overlay automatically).', 'monster-maintenance-mode' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Custom CSS', 'monster-maintenance-mode' ); ?></th>
							<td>
								<textarea name="<?php echo esc_attr( $this->option_name ); ?>[custom_css]" rows="3" class="large-text code" placeholder=".mmmode-container { border-radius: 0; }"><?php echo esc_textarea( $settings['custom_css'] ); ?></textarea>
							</td>
						</tr>
					</table>

					<hr>
					<h2><?php esc_html_e( '5. Countdown, Subscriptions & Social Links', 'monster-maintenance-mode' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Countdown Timer', 'monster-maintenance-mode' ); ?></th>
							<td>
								<label style="display:block; margin-bottom:10px;">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[countdown_enabled]" value="1" <?php checked( 1, $settings['countdown_enabled'] ); ?>>
									<?php esc_html_e( 'Show Countdown Timer', 'monster-maintenance-mode' ); ?>
								</label>
								<label style="display:block; margin-bottom:10px;">
									<?php esc_html_e( 'Launch Date & Time:', 'monster-maintenance-mode' ); ?>
									<input type="datetime-local" name="<?php echo esc_attr( $this->option_name ); ?>[countdown_end]" value="<?php echo esc_attr( $settings['countdown_end'] ); ?>">
								</label>
								<label style="display:block;">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[auto_disable]" value="1" <?php checked( 1, $settings['auto_disable'] ); ?>>
									<?php esc_html_e( 'Automatically disable Maintenance Mode when countdown reaches zero', 'monster-maintenance-mode' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Email Lead Capture', 'monster-maintenance-mode' ); ?></th>
							<td>
								<label style="display:block; margin-bottom:10px;">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[subscribe_enabled]" value="1" <?php checked( 1, $settings['subscribe_enabled'] ); ?>>
									<?php esc_html_e( 'Enable Email Subscription Form', 'monster-maintenance-mode' ); ?>
								</label>
								<input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[subscribe_text]" value="<?php echo esc_attr( $settings['subscribe_text'] ); ?>" class="regular-text" placeholder="Get notified when we go live:">
								<div style="margin-top:12px; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; display:inline-block;">
									<strong><?php esc_html_e( 'Total Subscribers:', 'monster-maintenance-mode' ); ?> <?php echo esc_html( (string) $subscriber_count ); ?></strong>
									<?php if ( $subscriber_count > 0 ) : ?>
										&nbsp;|&nbsp;
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'options-general.php?page=monster-maintenance-mode&mmmode_action=export_csv' ), 'mmmode_export_csv_nonce' ) ); ?>" class="button button-small"><?php esc_html_e( 'Export CSV', 'monster-maintenance-mode' ); ?></a>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'options-general.php?page=monster-maintenance-mode&mmmode_action=clear_subscribers' ), 'mmmode_clear_subscribers_nonce' ) ); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'Delete all subscribers permanently?', 'monster-maintenance-mode' ) ); ?>');" style="color:#b91c1c;"><?php esc_html_e( 'Clear List', 'monster-maintenance-mode' ); ?></a>
									<?php endif; ?>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Social & Contact Links', 'monster-maintenance-mode' ); ?></th>
							<td>
								<p style="margin-bottom:8px;"><input type="url" name="<?php echo esc_attr( $this->option_name ); ?>[social_facebook]" value="<?php echo esc_url( $settings['social_facebook'] ); ?>" class="regular-text" placeholder="https://facebook.com/..."></p>
								<p style="margin-bottom:8px;"><input type="url" name="<?php echo esc_attr( $this->option_name ); ?>[social_twitter]" value="<?php echo esc_url( $settings['social_twitter'] ); ?>" class="regular-text" placeholder="https://x.com/..."></p>
								<p style="margin-bottom:8px;"><input type="url" name="<?php echo esc_attr( $this->option_name ); ?>[social_instagram]" value="<?php echo esc_url( $settings['social_instagram'] ); ?>" class="regular-text" placeholder="https://instagram.com/..."></p>
								<p style="margin-bottom:8px;"><input type="url" name="<?php echo esc_attr( $this->option_name ); ?>[social_linkedin]" value="<?php echo esc_url( $settings['social_linkedin'] ); ?>" class="regular-text" placeholder="https://linkedin.com/..."></p>
								<p><input type="email" name="<?php echo esc_attr( $this->option_name ); ?>[contact_email]" value="<?php echo esc_attr( $settings['contact_email'] ); ?>" class="regular-text" placeholder="contact@yourdomain.com"></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'SEO Retry-After Header (Seconds)', 'monster-maintenance-mode' ); ?></th>
							<td>
								<input type="number" name="<?php echo esc_attr( $this->option_name ); ?>[retry_after]" value="<?php echo esc_attr( $settings['retry_after'] ); ?>" class="small-text" min="60">
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save All Maintenance Settings', 'monster-maintenance-mode' ) ); ?>
				</div>
			</form>
		</div>
		<?php
	}
}

new Monster_Maintenance_Mode();

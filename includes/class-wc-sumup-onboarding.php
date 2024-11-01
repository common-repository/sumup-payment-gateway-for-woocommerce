<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to manage onboarding connection
 */
class WC_Sumup_Onboarding {
	/**
	 * Undocumented variable
	 *
	 * @var string
	 */
	public $plugin_type = 'WOOCOMMERCE_V1';

	/**
	 * Undocumented variable
	 *
	 * @var string
	 */
	public $website_url;

	/**
	 * Undocumented variable
	 *
	 * @var string
	 */
	public $business_name;

	/**
	 * Construct.
	 */
	public function __construct() {
		$this->website_url = untrailingslashit( get_bloginfo( 'url' ) );
		$this->business_name = get_bloginfo( 'name' );
		\add_filter( 'woocommerce_settings_checkout', array( $this, 'onboarding_template' ) );
	}

	/**
	 * Init ajax request
	 *
	 * @return void
	 */
	public function init_ajax_request() {
		add_action( 'wp_ajax_sumup_connect', array( $this, 'sumup_connect' ) );
	}

	/**
	 * Sumup connect
	 *
	 * @return void
	 */
	public function sumup_connect() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'sumup-settings-nonce' ) ) {
			exit( 'Sorry, request not authorized' );
		}

		$response = $this->request_connection();
		$connection_id = json_decode( $response, true )[ 'id' ];
		set_transient( 'sumup-connection-id-' . $connection_id, $connection_id, 7200 );
		echo $response;
		die();
	}

	/**
	 * Request connection
	 *
	 * @return object
	 */
	public function request_connection()
	{
		$data = array(
			'plugin_type' => 'WOOCOMMERCE_V1',
			'plugin_version' => WC_SUMUP_VERSION,
			'website' => $this->website_url,
			'business_data' => array(
				'business_name' => $this->business_name,
			),
		);

		$data = json_encode($data, JSON_UNESCAPED_SLASHES);

		$ch = curl_init();
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_URL => 'https://op-plugin-onboarding.op-live-eks-eu-west-1.sam-app.ro/v1/connections', //https://op-plugin-onboarding.op-dev-eks-eu-west-1.sam-app.ro/v1/connections
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $data,
				CURLOPT_HTTPHEADER => array(
					'Idempotency-Key: ' . time(),
					'Content-Type: application/json',
				),
			)
		);

		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
	}

	/**
	 * Onboarding template
	 *
	 * @return void
	 */
	public function onboarding_template() {
		if ( empty( $_GET[ 'section' ] ) || 'sumup' !== $_GET[ 'section' ] ) {
			return;
		}

		$is_valid_onboarding_settings = true;

		wp_enqueue_script( 'sumup-settings' );
		wp_enqueue_style( 'sumup-settings' );

		/**
		 * Validate sumup account/connection after redirect from SumUp integrations page
		 */
		if ( ! empty( $_GET[ 'validate_settings' ] ) && $_GET[ 'validate_settings' ] === 'true' ) {
			$is_valid_onboarding_settings = Wc_Sumup_Credentials::validate();
			if ( $is_valid_onboarding_settings ) {
				include_once WC_SUMUP_PLUGIN_PATH . '/templates/onboarding-success-message.php';
			} else {
				include_once WC_SUMUP_PLUGIN_PATH . '/templates/onboarding-failed-message.php';
			}
		}

		/**
		 * Check if important settings already filled out.
		 *
		 * [] If is connected and DO NOT HAVE API Key show to connect? Old clients
		 */
		$sumup_settings = get_option( 'woocommerce_sumup_settings' );
		$is_integrations_settings_filled = ! empty( $sumup_settings['api_key'] ) || ( ! empty( $sumup_settings['client_id'] ) && ! empty( $sumup_settings['client_secret'] ) );
		if ( $is_integrations_settings_filled && $is_valid_onboarding_settings ) {
			return;
		}

		include_once WC_SUMUP_PLUGIN_PATH . '/templates/onboarding.php';
	}
}

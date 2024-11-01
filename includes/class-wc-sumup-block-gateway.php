<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Sumup_Blocks_Support extends AbstractPaymentMethodType
{

	private $gateway;

	protected $name = 'sumup'; // payment gateway id

	public function initialize()
	{
		// get payment gateway settings
		$this->settings = get_option("woocommerce_{$this->name}_settings", array());

		// you can also initialize your payment gateway here
		$gateways = WC()->payment_gateways->payment_gateways();
		$this->gateway = $gateways[$this->name];


	}

	public function is_active()
	{
		return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
	}

	public function get_payment_method_script_handles()
	{

		$shop_base_country = WC()->countries->get_base_country();
		$supported_countries = array(
			"AT",
			"AU",
			"BE",
			"BG",
			"BR",
			"CH",
			"CL",
			"CO",
			"CY",
			"CZ",
			"DE",
			"DK",
			"EE",
			"ES",
			"FI",
			"FR",
			"GB",
			"GR",
			"HR",
			"HU",
			"IE",
			"IT",
			"LT",
			"LU",
			"LV",
			"MT",
			"NL",
			"NO",
			"PL",
			"PT",
			"RO",
			"SE",
			"SI",
			"SK",
			"US",
		);
		$card_country = in_array($shop_base_country, $supported_countries) ? $shop_base_country : 'null';

		$show_zipcode = $card_country === 'US' ? 'true' : 'false';

		$card_locale = str_replace('_', '-', get_locale());
		$card_supported_locales = array(
			"bg-BG",
			"cs-CZ",
			"da-DK",
			"de-AT",
			"de-CH",
			"de-DE",
			"de-LU",
			"el-CY",
			"el-GR",
			"en-AU",
			"en-GB",
			"en-IE",
			"en-MT",
			"en-US",
			"es-CL",
			"es-ES",
			"et-EE",
			"fi-FI",
			"fr-BE",
			"fr-CH",
			"fr-FR",
			"fr-LU",
			"hu-HU",
			"it-CH",
			"it-IT",
			"lt-LT",
			"lv-LV",
			"nb-NO",
			"nl-BE",
			"nl-NL",
			"pt-BR",
			"pt-PT",
			"pl-PL",
			"sk-SK",
			"sl-SI",
			"sv-SE",
		);
		$card_locale = in_array($card_locale, $card_supported_locales) ? $card_locale : 'en-GB';

		/**
		 * Translators: the following error messages are shown to the end user
		 */
		$show_installments = 'false';
		$number_of_installments = null;
		if ($card_country === "BR") {
			$show_installments = isset($this->settings["enable_installments"]) && $this->settings["enable_installments"] === "yes" ? 'true' : 'false';
			$number_of_installments = isset($this->settings["number_of_installments"]) && $this->settings["number_of_installments"] !== false && $this->settings["number_of_installments"] !== 'select' ? $this->settings["number_of_installments"] : null;
		}

		if (is_checkout()) {

			/*
			 * Use the SumUp's SDK for accepting card payments.
			 * Documentation can be found at https://developer.sumup.com/docs/widgets-card
			 */
			wp_enqueue_script('sumup_gateway_card_sdk', 'https://gateway.sumup.com/gateway/ecom/card/v2/sdk.js', array(), WC_SUMUP_VERSION, false);
			wp_register_script(
				'wc-sumup-blocks-integration',
				plugin_dir_url(__DIR__) . 'build/index.js',
				array(
					'wc-blocks-registry',
					'wc-settings',
					'wp-element',
					'wp-html-entities',
					'sumup_gateway_card_sdk'
				),
				null, // or time() or filemtime( ... ) to skip caching
				true
			);

			wp_register_style("wc_sumup_checkout", plugin_dir_url(__DIR__) . 'build/index.css', array(), WC_SUMUP_VERSION);
		}
		/**
		 * Translators: the following error messages are shown to the end user
		 */
		$error_general = __('Transaction was unsuccessful. Please check the minimum amount or use another valid card.', 'sumup-payment-gateway-for-woocommerce');
		$error_invalid_form = __('Fill in all required details.', 'sumup-payment-gateway-for-woocommerce');

		wp_localize_script('wc-sumup-blocks-integration', 'sumup_gateway_params', array(

			'showInstallments' => $show_installments,
			'sumup_handler_url' => add_query_arg(
				array(
					'wc-api' => 'sumup_api_handler',
					'action' => 'create_checkout'
				),
				home_url() . '/'
			),
			'showZipCode' => "$show_zipcode",
			'maxInstallments' => isset($number_of_installments) ? $number_of_installments : 0,
			'locale' => "$card_locale",
			'country' => '',
			'status' => '',
			'errors' => array(
				'general_error' => "$error_general",
				'invalid_form' => "$error_invalid_form",
				'payment_error' => ""
			),
			'enablePix' => isset($this->settings["enable_pix"]) ? $this->settings["enable_pix"] : 'no',
			'openPaymentInModal' => isset($this->settings["open_payment_modal"]) ? $this->settings['open_payment_modal'] : 'no',
			'redirectUrl' => '',
		));


		return array('wc-sumup-blocks-integration');

	}

	public function get_payment_method_data()
	{
		return array(
			'title' => $this->get_setting('title'),
			'description' => $this->get_setting('description'),
			'supports' => array_filter($this->gateway->supports, [$this->gateway, 'supports'])
		);
	}

}

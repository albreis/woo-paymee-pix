<?php
/**
 * WooCommerce PayMee Gateway class
 *
 * @package Woo_PayMee/Classes/Gateway
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce PayMee gateway.
 */
class WC_Woo_PayMee_Pix_Gateway extends WC_Payment_Gateway {
	
	/**
	 * Constructor for the gateway.
	 */
	public function __construct() 
	{
		$this->id                 = 'paymee_pix';
		$this->icon               = apply_filters( 'woocommerce_paymee_pix_icon', plugins_url( 'assets/images/icon-pix.png?v2', plugin_dir_path( __FILE__ ) ) );
		$this->method_title       = __( 'PayMee Pix', 'woocommerce-paymee-pix' );
		$this->method_description = __( 'Aceite transferência ou dinheiro instantaneamente com a PayMee.', 'woocommerce-paymee' );

		$this->init_form_fields();

		$this->init_settings();

		$this->title             = $this->get_option( 'title' );
		$this->description       = $this->get_option( 'description' );
		$this->api_key           = $this->get_option( 'api_key' );
		$this->api_token         = $this->get_option( 'api_token' );
		$this->sandbox_api_key   = $this->get_option( 'api_key' );
		$this->sandbox_api_token = $this->get_option( 'api_token' );
		$this->method            = $this->get_option( 'method', 'direct' );
		$this->tc_transfer       = $this->get_option( 'tc_transfer', 'yes' );
		$this->tc_cash       	 = $this->get_option( 'tc_cash', 'yes' );
		$this->send_only_total   = $this->get_option( 'send_only_total', 'no' );
		$this->invoice_prefix    = $this->get_option( 'invoice_prefix', 'WC-' );
		$this->sandbox           = $this->get_option( 'sandbox', 'no' );
		$this->debug             = $this->get_option( 'debug' );
		$this->order_button_text = __( $this->get_option( 'texto_botao' ) , 'woocommerce-paymee' );

		if(is_admin()) 
		{
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}
		
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
      
        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

		if ( 'yes' == $this->debug ) 
		{
			if ( function_exists( 'wc_get_logger' ) ) 
			{
				$this->log = wc_get_logger();
			} else {
				$this->log = new WC_Logger();
			}
		}
		
		$this->api = new WC_Woo_PayMee_Pix_API( $this );
		add_action('woocommerce_api_paymee_pix_ipn_listener', array($this, 'paymee_pix_ipn_listener'));
	}

	public function thankyou_page($order_id) 
	{
		$order = new WC_Order($order_id);
		$paymee = $order->get_meta('paymee_pix_info');
		$timer = (strtotime($order->order_date) + (60 * 60 * 4));
		$valid = ($timer > time());
		if(isset($_GET['forceblock'])) {
			$valid = false;
		}
		?>
		
		<script src="<?php echo plugin_dir_url( __DIR__ ); ?>assets/clipboard.min.js"></script>
		<script src="<?php echo plugin_dir_url( __DIR__ ); ?>assets/jquery.countdown.min.js"></script>

		<div class="instrus">
			<?php if (in_array($order->get_status(), ['processing', 'complete'])): ?>
				<h2 style="display:flex;align-items:center;justify-content:center;font-size:60px;width:100px;height:100px;display:block;line-height:100px;text-align:center;margin: 30px auto;color: #ddd;border:4px solid #ddd;border-radius:100px;">
					<svg style="transform:scale(0.7);position:relative;top:-4px;" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="thumbs-up" class="svg-inline--fa fa-thumbs-up fa-w-16" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M104 224H24c-13.255 0-24 10.745-24 24v240c0 13.255 10.745 24 24 24h80c13.255 0 24-10.745 24-24V248c0-13.255-10.745-24-24-24zM64 472c-13.255 0-24-10.745-24-24s10.745-24 24-24 24 10.745 24 24-10.745 24-24 24zM384 81.452c0 42.416-25.97 66.208-33.277 94.548h101.723c33.397 0 59.397 27.746 59.553 58.098.084 17.938-7.546 37.249-19.439 49.197l-.11.11c9.836 23.337 8.237 56.037-9.308 79.469 8.681 25.895-.069 57.704-16.382 74.757 4.298 17.598 2.244 32.575-6.148 44.632C440.202 511.587 389.616 512 346.839 512l-2.845-.001c-48.287-.017-87.806-17.598-119.56-31.725-15.957-7.099-36.821-15.887-52.651-16.178-6.54-.12-11.783-5.457-11.783-11.998v-213.77c0-3.2 1.282-6.271 3.558-8.521 39.614-39.144 56.648-80.587 89.117-113.111 14.804-14.832 20.188-37.236 25.393-58.902C282.515 39.293 291.817 0 312 0c24 0 72 8 72 81.452z"></path></svg>
				</h2><br/>
				<strong>PAGAMENTO CONFIRMADO!</strong></br>
				<p>Seu pagamento foi confirmado com sucesso.</p><br/>
				<a class="close" href="<?php echo get_site_url(); ?>">VOLTAR PARA A LOJA</a>
				<style>.woocommerce-thankyou-order-received,.woocommerce-customer-details,.woocommerce-order-details,ul.order_details{display:none}</style>
			<?php elseif($valid): ?>
				<?php if($paymee->response->instructions->steps->qrCode): ?>
					<img width="250" height="auto" src="<?php echo plugin_dir_url( __DIR__ ) ; ?>assets/images/pix-bc-logo-1--.png" /><br/>
					<script>
						window.addEventListener('load', function(){
							jQuery('#clock').countdown('<?php echo date('Y-m-d H:i:s', strtotime($order->order_date) + (60 * 60 * 4)); ?>', function(event) {
								jQuery(this).html(event.strftime('Tempo restante: %H:%M:%S'));
							});
						})
					</script>
					<p><span id="clock"></span></p>
					<img src="<?php echo $paymee->response->instructions->qrCode->url; ?>" /><br/>
					<strong>Escaneie o código com seu celular</strong></br><br/>
					<p>Abra o app do seu banco no celular, escolha Pix e<br/>aponte a câmera para o código</p><br/>
					<input type="text" style="opacity:0;padding:0;margin:0;width:1px;height:1px;" id="qrcode" value="<?php echo $paymee->response->instructions->qrCode->plain; ?>" />
					<button class="btn" data-clipboard-target="#qrcode" onclick="alert('Código copiado!')">
						COPIAR CÓDIGO QR
					</button><br/><br/>

					<script>
					window.addEventListener('load', function(){
						new ClipboardJS('.btn');
					})
					</script>
					
					<script>

					window.addEventListener('load', function(){

						var ajaxurl = "<?= admin_url('admin-ajax.php');?>";
						var id = <?= $order_id;?>;

						var postdata= {'action':'pix_reload' , 'param':id};

  						function verificaStatus() 
  						{
							jQuery.get(ajaxurl, postdata, function(response) 
							{
								if(response == 'processing' || response == 'complete') {
									location.reload()
								}
								setTimeout(verificaStatus, 5000)
							});
						}
						verificaStatus()
						new ClipboardJS('.btn');
					})
					</script>
				<?php endif; ?>
			<?php else: ?>
				<h2 style="font-size:60px;width:100px;height:100px;display:flex;align-items:center;justify-content:center;margin: 30px auto;color: #ddd;border:4px solid #ddd;border-radius:100px;">
					<svg style="transform:scale(0.3)" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="exclamation" class="svg-inline--fa fa-exclamation fa-w-6" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 512"><path fill="currentColor" d="M176 432c0 44.112-35.888 80-80 80s-80-35.888-80-80 35.888-80 80-80 80 35.888 80 80zM25.26 25.199l13.6 272C39.499 309.972 50.041 320 62.83 320h66.34c12.789 0 23.331-10.028 23.97-22.801l13.6-272C167.425 11.49 156.496 0 142.77 0H49.23C35.504 0 24.575 11.49 25.26 25.199z"></path></svg>
				</h2><br/>
				<strong>O tempo expirou.</strong></br>
				<p>Para pagar finalize sua compra novamente</p><br/>
				<a class="close" href="<?php echo get_site_url(); ?>">FECHAR</a>
				<style>.woocommerce-thankyou-order-received,.woocommerce-customer-details,.woocommerce-order-details,ul.order_details{display:none}</style>
			<?php endif; ?>
		</div>
		<style>
		a.close{width:100%;max-width:200px;padding: 10px 20px;background: #154DD8;color:#fff;text-decoration:none;text-align:center;display:block;margin: 15px auto;font-weight:bold;}
		.instrus{text-align: center;padding:30px;background:#fff;margin-bottom:30px;}
		.instrus h3 {font-weight:bold;}
		.instrus img {display:block;margin:auto;}
		.instrus h4 {font-weight: bold;}
		</style>
		<?php
		if ( $timer <  time() && $this->instructions ) 
		{
            echo wpautop( wptexturize( $this->instructions ) );
        }
    }

	/**
	 * check if transactions is paid at PayMee
	 * @return bool
	 */
	private function check_webhook_status($obj) 
	{
        $x_api_key = $this->get_api_key();
        $x_api_token = $this->get_api_token();
        $url = "https://" . $this->get_enviroment() . ".paymee.com.br/v1.1/transactions/" . $obj->saleToken;
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "x-api-key: $x_api_key",
                "x-api-token: $x_api_token"
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
		if ($err) 
		{
            $this->log->debug("cURL Error #:" . $err);
            return false;
        } 
		$responseData = json_decode($response, true);
		if($responseData['message'] == 'success' && 
					$responseData['situation'] == 'PAID' && 
						$obj->referenceCode === $responseData['referenceCode']) {
            return true;
		} 
		return false;
	}
	
	/**
	 * handle a PayMee's webhook
	 * @return void
	 */
	public function confirm_payment($order_id, $payload) 
	{
		
		global $woocommerce;
		try {
			$order = new WC_Order($order_id);
			if(!isset($order) || !$order->id) 
			{
				status_header(404);
			}
			else if(($order->get_status() !== 'processing' && $order->get_status() !== 'completed') && $this->check_webhook_status($payload)) 
			{
				$order->update_status('processing');
			}
			status_header(200);
		}
		catch (Exception $e) 
		{
			$this->log->debug($e->getMessage());
			status_header(500);
		}
	}


	public function paymee_pix_ipn_listener() 
	{
		try {
			$raw_post = file_get_contents( 'php://input' );
			$payload  = json_decode( $raw_post );
				
			if (isset($payload->referenceCode)) 
			{
				$order_id = intval(str_replace($this->invoice_prefix, "", $payload->referenceCode));
				$this->confirm_payment($order_id, $payload);
			}
		}
		catch (Exception $e) {
		}
	}


	/**
	 * Returns a bool that indicates if currency is amongst the supported ones.
	 *
	 * @return bool
	 */
	public function using_supported_currency() 
	{
		return 'BRL' === get_woocommerce_currency();
	}

	/**
	 * Get email.
	 *
	 * @return string
	 */
	public function get_api_key() 
	{
		return ( 'yes' == $this->sandbox ) ? $this->sandbox_api_key : $this->api_key;
	}

	/**
	 * Get token.
	 *
	 * @return string
	 */
	public function get_api_token() 
	{
		return ( 'yes' == $this->sandbox ) ? $this->sandbox_api_token : $this->api_token;
	}

	/**
	 * Get enviroment
	 */
	public function get_enviroment() 
	{
		return ('yes' === $this->sandbox) ? 'apisandbox' : 'api';
	}

	/**
	 * Returns a value indicating the the Gateway is available or not. It's called
	 * automatically by WooCommerce before allowing customers to use the gateway
	 * for payment.
	 *
	 * @return bool
	 */
	public function is_available() 
	{
		// Test if is valid for use.
		$available = 'yes' === $this->get_option( 'enabled' ) && '' !== $this->get_api_key() && '' !== $this->get_api_token() && $this->using_supported_currency();

		if ( 'transparent' == $this->method && ! class_exists( 'Extra_Checkout_Fields_For_Brazil' ) ) 
		{
			$available = false;
		}

		return $available;
	}

	/**
	 * Has fields.
	 *
	 * @return bool
	 */
	public function has_fields() 
	{
		return 'transparent' === $this->method;
	}

	/**
	 * Get log.
	 *
	 * @return string
	 */
	protected function get_log_view() 
	{
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.2', '>=' ) ) 
		{
			return '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' . __( 'System Status &gt; Logs', 'woocommerce-paymee' ) . '</a>';
		}

		return '<code>woocommerce/logs/' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.txt</code>';
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() 
	{
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-paymee' ),
				'type'    => 'checkbox',
				'label'   => __( 'Habilitar a PayMee', 'woocommerce-paymee' ),
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __( 'Título', 'woocommerce-paymee' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-paymee' ),
				'desc_tip'    => true,
				'default'     => __( 'PayMee', 'woocommerce-paymee' ),
			),
			'description' => array(
				'title'       => __( 'Descrição', 'woocommerce-paymee' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-paymee' ),
				'default'     => __( 'Pague com PIX com a PayMee BR', 'woocommerce-paymee' ),
			),
			'texto_botao' => array(
				'title'       => __( 'Texto do botão', 'woocommerce-paymee' ),
				'type'        => 'text',
				'description' => __( 'Texto exibido no botão de checkout', 'woocommerce-paymee' ),
				'default'     => __( 'Gerar QR Code pra pagamento', 'woocommerce-paymee' ),
			),
			'integration' => array(
				'title'       => __( 'Integração', 'woocommerce-paymee' ),
				'type'        => 'title',
				'description' => '',
			),
			'method' => array(
				'title'       => __( 'Método de integração', 'woocommerce-paymee' ),
				'type'        => 'select',
				'description' => __( '', 'woocommerce-paymee' ),
				'desc_tip'    => true,
				'default'     => 'direct',
				'class'       => 'wc-enhanced-select',
				'options'     => array(
					'redirect'    => __( 'Semi transparente (padrão)', 'woocommerce-paymee' )
				),
			),
			'sandbox' => array(
				'title'       => __( 'PayMee Sandbox', 'woocommerce-paymee' ),
				'type'        => 'checkbox',
				'label'       => __( 'Habilitar a PayMee Sandbox', 'woocommerce-paymee' ),
				'desc_tip'    => true,
				'default'     => 'no',
				'description' => __( 'Pague com Pix via Paymee', 'woocommerce-paymee' ),
			),
			'api_key' => array(
				'title'       => __( 'PayMee X-API-KEY', 'woocommerce-paymee' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Para gerar suas credenciais acesse: %s.', 'woocommerce-paymee' ), '<a href="https://apisandbox.paymee.com.br/register">' . __( 'here', 'woocommerce-paymee' ) . '</a>' ),
				'default'     => '',
			),
			'api_token' => array(
				'title'       => __( 'PayMee X-API-TOKEN', 'woocommerce-paymee' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Para gerar suas credenciais acesse: %s.', 'woocommerce-paymee' ), '<a href="https://apisandbox.paymee.com.br/register">' . __( 'here', 'woocommerce-paymee' ) . '</a>' ),
				'default'     => '',
			),
			'sandbox_api_key' => array(
				'title'       => __( 'Sandbox PayMee X-API-KEY', 'woocommerce-paymee' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Para gerar suas credenciais acesse: %s.', 'woocommerce-paymee' ), '<a href="https://apisandbox.paymee.com.br/register">' . __( 'here', 'woocommerce-paymee' ) . '</a>' ),
				'default'     => '',
			),
			'sandbox_api_token' => array(
				'title'       => __( 'Sandbox PayMee X-API-TOKEN', 'woocommerce-paymee' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Para gerar suas credenciais acesse: %s.', 'woocommerce-paymee' ), '<a href="https://apisandbox.paymee.com.br/register">' . __( 'here', 'woocommerce-paymee' ) . '</a>' ),
				'default'     => '',
			),
			'behavior' => array(
				'title'       => __( 'Informações extras', 'woocommerce-paymee' ),
				'type'        => 'title',
				'description' => '',
			),
			'send_only_total' => array(
				'title'   => __( 'Apenas o valor final', 'woocommerce-paymee' ),
				'type'    => 'checkbox',
				'label'   => __( 'Se essa opção estiver marcada, será enviado apenas o valor total do pedido.', 'woocommerce-paymee' ),
				'default' => 'no',
			),
			'invoice_prefix' => array(
				'title'       => __( 'Prefixo da venda', 'woocommerce-paymee' ),
				'type'        => 'text',
				'description' => __( 'Please enter a prefix for your invoice numbers. If you use your PayMee account for multiple stores ensure this prefix is unqiue as PayMee will not allow orders with the same invoice number.', 'woocommerce-paymee' ),
				'desc_tip'    => true,
				'default'     => 'WC-',
			),
			'debug' => array(
				'title'       => __( 'Debug Log', 'woocommerce-paymee' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'woocommerce-paymee' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log PayMee events, such as API requests, inside %s', 'woocommerce-paymee' ), $this->get_log_view() ),
			),
		);
	}

	/**
	 * Admin page.
	 */
	public function admin_options() 
	{
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'paymee-admin', plugins_url( 'assets/js/admin/admin' . $suffix . '.js', plugin_dir_path( __FILE__ ) ), array( 'jquery' ), WC_Woo_PayMee_Pix::VERSION, true );

		include dirname( __FILE__ ) . '/admin/views/html-admin-page.php';
	}

	/**
	 * Send email notification.
	 *
	 * @param string $subject Email subject.
	 * @param string $title   Email title.
	 * @param string $message Email message.
	 */
	protected function send_email( $subject, $title, $message ) 
	{
		$mailer = WC()->mailer();
		$mailer->send( get_option( 'admin_email' ), $subject, $mailer->wrap_message( $title, $message ) );
	}


	/**
	 * Process the payment and return the result.
	 *
	 * @param  int $order_id Order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) 
	{
		$order = wc_get_order( $order_id );

		//WC()->cart->empty_cart();
		$response = $this->api->do_checkout_request( $order, $_POST );
		return array(
			'result'   => 'success',
			'redirect' => $response['url'],
		);
	}


	/**
	 * Save payment meta data.
	 *
	 * @param  WC_Order $order Order instance.
	 * @param  array   $posted Posted data.
	 */
	protected function save_payment_meta_data( $order, $posted ) 
	{
		$meta_data    = array();
		$payment_data = array(
			'type'         => '',
			'method'       => '',
			'installments' => '',
			'link'         => '',
		);

		if ( isset( $posted->sender->email ) ) 
		{
			$meta_data[ __( 'Payer email', 'woocommerce-paymee' ) ] = sanitize_text_field( (string) $posted->sender->email );
		}
		if ( isset( $posted->sender->name ) ) 
		{
			$meta_data[ __( 'Payer name', 'woocommerce-paymee' ) ] = sanitize_text_field( (string) $posted->sender->name );
		}
		if ( isset( $posted->paymentMethod->type ) ) 
		{
			$payment_data['type'] = intval( $posted->paymentMethod->type );
			$meta_data[ __( 'Payment type', 'woocommerce-paymee' ) ] = $this->api->get_payment_name_by_type( $payment_data['type'] );
		}
		if ( isset( $posted->paymentMethod->code ) ) 
		{
			$payment_data['method'] = $this->api->get_payment_method_name( intval( $posted->paymentMethod->code ) );
			$meta_data[ __( 'Payment method', 'woocommerce-paymee' ) ] = $payment_data['method'];
		}
		if ( isset( $posted->paymentLink ) ) 
		{
			$payment_data['link'] = sanitize_text_field( (string) $posted->paymentLink );
			$meta_data[ __( 'Payment URL', 'woocommerce-paymee' ) ] = $payment_data['link'];
		}

		$meta_data['_WC_Woo_PayMee_Pix_payment_data'] = $payment_data;

		// WooCommerce 3.0 or later.
		if ( method_exists( $order, 'update_meta_data' ) ) 
		{
			foreach ( $meta_data as $key => $value ) 
			{
				$order->update_meta_data( $key, $value );
			}
			$order->save();
		} else {
			foreach ( $meta_data as $key => $value ) 
			{
				update_post_meta( $order->id, $key, $value );
			}
		}
	}
}

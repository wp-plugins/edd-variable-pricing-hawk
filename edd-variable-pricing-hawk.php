<?php
/*
Plugin Name: EDD Variable Pricing Hawk
Plugin URI: http://wp-glogin.com/edd-variable-pricing-hawk/
Description: Adds an alert confirming variable pricing options have been chosen correctly
Version: 1.1
Author: Dan Lester
Author URI: http://wp-glogin.com/edd-variable-pricing-hawk/
License: GPL3
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * CSS example to change alert styles:
 * 
 * div.edd_vph_alert {
 *	font-size: 2em;
 *	color: red !important;
 * }
 */

class EDD_Variable_Pricing_Hawk {

	private static $instance;

	/**
	 * Obtain or start singleton instance
	 */
	public static function instance() {
		if ( ! isset ( self::$instance ) || is_null(self::$instance) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	protected function __construct() {
		$this->setup_hooks();
		$this->load_textdomain();
	}
	
	/**
	 * Internationalization
	 */
	private function load_textdomain() {
		load_plugin_textdomain( 'edd-vph', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
	}

	/**
	 * Add all actions and filters
	 */
	private function setup_hooks() {
		add_action( 'edd_after_price_option', array($this, 'edd_vph_output_alerttext'), 10, 3 );
		add_action( 'edd_download_price_table_head', array($this, 'edd_vph_download_price_table_head') );
		add_action( 'edd_download_price_table_row', array($this, 'edd_vph_download_price_table_row'), 10, 3 );
		add_filter( 'edd_price_row_args', array($this, 'edd_vph_price_row_args'), 10, 2 );
		
		add_action( 'edd_after_price_field', array($this, 'edd_vph_after_price_field'), 10, 1);
		add_action( 'edd_metabox_fields_save', array( $this, 'edd_vph_metabox_fields_save' ) );
		
		add_filter( 'edd_price_option_checked', array($this, 'edd_vph_price_option_checked'), 10, 3 );
		add_filter( 'edd_after_price_options', array($this, 'edd_vph_after_price_options'), 10, 1 );
		
		add_filter( 'edd_purchase_link_args', array($this, 'edd_vph_purchase_link_args'), 10, 1 );
	}

	/**
	 * Output 'Blank all selections by default' checkbox in Downloads admin form, plus JS to put it in correct position
	 * @param $post_id
	 */
	public function edd_vph_after_price_field($post_id) {
		$blankselections = $this->edd_has_blankselections($post_id);
		?>
			<script type="text/javascript">
				jQuery( document ).ready( function($) {
					// Move our checkbox to be within the variable pricing div
					$('p#edd_vph_downloadoptions').insertBefore($('div#edd_price_fields'));
				} );
			</script>
		
			<p id="edd_vph_downloadoptions">
				<label for="edd_vph_blankselections">
				<input type="checkbox" name="_vph_blankselections" id="edd_vph_blankselections" value="1" <?php checked( 1, $blankselections ); ?> />
					<?php _e( 'Blank all selections by default', 'edd-vph' ); ?>
				</label>
			</p>
		<?php
	}
	
	/**
	 * Add our 'blank selection' to list of fields to be saved in Download
	 * @param  $fields Array of fields
	 * @return array
	 */
	public function edd_vph_metabox_fields_save( $fields ) {
		$fields[] = '_vph_blankselections';
	
		return $fields;
	}
	
	/**
	 * Inspect Download record to see if Blank selections is checked
	 * @param $download_id
	 */
	protected function edd_has_blankselections( $download_id ) {
		if ( get_post_meta( $download_id, '_vph_blankselections', true ) ) {
			return true;
		}
	
		return false;
	}
	
	/**
	 * Tell EDD whether we want selections to be blank for this item 
	 * @param $checked
	 * @param $download_id
	 * @param $key
	 * @return string|unknown
	 */
	public function edd_vph_price_option_checked( $checked, $download_id, $key ) {
		if ($this->edd_has_blankselections($download_id)) {
			return '';
		}
		return $checked;
	}
	
	/**
	 * Attributes to apply to a purchase link
	 * @param $args
	 */
	public function edd_vph_purchase_link_args( $args ) {
		
		if ($this->edd_has_blankselections($args['download_id'])) {
			$args['class'] = (isset($args['class']) ? $args['class'] : ''). ' edd_vph_blankselections';
		}
		
		return $args;
	}
	
	protected $outputted_js = false;
	
	/**
	 * Outputs Javascript code to show/hide alert text on selection
	 * @param $download_id
	 */
	public function edd_vph_after_price_options( $download_id ) {
		// Only output this js once per page
		if ($this->outputted_js) {
			return;
		}
		$this->outputted_js = true;
		?>
		<script type="text/javascript">
		jQuery( document ).ready( function($) {
			$('div.edd_price_options').find('input[type=radio]').on("change", function() {
				var self = $(this);
				// Traverse tree to find root item
				var parentli = self.parent().parent();
				// Close any alerts open for other options
				parentli.parent().find("div.edd_vph_alert").hide();
				// Find any associated alerts with the newly-selected option
				var id = self.attr('id').replace('edd_price_option_', 'edd_vph_alert_');
				parentli.find("div#"+id).show();

				var purchasesubmit = parentli.closest("form.edd_download_purchase_form").find("div.edd_purchase_submit_wrapper input[type=submit].edd-add-to-cart");
				purchasesubmit.removeAttr('disabled');
			});

			// Disable relevant submit buttons until user makes a choice
			$("input[type=submit].edd_vph_blankselections").attr('disabled', 'disabled');
		} );
		</script>
		<?php
	}
	

	/**
	 * Outputs the hidden alerttext on the purchase selection page
	 * @param $key
	 * @param $price
	 * @param $download_id
	 */
	function edd_vph_output_alerttext( $key, $price, $download_id ) { 
		
		if (isset( $price['alerttext'] ) && $price['alerttext'] != '' ) {
			echo '<div class="edd_vph_alert" id="edd_vph_alert_'.$download_id.'_'.$key.'" style="display: none; color: blue">' . esc_html( $price['alerttext'] ) . '</div>';
		}
	}
	
	
	/**
	 * Adds the admin table header
	 */
	function edd_vph_download_price_table_head() { ?>
	
		<th><?php _e( 'Alert Text', 'edd-vph' ); ?></th>
	
	<?php }
	
	
	/**
	 * Adds the admin table cell with alerttext input field
	 * @param $post_id
	 * @param $key
	 * @param $args
	 */
	function edd_vph_download_price_table_row( $post_id, $key, $args ) {
		$alerttext = isset($args['alerttext']) ? $args['alerttext'] : '';
	?>
	
		<td>
			<input type="text" class="edd_variable_prices_alerttext" value="<?php echo esc_attr( $alerttext ); ?>" placeholder="<?php _e( 'Selection Alert Text', 'edd-vph' ); ?>" name="edd_variable_prices[<?php echo $key; ?>][alerttext]" id="edd_variable_prices[<?php echo $key; ?>][alerttext]" size="20" style="width:100%" />
		</td>
	
	<?php }
	
	
	/**
	 * Add alerttext field to edd_price_row_args
	 * @param $args
	 * @param $value
	 * @return Array
	 */
	function edd_vph_price_row_args( $args, $value ) {
		$args['alerttext'] = isset( $value['alerttext'] ) ? $value['alerttext'] : '';
		return $args;
	}

}

/**
 * Get the single instance running
 */
function edd_vph_variable_pricing_hawk() {
	EDD_Variable_Pricing_Hawk::instance();
}
add_action( 'plugins_loaded', 'edd_vph_variable_pricing_hawk' );


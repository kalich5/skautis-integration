<?php
/**
 * Contains the Settings class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Modules\Unit\Admin;

use Skautis_Integration\Admin\Settings as Admin_Settings;
use Skautis_Integration\Admin\Settings_Sections;
use Skautis_Integration\Modules\Recruitment\Admin\Appearances_Page;
use Skautis_Integration\Modules\Recruitment\Appearances;
use Skautis_Integration\Modules\Recruitment\Recruitment_Data;
use Skautis_Integration\Modules\Unit\Unit_Data;
use Skautis_Integration\Utils\Helpers;

/**
 * Adds the "Unit" page to the SkautIS menu, with the registration number of the unit and the overview of its public information loaded from SkautIS.
 *
 * @phan-constructor-used-for-side-effects
 */
final class Settings {

	/**
	 * The slug of the administration page, which is also its settings group.
	 */
	const PAGE = SKAUTIS_INTEGRATION_NAME . '_unit';

	/**
	 * The hook suffix of the administration page.
	 *
	 * @var string
	 */
	private static $hook_suffix = '';

	/**
	 * A link to the Unit_Data service instance.
	 *
	 * @var Unit_Data
	 */
	private $unit_data;

	/**
	 * Constructs the service and saves all dependencies.
	 *
	 * @param Unit_Data $unit_data An injected Unit_Data service instance.
	 */
	public function __construct( Unit_Data $unit_data ) {
		$this->unit_data = $unit_data;
		add_action( 'admin_init', array( $this, 'setup_setting_fields' ) );
		// After the settings, the user management and the recruitment.
		add_action( 'admin_menu', array( self::class, 'setup_page' ), 12 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_styles' ) );
	}

	/**
	 * Adds the page to the SkautIS menu.
	 *
	 * @return void
	 */
	public static function setup_page() {
		$hook_suffix = add_submenu_page(
			SKAUTIS_INTEGRATION_NAME,
			_x( 'Unit', 'admin page', 'skautis-integration' ),
			_x( 'Unit', 'admin page', 'skautis-integration' ),
			Helpers::get_skautis_manager_capability(),
			self::PAGE,
			array( self::class, 'print_page' )
		);
		if ( false !== $hook_suffix ) {
			self::$hook_suffix = $hook_suffix;
		}
	}

	/**
	 * Enqueues the styles of the plugin administration pages on the page.
	 *
	 * @param string $hook_suffix The suffix of the hook for the current admin page.
	 *
	 * @return void
	 */
	public static function enqueue_styles( $hook_suffix ) {
		if ( '' !== self::$hook_suffix && self::$hook_suffix === $hook_suffix ) {
			Helpers::enqueue_style( 'admin_settings', 'admin/css/skautis-admin-settings.min.css' );
			// The previews of the appearances use the styles of the blocks.
			wp_enqueue_style( SKAUTIS_INTEGRATION_NAME . '_modules_recruitment' );
			Helpers::enqueue_style( 'modules_recruitment_admin', 'modules/Recruitment/admin/css/skautis-modules-recruitment-admin.min.css' );
			Helpers::enqueue_script( 'modules_recruitment_appearance', 'modules/Recruitment/admin/js/skautis-modules-recruitment-appearance.min.js', array( 'wp-dom-ready' ) );
		}
	}

	/**
	 * Prints the page.
	 *
	 * @return void
	 */
	public static function print_page() {
		if ( ! Helpers::user_is_skautis_manager() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'skautis-integration' ) );
		}

		Admin_Settings::print_page_header( _x( 'Unit', 'admin page', 'skautis-integration' ) );
		echo '<div class="skautis-settings-body">';
		if ( ! Appearances_Page::maybe_print_editor( Appearances::UNIT ) ) {
			settings_errors();
			echo '<form method="POST" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
			settings_fields( self::PAGE );
			Settings_Sections::print( self::PAGE );
			submit_button();
			echo '</form>';
			Appearances_Page::print_list( Appearances::UNIT );
		}
		echo '</div>';
	}

	/**
	 * Adds the settings to WordPress.
	 *
	 * @return void
	 */
	public function setup_setting_fields() {
		add_settings_section(
			self::PAGE,
			__( 'Information about the unit', 'skautis-integration' ),
			static function () {
				echo '<p>' . esc_html__( 'The public information about the unit from SkautIS – the seat, the contacts marked as public, the statutory body, the bank account and the child units – can be shown on the site with the "Unit" block. The block shows only what you choose in its settings.', 'skautis-integration' ) . '</p>';
			},
			self::PAGE
		);

		add_settings_field(
			Unit_Data::UNIT_OPTION,
			__( 'Registration number of the unit', 'skautis-integration' ),
			array( self::class, 'field_unit' ),
			self::PAGE,
			self::PAGE,
			array( 'label_for' => Unit_Data::UNIT_OPTION )
		);

		add_settings_field(
			Appearances::UNIT_DEFAULT_OPTION,
			__( 'Default appearance of the block', 'skautis-integration' ),
			array( self::class, 'field_default_appearance' ),
			self::PAGE,
			self::PAGE,
			array( 'label_for' => Appearances::UNIT_DEFAULT_OPTION )
		);

		add_settings_section(
			self::PAGE . '_status',
			__( 'Data from SkautIS', 'skautis-integration' ),
			array( $this, 'print_status' ),
			self::PAGE
		);

		register_setting(
			self::PAGE,
			Unit_Data::UNIT_OPTION,
			array(
				'type'              => 'string',
				'show_in_rest'      => false,
				'sanitize_callback' => static function ( $value ) {
					$number = Recruitment_Data::sanitize_number( $value );
					// Saving the settings loads the data from SkautIS again.
					Unit_Data::clear_cache( '' !== $number ? $number : Recruitment_Data::get_unit_number() );

					return $number;
				},
			)
		);

		register_setting(
			self::PAGE,
			Appearances::UNIT_DEFAULT_OPTION,
			array(
				'type'              => 'string',
				'show_in_rest'      => false,
				'sanitize_callback' => static function ( $value ) {
					return is_string( $value ) && ! is_null( Appearances::get( $value ) ) ? $value : '';
				},
			)
		);
	}

	/**
	 * Prints the field for the default appearance of the block.
	 *
	 * @return void
	 */
	public static function field_default_appearance() {
		$selected    = get_option( Appearances::UNIT_DEFAULT_OPTION, '' );
		$selected    = is_string( $selected ) ? $selected : '';
		$recruitment = Appearances::get( Appearances::get_default_id() );
		echo '<select id="' . esc_attr( Appearances::UNIT_DEFAULT_OPTION ) . '" name="' . esc_attr( Appearances::UNIT_DEFAULT_OPTION ) . '">';
		/* translators: %s: The name of the default appearance of the recruitment */
		echo '<option value="">' . esc_html( sprintf( __( 'Like the recruitment (%s)', 'skautis-integration' ), is_null( $recruitment ) ? '' : $recruitment['name'] ) ) . '</option>';
		foreach ( Appearances::get_all() as $id => $appearance ) {
			echo '<option value="' . esc_attr( $id ) . '"' . selected( $selected, $id, false ) . '>' . esc_html( $appearance['name'] ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'For the templates of Dobrý skautský web, choose the appearance of your template. A block can have its own appearance in its settings in the editor.', 'skautis-integration' ) . '</p>';
	}

	/**
	 * Prints the field for the registration number of the unit.
	 *
	 * @return void
	 */
	public static function field_unit() {
		$number = get_option( Unit_Data::UNIT_OPTION, '' );
		echo '<input type="text" class="regular-text" id="' . esc_attr( Unit_Data::UNIT_OPTION ) . '" name="' . esc_attr( Unit_Data::UNIT_OPTION ) . '" value="' . esc_attr( is_string( $number ) ? $number : '' ) . '" placeholder="' . esc_attr( '' !== Recruitment_Data::get_unit_number() ? Recruitment_Data::get_unit_number() : '112.31' ) . '" inputmode="decimal" />';
		echo '<p class="description">' . esc_html__( 'The registration number of the group (středisko) or the troop (oddíl), e.g. 112.31. When empty, the unit of the recruitment is used. A block can show another unit, set in its settings.', 'skautis-integration' ) . '</p>';
	}

	/**
	 * Prints the overview of the data loaded from SkautIS.
	 *
	 * @return void
	 */
	public function print_status() {
		if ( '' === Unit_Data::get_unit_number() ) {
			echo '<p>' . esc_html__( 'Fill in the registration number of the unit and save the settings.', 'skautis-integration' ) . '</p>';
			return;
		}

		$data = $this->unit_data->get_data();
		$unit = $data['unit'];
		if ( is_null( $unit ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The unit could not be loaded from SkautIS. Check the registration number and that the App ID of this site is allowed to use the SkautIS methods of the unit.', 'skautis-integration' ) . '</p></div>';
		} else {
			$rows = array(
				__( 'Unit', 'skautis-integration' )        => trim( $unit['registration_number'] . ' ' . $unit['name'] ),
				__( 'Seat', 'skautis-integration' )        => $unit['address'],
				__( 'Seat on the map', 'skautis-integration' ) => 0.0 !== $unit['lat'] ? __( 'Yes', 'skautis-integration' ) : __( 'No', 'skautis-integration' ),
				__( 'Public contacts', 'skautis-integration' ) => (string) count( $unit['contacts'] ),
				__( 'Statutory body, assistant and contact person', 'skautis-integration' ) => (string) count( $unit['people'] ),
				__( 'Bank accounts', 'skautis-integration' ) => (string) count( $unit['accounts'] ),
				__( 'Child units', 'skautis-integration' ) => (string) count( $unit['units'] ),
			);
			echo '<table class="widefat striped"><tbody>';
			foreach ( $rows as $label => $value ) {
				echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}

		if ( count( $data['errors'] ) > 0 ) {
			echo '<p>' . esc_html__( 'These SkautIS methods could not be called:', 'skautis-integration' ) . ' <code>' . esc_html( implode( ', ', $data['errors'] ) ) . '</code></p>';
		}
		/* translators: %s: The date and time */
		echo '<p class="description">' . esc_html( sprintf( __( 'Loaded on %s. The data are loaded again once a day and after saving the settings.', 'skautis-integration' ), wp_date( strval( get_option( 'date_format' ) ) . ' H:i', $data['loaded'] ) ) ) . '</p>';
	}
}

<?php
/**
 * Contains the Settings class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Modules\Recruitment\Admin;

use Skautis_Integration\Admin\Settings as Admin_Settings;
use Skautis_Integration\Admin\Settings_Sections;
use Skautis_Integration\Modules\Recruitment\Appearances;
use Skautis_Integration\Modules\Recruitment\Recruitment_Data;
use Skautis_Integration\Utils\Helpers;

/**
 * Adds the "Recruitment" page to the SkautIS menu, with the settings of the recruitment information and the map of the units.
 *
 * @phan-constructor-used-for-side-effects
 */
final class Settings {

	/**
	 * The slug of the administration page, which is also its settings group.
	 */
	const PAGE = SKAUTIS_INTEGRATION_NAME . '_recruitment';

	/**
	 * The hook suffix of the administration page.
	 *
	 * @var string
	 */
	private static $hook_suffix = '';

	/**
	 * A link to the Recruitment_Data service instance.
	 *
	 * @var Recruitment_Data
	 */
	private $recruitment_data;

	/**
	 * Constructs the service and saves all dependencies.
	 *
	 * @param Recruitment_Data $recruitment_data An injected Recruitment_Data service instance.
	 */
	public function __construct( Recruitment_Data $recruitment_data ) {
		$this->recruitment_data = $recruitment_data;
		add_action( 'admin_init', array( $this, 'setup_setting_fields' ) );
		// After the settings and the user management.
		add_action( 'admin_menu', array( self::class, 'setup_page' ), 11 );
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
			_x( 'Recruitment', 'admin page', 'skautis-integration' ),
			_x( 'Recruitment', 'admin page', 'skautis-integration' ),
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

		Admin_Settings::print_page_header( _x( 'Recruitment', 'admin page', 'skautis-integration' ) );
		echo '<div class="skautis-settings-body">';
		if ( ! Appearances_Page::maybe_print_editor() ) {
			settings_errors();
			?>
			<form method="POST" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php
				settings_fields( self::PAGE );
				Settings_Sections::print( self::PAGE );
				submit_button();
				?>
			</form>
			<?php
			Appearances_Page::print_list();
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
			__( 'Recruitment and map of the units', 'skautis-integration' ),
			static function () {
				echo '<p>' . esc_html__( 'The recruitment information that the units fill in SkautIS on the "Jednotka: Nábory" tab can be shown on the site with the "Recruitment" and "Map of the units" blocks.', 'skautis-integration' ) . ' <a href="' . esc_url( Admin_Settings::get_tab_url( 'maps' ) ) . '">' . esc_html__( 'The API key of the maps is set in the settings, on the "Maps" tab.', 'skautis-integration' ) . '</a></p>';
			},
			self::PAGE
		);

		add_settings_field(
			Recruitment_Data::UNIT_OPTION,
			__( 'Registration number of the unit', 'skautis-integration' ),
			array( self::class, 'field_unit' ),
			self::PAGE,
			self::PAGE,
			array( 'label_for' => Recruitment_Data::UNIT_OPTION )
		);

		add_settings_field(
			Appearances::DEFAULT_OPTION,
			__( 'Default appearance of the blocks', 'skautis-integration' ),
			array( self::class, 'field_default_appearance' ),
			self::PAGE,
			self::PAGE,
			array( 'label_for' => Appearances::DEFAULT_OPTION )
		);

		add_settings_section(
			self::PAGE . '_status',
			__( 'Data from SkautIS', 'skautis-integration' ),
			array( $this, 'print_status' ),
			self::PAGE
		);

		register_setting(
			self::PAGE,
			Recruitment_Data::UNIT_OPTION,
			array(
				'type'              => 'string',
				'show_in_rest'      => false,
				'sanitize_callback' => static function ( $value ) {
					// Saving the settings loads the data from SkautIS again.
					Recruitment_Data::clear_cache();

					return is_string( $value ) ? preg_replace( '/[^0-9.]/u', '', $value ) : '';
				},
			)
		);

		register_setting(
			self::PAGE,
			Appearances::DEFAULT_OPTION,
			array(
				'type'              => 'string',
				'show_in_rest'      => false,
				'sanitize_callback' => static function ( $value ) {
					return is_string( $value ) && ! is_null( Appearances::get( $value ) ) ? $value : Appearances::THEME;
				},
			)
		);
	}

	/**
	 * Prints the field for the registration number of the unit.
	 *
	 * @return void
	 */
	public static function field_unit() {
		echo '<input type="text" class="regular-text" id="' . esc_attr( Recruitment_Data::UNIT_OPTION ) . '" name="' . esc_attr( Recruitment_Data::UNIT_OPTION ) . '" value="' . esc_attr( Recruitment_Data::get_unit_number() ) . '" placeholder="112.31" inputmode="decimal" />';
		echo '<p class="description">' . esc_html__( 'The registration number of the group (středisko) or the troop (oddíl), e.g. 112.31. For a group, the recruitment of all its troops is shown.', 'skautis-integration' ) . '</p>';
	}

	/**
	 * Prints the field for the default appearance of the blocks.
	 *
	 * @return void
	 */
	public static function field_default_appearance() {
		$default = Appearances::get_default_id();
		echo '<select id="' . esc_attr( Appearances::DEFAULT_OPTION ) . '" name="' . esc_attr( Appearances::DEFAULT_OPTION ) . '">';
		foreach ( Appearances::get_all() as $id => $appearance ) {
			echo '<option value="' . esc_attr( $id ) . '"' . selected( $id, $default, false ) . '>' . esc_html( $appearance['name'] ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'For the templates of Dobrý skautský web, choose the appearance of your template. A block can have its own appearance in its settings in the editor.', 'skautis-integration' ) . '</p>';
	}


	/**
	 * Prints the overview of the data loaded from SkautIS.
	 *
	 * @return void
	 */
	public function print_status() {
		if ( '' === Recruitment_Data::get_unit_number() ) {
			echo '<p>' . esc_html__( 'Fill in the registration number of the unit and save the settings.', 'skautis-integration' ) . '</p>';
			return;
		}

		$data = $this->recruitment_data->get_data();
		if ( count( $data['units'] ) === 0 ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'No recruitment information could be loaded from SkautIS. Check the registration number and that the App ID of this site is allowed to use the SkautIS methods of the recruitment.', 'skautis-integration' ) . '</p></div>';
		} else {
			echo '<table class="widefat striped skautis-recruitment-status"><thead><tr>';
			echo '<th scope="col">' . esc_html__( 'Unit', 'skautis-integration' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Recruitment categories', 'skautis-integration' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Meetings', 'skautis-integration' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Clubrooms on the map', 'skautis-integration' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $data['units'] as $unit ) {
				$on_map = count(
					array_filter(
						$unit['places'],
						static function ( array $place ): bool {
							return 0.0 !== $place['lat'] && 0.0 !== $place['lng'];
						}
					)
				);
				echo '<tr><td>' . esc_html( trim( $unit['registration_number'] . ' ' . $unit['name'] ) ) . '</td>';
				echo '<td>' . esc_html( (string) count( $unit['categories'] ) ) . '</td>';
				echo '<td>' . esc_html( (string) count( $unit['meetings'] ) ) . '</td>';
				echo '<td>' . esc_html( $on_map . ' / ' . count( $unit['places'] ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}

		if ( count( $data['errors'] ) > 0 ) {
			echo '<p>' . esc_html__( 'These SkautIS methods could not be called:', 'skautis-integration' ) . ' <code>' . esc_html( implode( ', ', $data['errors'] ) ) . '</code></p>';
		}
		/* translators: %s: The date and time */
		echo '<p class="description">' . esc_html( sprintf( __( 'Loaded on %s. The data are loaded again once a day and after saving the settings.', 'skautis-integration' ), wp_date( strval( get_option( 'date_format' ) ) . ' ' . strval( get_option( 'time_format' ) ), $data['loaded'] ) ) ) . '</p>';
	}
}

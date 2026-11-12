<?php
/**
 * Contains the Settings class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Modules\Recruitment\Admin;

use Skautis_Integration\Modules\Recruitment\Recruitment_Data;

/**
 * Registers and shows the settings of the recruitment information and the map of the units.
 *
 * @phan-constructor-used-for-side-effects
 */
final class Settings {

	/**
	 * The settings group and page of the settings tab.
	 */
	const PAGE = SKAUTIS_INTEGRATION_NAME . '_recruitment';

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
				echo '<p>' . esc_html__( 'The recruitment information that the units fill in SkautIS on the "Jednotka: Nábory" tab can be shown on the site with the "Recruitment" and "Map of the units" blocks.', 'skautis-integration' ) . '</p>';
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
			Recruitment_Data::MAPY_API_KEY_OPTION,
			__( 'Mapy.com API key', 'skautis-integration' ),
			array( self::class, 'field_mapy_api_key' ),
			self::PAGE,
			self::PAGE,
			array( 'label_for' => Recruitment_Data::MAPY_API_KEY_OPTION )
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
			Recruitment_Data::MAPY_API_KEY_OPTION,
			array(
				'type'              => 'string',
				'show_in_rest'      => false,
				'sanitize_callback' => static function ( $value ) {
					return is_string( $value ) ? sanitize_text_field( $value ) : '';
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
	 * Prints the field for the API key of Mapy.com.
	 *
	 * @return void
	 */
	public static function field_mapy_api_key() {
		$key = get_option( Recruitment_Data::MAPY_API_KEY_OPTION, '' );
		echo '<input type="text" class="regular-text" id="' . esc_attr( Recruitment_Data::MAPY_API_KEY_OPTION ) . '" name="' . esc_attr( Recruitment_Data::MAPY_API_KEY_OPTION ) . '" value="' . esc_attr( is_string( $key ) ? $key : '' ) . '" autocomplete="off" spellcheck="false" />';
		echo '<p class="description">';
		echo esc_html__( 'The map uses the maps of Mapy.com with this key. You get a free key after registering at developer.mapy.com. Without the key, the map uses OpenStreetMap.', 'skautis-integration' );
		echo ' <a href="https://developer.mapy.com/" target="_blank" rel="noopener noreferrer">developer.mapy.com</a>';
		echo '</p>';
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

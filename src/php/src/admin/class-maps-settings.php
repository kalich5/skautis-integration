<?php
/**
 * Contains the Maps_Settings class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Admin;

/**
 * The settings of the maps on the "Maps" tab of the settings page, shared by the recruitment and the unit blocks.
 *
 * @phan-constructor-used-for-side-effects
 */
final class Maps_Settings {

	/**
	 * The settings group and page of the "Maps" tab.
	 */
	const PAGE = SKAUTIS_INTEGRATION_NAME . '_maps';

	/**
	 * The option with the API key of Mapy.com.
	 */
	const MAPY_API_KEY_OPTION = SKAUTIS_INTEGRATION_NAME . '_mapy_api_key';

	/**
	 * The option where the key was stored before it moved to the "Maps" tab.
	 */
	const OLD_MAPY_API_KEY_OPTION = SKAUTIS_INTEGRATION_NAME . '_recruitment_mapy_api_key';

	/**
	 * Intializes all hooks used by the object.
	 */
	public function __construct() {
		add_action( 'admin_init', array( self::class, 'setup_setting_fields' ) );
	}

	/**
	 * Returns the API key of Mapy.com, or an empty string when it is not set.
	 */
	public static function get_mapy_api_key(): string {
		$key = get_option( self::MAPY_API_KEY_OPTION, false );
		if ( false === $key ) {
			// The key saved on the "Recruitment" page before it moved here.
			$key = get_option( self::OLD_MAPY_API_KEY_OPTION, '' );
		}

		return is_string( $key ) ? trim( $key ) : '';
	}

	/**
	 * Adds the settings to WordPress.
	 *
	 * @return void
	 */
	public static function setup_setting_fields() {
		add_settings_section(
			self::PAGE,
			__( 'Maps', 'skautis-integration' ),
			static function () {
				echo '<p>' . esc_html__( 'The maps in the "Map of the units" and "Unit" blocks use the maps of Mapy.com with the API key, otherwise the maps of OpenStreetMap. The key is also used to find the seat of a unit on the map.', 'skautis-integration' ) . '</p>';
			},
			self::PAGE
		);

		add_settings_field(
			self::MAPY_API_KEY_OPTION,
			__( 'Mapy.com API key', 'skautis-integration' ),
			array( self::class, 'field_mapy_api_key' ),
			self::PAGE,
			self::PAGE,
			array( 'label_for' => self::MAPY_API_KEY_OPTION )
		);

		register_setting(
			self::PAGE,
			self::MAPY_API_KEY_OPTION,
			array(
				'type'              => 'string',
				'show_in_rest'      => false,
				'sanitize_callback' => static function ( $value ) {
					// The key is stored only here from now on.
					delete_option( self::OLD_MAPY_API_KEY_OPTION );

					return is_string( $value ) ? sanitize_text_field( $value ) : '';
				},
			)
		);
	}

	/**
	 * Prints the field for the API key of Mapy.com.
	 *
	 * @return void
	 */
	public static function field_mapy_api_key() {
		echo '<input type="text" class="regular-text" id="' . esc_attr( self::MAPY_API_KEY_OPTION ) . '" name="' . esc_attr( self::MAPY_API_KEY_OPTION ) . '" value="' . esc_attr( self::get_mapy_api_key() ) . '" autocomplete="off" spellcheck="false" />';
		echo '<p class="description">';
		echo esc_html__( 'You get a free key after registering at developer.mapy.com. Restrict the key to the domain of this site there, because the key is visible in the addresses of the map tiles. Without the key, the maps use OpenStreetMap.', 'skautis-integration' );
		echo ' <a href="https://developer.mapy.com/" target="_blank" rel="noopener noreferrer">developer.mapy.com</a>';
		echo '</p>';
	}
}

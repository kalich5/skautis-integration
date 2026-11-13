<?php
/**
 * Contains the Appearances_Page class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Modules\Recruitment\Admin;

use PHPMD\Attribute\SuppressWarnings;
use PHPMD\Rule\Design\ExitExpression;
use Skautis_Integration\Modules\Recruitment\Appearances;
use Skautis_Integration\Modules\Recruitment\Frontend\Blocks;
use Skautis_Integration\Modules\Unit\Admin\Settings as Unit_Settings;
use Skautis_Integration\Modules\Unit\Frontend\Unit_Card;
use Skautis_Integration\Modules\Unit\Unit_Data;
use Skautis_Integration\Utils\Helpers;
use Skautis_Integration\Utils\Request_Parameter_Helpers;

/**
 * Shows the appearances with previews on the "Recruitment" and "Unit" pages and lets the managers copy, edit and delete their own appearances.
 *
 * Both pages share the appearances, each page shows the previews of its blocks and has its own default appearance.
 *
 * @phan-constructor-used-for-side-effects
 */
final class Appearances_Page {

	/**
	 * The admin-post action that copies an appearance.
	 */
	const COPY_ACTION = SKAUTIS_INTEGRATION_NAME . '_recruitment_appearance_copy';

	/**
	 * The admin-post action that saves a custom appearance.
	 */
	const SAVE_ACTION = SKAUTIS_INTEGRATION_NAME . '_recruitment_appearance_save';

	/**
	 * The admin-post action that deletes a custom appearance.
	 */
	const DELETE_ACTION = SKAUTIS_INTEGRATION_NAME . '_recruitment_appearance_delete';

	/**
	 * Intializes all hooks used by the object.
	 */
	public function __construct() {
		add_action( 'admin_post_' . self::COPY_ACTION, array( self::class, 'copy' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( self::class, 'save' ) );
		add_action( 'admin_post_' . self::DELETE_ACTION, array( self::class, 'delete' ) );
	}

	/**
	 * Returns the context of the current request: Appearances::RECRUITMENT or Appearances::UNIT.
	 */
	private static function get_context(): string {
		$context = Request_Parameter_Helpers::get_string_variable( 'context', Request_Parameter_Helpers::post_string_variable( 'context' ) );

		return Appearances::UNIT === $context ? Appearances::UNIT : Appearances::RECRUITMENT;
	}

	/**
	 * Returns the URL of the "Recruitment" or "Unit" page, optionally with the editor of an appearance.
	 *
	 * @param string $appearance The ID of the edited appearance, or an empty string for the page itself.
	 * @param string $context The page: Appearances::RECRUITMENT or Appearances::UNIT.
	 */
	public static function get_page_url( string $appearance = '', string $context = Appearances::RECRUITMENT ): string {
		$url = admin_url( 'admin.php?page=' . ( Appearances::UNIT === $context ? Unit_Settings::PAGE : Settings::PAGE ) );

		return '' !== $appearance ? add_query_arg( 'appearance', $appearance, $url ) : $url;
	}

	/**
	 * Returns the URL of an admin-post action for an appearance, with its nonce.
	 *
	 * @param string $action The action.
	 * @param string $appearance The ID of the appearance.
	 * @param string $context The page to return to: Appearances::RECRUITMENT or Appearances::UNIT.
	 */
	private static function get_action_url( string $action, string $appearance, string $context ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'     => $action,
					'appearance' => $appearance,
					'context'    => $context,
				),
				admin_url( 'admin-post.php' )
			),
			$action . '_' . $appearance
		);
	}

	/**
	 * Checks the nonce and the permissions of an admin-post action and returns the ID of its appearance.
	 *
	 * @param string $action The action.
	 */
	private static function check_action( string $action ): string {
		$appearance = Request_Parameter_Helpers::get_string_variable( 'appearance', Request_Parameter_Helpers::post_string_variable( 'appearance' ) );
		check_admin_referer( $action . '_' . $appearance );
		if ( ! Helpers::user_is_skautis_manager() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'skautis-integration' ) );
		}

		return $appearance;
	}

	/**
	 * Copies an appearance and opens the editor of the copy.
	 *
	 * @return void
	 */
	#[SuppressWarnings( ExitExpression::class )]
	public static function copy() {
		$copy = Appearances::copy( self::check_action( self::COPY_ACTION ) );
		wp_safe_redirect( self::get_page_url( $copy, self::get_context() ) );
		exit();
	}

	/**
	 * Saves a custom appearance from the editor.
	 *
	 * @return void
	 */
	#[SuppressWarnings( ExitExpression::class )]
	public static function save() {
		$appearance = self::check_action( self::SAVE_ACTION );
		if ( isset( Appearances::get_custom()[ $appearance ] ) ) {
			$values = array();
			foreach ( array_keys( Appearances::FIELDS ) as $field ) {
				$inherit          = in_array( $field, Appearances::get_optional_fields(), true ) && '1' === Request_Parameter_Helpers::post_string_variable( 'appearance_' . $field . '_inherit' );
				$values[ $field ] = $inherit ? '' : Request_Parameter_Helpers::post_string_variable( 'appearance_' . $field );
			}
			Appearances::save( $appearance, Request_Parameter_Helpers::post_string_variable( 'appearance_name' ), $values );
		}
		wp_safe_redirect( add_query_arg( 'saved', '1', self::get_page_url( $appearance, self::get_context() ) ) );
		exit();
	}

	/**
	 * Deletes a custom appearance.
	 *
	 * @return void
	 */
	#[SuppressWarnings( ExitExpression::class )]
	public static function delete() {
		Appearances::delete( self::check_action( self::DELETE_ACTION ) );
		wp_safe_redirect( self::get_page_url( '', self::get_context() ) );
		exit();
	}

	/**
	 * Returns sample units for the previews of the appearances.
	 *
	 * @return array<array{id: int, name: string, registration_number: string, is_water: bool, note: string, categories: array<array{age_from: int, age_to: int, sex: string, note: string, meeting: int}>, meetings: array<array{id: int, day: string, day_order: int, time_from: string, time_to: string, periodicity: string, place: int, place_name: string}>, places: array<array{id: int, name: string, address: string, lat: float, lng: float}>}>
	 */
	private static function get_sample_units(): array {
		$place = array(
			'id'      => 1,
			'name'    => __( 'Clubroom', 'skautis-integration' ),
			'address' => 'Hlavní 12, Nové Údolí',
			'lat'     => 0.0,
			'lng'     => 0.0,
		);

		return array(
			array(
				'id'                  => 1,
				'name'                => __( '1st troop Petrklíč', 'skautis-integration' ),
				'registration_number' => '',
				'is_water'            => false,
				'note'                => __( 'Come to any meeting and see for yourself.', 'skautis-integration' ),
				'categories'          => array(
					array(
						'age_from' => 7,
						'age_to'   => 11,
						'sex'      => 'male',
						'note'     => '',
						'meeting'  => 1,
					),
					array(
						'age_from' => 7,
						'age_to'   => 11,
						'sex'      => 'female',
						'note'     => '',
						'meeting'  => 2,
					),
				),
				'meetings'            => array(
					array(
						'id'          => 1,
						'day'         => __( 'Tuesday', 'skautis-integration' ),
						'day_order'   => 2,
						'time_from'   => '16:30',
						'time_to'     => '18:00',
						'periodicity' => '',
						'place'       => 1,
						'place_name'  => '',
					),
					array(
						'id'          => 2,
						'day'         => __( 'Wednesday', 'skautis-integration' ),
						'day_order'   => 3,
						'time_from'   => '16:30',
						'time_to'     => '18:00',
						'periodicity' => '',
						'place'       => 1,
						'place_name'  => '',
					),
				),
				'places'              => array( $place ),
			),
			array(
				'id'                  => 2,
				'name'                => __( '5th water troop Racek', 'skautis-integration' ),
				'registration_number' => '',
				'is_water'            => true,
				'note'                => '',
				'categories'          => array(
					array(
						'age_from' => 11,
						'age_to'   => 15,
						'sex'      => '',
						'note'     => '',
						'meeting'  => 0,
					),
				),
				'meetings'            => array(
					array(
						'id'          => 3,
						'day'         => __( 'Thursday', 'skautis-integration' ),
						'day_order'   => 4,
						'time_from'   => '17:00',
						'time_to'     => '19:00',
						'periodicity' => '',
						'place'       => 1,
						'place_name'  => '',
					),
				),
				'places'              => array( $place ),
			),
		);
	}

	/**
	 * Returns a sample unit for the previews of the appearances of the "Unit" block.
	 *
	 * @return array{id: int, name: string, registration_number: string, ic: string, address: string, lat: float, lng: float, contacts: array<array{type: string, value: string, note: string}>, people: array<array{role: string, function: string, name: string}>, accounts: array<array{name: string, number: string}>, units: array<array{name: string, registration_number: string}>}
	 */
	private static function get_sample_unit(): array {
		return array(
			'id'                  => 1,
			'name'                => __( 'Group Nové Údolí', 'skautis-integration' ),
			'registration_number' => '999.01',
			'ic'                  => '12345678',
			'address'             => 'Hlavní 12, 123 45 Nové Údolí',
			'lat'                 => 0.0,
			'lng'                 => 0.0,
			'contacts'            => array(
				array(
					'type'  => __( 'E-mail', 'skautis-integration' ),
					'value' => 'stredisko@example.com',
					'note'  => '',
				),
				array(
					'type'  => __( 'Web', 'skautis-integration' ),
					'value' => 'https://example.com',
					'note'  => '',
				),
			),
			'people'              => array(
				array(
					'role'     => Unit_Data::STATUTORY,
					'function' => __( 'Group leader', 'skautis-integration' ),
					'name'     => 'Jana Nováková',
				),
				array(
					'role'     => Unit_Data::ASSISTANT,
					'function' => __( 'Deputy group leader', 'skautis-integration' ),
					'name'     => 'Petr Svoboda',
				),
			),
			'accounts'            => array(),
			'units'               => array(),
		);
	}

	/**
	 * Returns the HTML code of the preview of an appearance: the cards of sample units and the map markers, or the card of a sample unit for the "Unit" block.
	 *
	 * @param string $appearance The ID of the appearance.
	 * @param bool   $full Whether to show both sample units, otherwise only the first one.
	 * @param string $context The blocks: Appearances::RECRUITMENT or Appearances::UNIT.
	 */
	private static function get_preview( string $appearance, bool $full, string $context ): string {
		if ( Appearances::UNIT === $context ) {
			return '<div class="skautis-unit-info skautis-appearance-preview" style="' . esc_attr( Appearances::get_style( $appearance ) ) . '" aria-hidden="true">'
				. Unit_Card::render( self::get_sample_unit(), array() )
				. '<div class="skautis-appearance-markers"><span class="skautis-unit-map-marker"><span></span></span></div>'
				. '</div>';
		}
		$units = self::get_sample_units();

		return '<div class="skautis-recruitment skautis-appearance-preview" style="' . esc_attr( Appearances::get_style( $appearance ) ) . '" aria-hidden="true">'
			. Blocks::render_units( $full ? $units : array_slice( $units, 0, 1 ) )
			. '<div class="skautis-appearance-markers"><span class="skautis-unit-map-marker"><span></span></span><span class="skautis-unit-map-marker is-water"><span></span></span></div>'
			. '</div>';
	}

	/**
	 * Prints the list of the appearances with their previews.
	 *
	 * @param string $context The page: Appearances::RECRUITMENT or Appearances::UNIT.
	 *
	 * @return void
	 */
	public static function print_list( string $context = Appearances::RECRUITMENT ) {
		$default = Appearances::get_default_id( $context );
		echo '<div class="skautis-settings-card"><h2>' . esc_html__( 'Appearance of the blocks', 'skautis-integration' ) . '</h2>';
		if ( Appearances::UNIT === $context ) {
			echo '<p>' . esc_html__( 'The appearance sets the colors of the "Unit" block. The fonts are taken from the theme of the site. Choose the default appearance above, or the appearance of a single block in its settings in the editor. To change the colors, copy an appearance and edit the copy.', 'skautis-integration' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'The appearance sets the colors of the "Recruitment" and "Map of the units" blocks. The fonts are taken from the theme of the site. Choose the default appearance above, or the appearance of a single block in its settings in the editor. To change the colors, copy an appearance and edit the copy.', 'skautis-integration' ) . '</p>';
		}
		echo '<p class="description">' . esc_html__( 'The appearances are shared by the "Recruitment" and "Unit" pages, a change of an appearance changes the blocks of both.', 'skautis-integration' ) . '</p>';
		echo '<div class="skautis-appearances">';
		foreach ( Appearances::get_all() as $appearance_id => $appearance ) {
			echo '<div class="skautis-appearance' . ( $appearance_id === $default ? ' is-default' : '' ) . '">';
			echo '<h3>' . esc_html( $appearance['name'] ) . ( $appearance_id === $default ? ' <span class="skautis-appearance-badge">' . esc_html__( 'Default', 'skautis-integration' ) . '</span>' : '' ) . '</h3>';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in get_preview().
			echo self::get_preview( $appearance_id, false, $context );
			$builtin = true === $appearance['builtin'];
			echo '<p class="skautis-appearance-actions">';
			if ( ! $builtin ) {
				echo '<a class="button button-primary" href="' . esc_url( self::get_page_url( $appearance_id, $context ) ) . '">' . esc_html__( 'Edit', 'skautis-integration' ) . '</a> ';
			}
			echo '<a class="button" href="' . esc_url( self::get_action_url( self::COPY_ACTION, $appearance_id, $context ) ) . '">' . esc_html( $builtin ? __( 'Copy and edit', 'skautis-integration' ) : __( 'Copy', 'skautis-integration' ) ) . '</a>';
			if ( ! $builtin ) {
				/* translators: %s: The name of the appearance */
				$confirm = sprintf( __( 'Delete the appearance "%s"? Blocks with this appearance get the default appearance.', 'skautis-integration' ), $appearance['name'] );
				echo ' <a class="button button-link-delete" href="' . esc_url( self::get_action_url( self::DELETE_ACTION, $appearance_id, $context ) ) . '" onclick="return confirm(' . esc_attr( (string) wp_json_encode( $confirm ) ) . ');">' . esc_html__( 'Delete', 'skautis-integration' ) . '</a>';
			}
			echo '</p></div>';
		}
		echo '</div></div>';
	}

	/**
	 * Prints the editor of a custom appearance with a live preview, if one is requested.
	 *
	 * @param string $context The page: Appearances::RECRUITMENT or Appearances::UNIT.
	 *
	 * @return bool Whether the editor was printed.
	 */
	public static function maybe_print_editor( string $context = Appearances::RECRUITMENT ): bool {
		$appearance_id = Request_Parameter_Helpers::get_string_variable( 'appearance' );
		$appearance    = Appearances::get_custom()[ $appearance_id ] ?? null;
		if ( is_null( $appearance ) ) {
			return false;
		}

		$labels   = Appearances::get_field_labels();
		$optional = Appearances::get_optional_fields();
		echo '<p><a href="' . esc_url( self::get_page_url( '', $context ) ) . '">← ' . esc_html( Appearances::UNIT === $context ? __( 'Back to the unit', 'skautis-integration' ) : __( 'Back to the recruitment', 'skautis-integration' ) ) . '</a></p>';
		if ( '1' === Request_Parameter_Helpers::get_string_variable( 'saved' ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'The appearance was saved.', 'skautis-integration' ) . '</p></div>';
		}
		echo '<div class="skautis-appearance-editor">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="skautis-settings-card">';
		echo '<h2>' . esc_html__( 'Edit the appearance', 'skautis-integration' ) . '</h2>';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::SAVE_ACTION ) . '" /><input type="hidden" name="appearance" value="' . esc_attr( $appearance_id ) . '" /><input type="hidden" name="context" value="' . esc_attr( $context ) . '" />';
		wp_nonce_field( self::SAVE_ACTION . '_' . $appearance_id );
		echo '<table class="form-table" role="presentation"><tr><th scope="row"><label for="appearance_name">' . esc_html__( 'Name', 'skautis-integration' ) . '</label></th>';
		echo '<td><input type="text" class="regular-text" id="appearance_name" name="appearance_name" value="' . esc_attr( $appearance['name'] ) . '" required /></td></tr>';
		foreach ( Appearances::FIELDS as $field => $property ) {
			$value = $appearance['values'][ $field ] ?? '';
			echo '<tr><th scope="row"><label for="appearance_' . esc_attr( $field ) . '">' . esc_html( $labels[ $field ] ) . '</label></th><td>';
			if ( 'radius' === $field ) {
				echo '<input type="number" min="0" max="32" class="small-text" id="appearance_radius" name="appearance_radius" value="' . esc_attr( $value ) . '" data-skautis-property="' . esc_attr( $property ) . '" data-skautis-unit="px" />';
			} else {
				echo '<input type="color" id="appearance_' . esc_attr( $field ) . '" name="appearance_' . esc_attr( $field ) . '" value="' . esc_attr( '' !== $value ? $value : '#000000' ) . '" data-skautis-property="' . esc_attr( $property ) . '" />';
				if ( in_array( $field, $optional, true ) ) {
					echo ' <label class="skautis-appearance-inherit"><input type="checkbox" name="appearance_' . esc_attr( $field ) . '_inherit" value="1" data-skautis-inherit="appearance_' . esc_attr( $field ) . '"' . checked( '' === $value, true, false ) . ' /> ' . esc_html__( 'Like the theme of the site', 'skautis-integration' ) . '</label>';
				}
			}
			echo '</td></tr>';
		}
		echo '</table>';
		submit_button( __( 'Save the appearance', 'skautis-integration' ) );
		echo '</form>';
		echo '<div class="skautis-settings-card skautis-appearance-editor-preview"><h2>' . esc_html__( 'Preview', 'skautis-integration' ) . '</h2>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in get_preview().
		echo self::get_preview( $appearance_id, true, $context );
		echo '</div></div>';

		return true;
	}
}

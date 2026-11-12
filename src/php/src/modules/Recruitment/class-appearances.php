<?php
/**
 * Contains the Appearances class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Modules\Recruitment;

/**
 * The appearances of the "Recruitment" and "Map of the units" blocks.
 *
 * An appearance is a set of colors and a corner radius, applied to a block as CSS custom properties. There are built-in appearances for the templates of Dobrý skautský web (DSW1, DSW2, DSW3) and the site can have its own appearances, e.g. copies of the built-in ones with changed colors.
 *
 * @phpstan-type Values array<string, string>
 * @phpstan-type Appearance array{name: string, builtin: bool, values: Values}
 */
final class Appearances {

	/**
	 * The option with the custom appearances, in the form `ID => array{name, values}`.
	 */
	const OPTION = SKAUTIS_INTEGRATION_NAME . '_recruitment_appearances';

	/**
	 * The option with the ID of the appearance of blocks without their own appearance.
	 */
	const DEFAULT_OPTION = SKAUTIS_INTEGRATION_NAME . '_recruitment_default_appearance';

	/**
	 * The appearance that takes everything from the theme of the site.
	 */
	const THEME = 'theme';

	/**
	 * The fields of an appearance in the form `field => CSS custom property`. An empty value of a color means the color of the theme.
	 */
	const FIELDS = array(
		'accent'           => '--skautis-rec-accent',
		'tag_text'         => '--skautis-rec-tag-text',
		'tag_background'   => '--skautis-rec-tag-bg',
		'water'            => '--skautis-rec-water',
		'water_text'       => '--skautis-rec-water-text',
		'water_background' => '--skautis-rec-water-bg',
		'text'             => '--skautis-rec-text',
		'heading'          => '--skautis-rec-heading',
		'card_background'  => '--skautis-rec-card-bg',
		'border'           => '--skautis-rec-border',
		'radius'           => '--skautis-rec-radius',
	);

	/**
	 * Returns the labels of the fields.
	 *
	 * @return array<string, string> The labels in the form `field => label`.
	 */
	public static function get_field_labels(): array {
		return array(
			'accent'           => __( 'Accent (map markers, links)', 'skautis-integration' ),
			'tag_text'         => __( 'Text of the labels "for whom"', 'skautis-integration' ),
			'tag_background'   => __( 'Background of the labels "for whom"', 'skautis-integration' ),
			'water'            => __( 'Map markers of water troops', 'skautis-integration' ),
			'water_text'       => __( 'Text of the label "Water troop"', 'skautis-integration' ),
			'water_background' => __( 'Background of the label "Water troop"', 'skautis-integration' ),
			'text'             => __( 'Text', 'skautis-integration' ),
			'heading'          => __( 'Names of the units', 'skautis-integration' ),
			'card_background'  => __( 'Background of the cards', 'skautis-integration' ),
			'border'           => __( 'Border of the cards', 'skautis-integration' ),
			'radius'           => __( 'Rounded corners (px)', 'skautis-integration' ),
		);
	}

	/**
	 * Returns the fields that may be left empty to take the color of the theme.
	 *
	 * @return array<string>
	 */
	public static function get_optional_fields(): array {
		return array( 'text', 'heading', 'card_background' );
	}

	/**
	 * Returns the built-in appearances.
	 *
	 * The colors of DSW1, DSW2 and DSW3 are taken from the demo sites of Dobrý skautský web (dsw1.skauting.cz etc.).
	 *
	 * @phpstan-return array<string, Appearance>
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_builtin(): array {
		return array(
			self::THEME => array(
				'name'    => __( 'Like the theme of the site', 'skautis-integration' ),
				'builtin' => true,
				'values'  => array(
					'accent'           => '#2271b1',
					'tag_text'         => '#135e96',
					'tag_background'   => '#f0f6fc',
					'water'            => '#0a7c86',
					'water_text'       => '#0a5c63',
					'water_background' => '#e6f4f5',
					'text'             => '',
					'heading'          => '',
					'card_background'  => '',
					'border'           => '#d9d9d9',
					'radius'           => '6',
				),
			),
			'dsw1'      => array(
				'name'    => __( 'DSW1 – troop, blue', 'skautis-integration' ),
				'builtin' => true,
				'values'  => array(
					'accent'           => '#077ea5',
					'tag_text'         => '#075772',
					'tag_background'   => '#dcecf2',
					'water'            => '#002039',
					'water_text'       => '#ffffff',
					'water_background' => '#075772',
					'text'             => '#002039',
					'heading'          => '#002039',
					'card_background'  => '#ffffff',
					'border'           => '#a1cedd',
					'radius'           => '7',
				),
			),
			'dsw2'      => array(
				'name'    => __( 'DSW2 – troop, orange', 'skautis-integration' ),
				'builtin' => true,
				'values'  => array(
					'accent'           => '#f28d00',
					'tag_text'         => '#392100',
					'tag_background'   => '#fde3bf',
					'water'            => '#1f6f8b',
					'water_text'       => '#ffffff',
					'water_background' => '#1f6f8b',
					'text'             => '#392100',
					'heading'          => '#392100',
					'card_background'  => '#ffffff',
					'border'           => '#e0dddb',
					'radius'           => '7',
				),
			),
			'dsw3'      => array(
				'name'    => __( 'DSW3 – group, green', 'skautis-integration' ),
				'builtin' => true,
				'values'  => array(
					'accent'           => '#3b8414',
					'tag_text'         => '#285706',
					'tag_background'   => '#e6f2d9',
					'water'            => '#166c83',
					'water_text'       => '#ffffff',
					'water_background' => '#166c83',
					'text'             => '#472f05',
					'heading'          => '#285706',
					'card_background'  => '#ffffff',
					'border'           => '#f0d79d',
					'radius'           => '7',
				),
			),
		);
	}

	/**
	 * Returns the custom appearances of the site.
	 *
	 * @phpstan-return array<string, Appearance>
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_custom(): array {
		$stored = get_option( self::OPTION, array() );
		$result = array();
		foreach ( is_array( $stored ) ? $stored : array() as $appearance_id => $appearance ) {
			if ( ! is_string( $appearance_id ) || ! is_array( $appearance ) || ! isset( $appearance['name'], $appearance['values'] ) || ! is_array( $appearance['values'] ) ) {
				continue;
			}
			$result[ $appearance_id ] = array(
				'name'    => strval( $appearance['name'] ),
				'builtin' => false,
				'values'  => self::sanitize_values( $appearance['values'] ),
			);
		}

		return $result;
	}

	/**
	 * Returns all appearances: the built-in ones first, then the custom ones.
	 *
	 * @phpstan-return array<string, Appearance>
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_all(): array {
		return array_merge( self::get_builtin(), self::get_custom() );
	}

	/**
	 * Returns an appearance, or null when it does not exist.
	 *
	 * @param string $appearance_id The ID of the appearance.
	 *
	 * @phpstan-return Appearance|null
	 * @return array<string, mixed>|null
	 */
	public static function get( string $appearance_id ) {
		return self::get_all()[ $appearance_id ] ?? null;
	}

	/**
	 * Returns the ID of the appearance of blocks without their own appearance.
	 */
	public static function get_default_id(): string {
		$appearance_id = get_option( self::DEFAULT_OPTION, self::THEME );

		return is_string( $appearance_id ) && ! is_null( self::get( $appearance_id ) ) ? $appearance_id : self::THEME;
	}

	/**
	 * Saves a custom appearance.
	 *
	 * @param string               $appearance_id The ID of the appearance.
	 * @param string               $name The name of the appearance.
	 * @param array<string, mixed> $values The values of the appearance.
	 *
	 * @return void
	 */
	public static function save( string $appearance_id, string $name, array $values ) {
		$stored                   = get_option( self::OPTION, array() );
		$stored                   = is_array( $stored ) ? $stored : array();
		$stored[ $appearance_id ] = array(
			'name'   => '' !== trim( $name ) ? trim( $name ) : __( 'My appearance', 'skautis-integration' ),
			'values' => self::sanitize_values( $values ),
		);
		update_option( self::OPTION, $stored, false );
	}

	/**
	 * Creates a custom appearance as a copy of another appearance.
	 *
	 * @param string $from The ID of the copied appearance.
	 *
	 * @return string The ID of the new appearance, or an empty string when the copied appearance does not exist.
	 */
	public static function copy( string $from ): string {
		$source = self::get( $from );
		if ( is_null( $source ) ) {
			return '';
		}
		$custom = self::get_custom();
		$number = count( $custom ) + 1;
		while ( isset( $custom[ 'custom-' . $number ] ) ) {
			++$number;
		}
		$appearance_id = 'custom-' . $number;
		/* translators: %s: The name of the copied appearance */
		self::save( $appearance_id, sprintf( __( '%s (copy)', 'skautis-integration' ), $source['name'] ), $source['values'] );

		return $appearance_id;
	}

	/**
	 * Deletes a custom appearance. Blocks with this appearance get the default appearance.
	 *
	 * @param string $appearance_id The ID of the appearance.
	 *
	 * @return void
	 */
	public static function delete( string $appearance_id ) {
		$stored = get_option( self::OPTION, array() );
		if ( is_array( $stored ) && isset( $stored[ $appearance_id ] ) ) {
			unset( $stored[ $appearance_id ] );
			update_option( self::OPTION, $stored, false );
		}
		if ( get_option( self::DEFAULT_OPTION ) === $appearance_id ) {
			update_option( self::DEFAULT_OPTION, self::THEME );
		}
	}

	/**
	 * Returns the valid values of an appearance: colors as #rrggbb or empty for the color of the theme, the radius as a number of pixels.
	 *
	 * @param array<mixed> $values The values.
	 *
	 * @phpstan-return Values
	 * @return array<string, string>
	 */
	public static function sanitize_values( array $values ): array {
		$result = array();
		foreach ( array_keys( self::FIELDS ) as $field ) {
			$value = isset( $values[ $field ] ) && is_scalar( $values[ $field ] ) ? trim( strval( $values[ $field ] ) ) : '';
			if ( 'radius' === $field ) {
				$result[ $field ] = strval( max( 0, min( 32, intval( $value ) ) ) );
			} elseif ( 1 === preg_match( '/^#[0-9a-f]{6}$/i', $value ) ) {
				$result[ $field ] = strtolower( $value );
			} else {
				$result[ $field ] = '';
			}
		}

		return $result;
	}

	/**
	 * Returns the inline style with the CSS custom properties of an appearance.
	 *
	 * @param string $appearance_id The ID of the appearance, an empty string for the default appearance.
	 */
	public static function get_style( string $appearance_id ): string {
		$appearance = self::get( '' !== $appearance_id ? $appearance_id : self::get_default_id() ) ?? self::get( self::THEME );
		if ( is_null( $appearance ) ) {
			return '';
		}

		$style = array();
		foreach ( self::FIELDS as $field => $property ) {
			$value = $appearance['values'][ $field ] ?? '';
			if ( '' === $value ) {
				continue;
			}
			$style[] = $property . ': ' . ( 'radius' === $field ? $value . 'px' : $value );
		}

		return implode( '; ', $style );
	}
}

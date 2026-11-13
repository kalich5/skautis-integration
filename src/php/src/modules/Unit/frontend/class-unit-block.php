<?php
/**
 * Contains the Unit_Block class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Modules\Unit\Frontend;

use Skautis_Integration\Modules\Recruitment\Appearances;
use Skautis_Integration\Modules\Recruitment\Frontend\Blocks;
use Skautis_Integration\Modules\Recruitment\Recruitment_Data;
use Skautis_Integration\Modules\Unit\Unit_Data;

/**
 * Registers and renders the "Unit" block with the public information about a unit from SkautIS.
 *
 * The block uses the cards, the map and the appearances of the recruitment blocks.
 *
 * @phan-constructor-used-for-side-effects
 */
final class Unit_Block {

	/**
	 * The name of the block.
	 */
	const BLOCK = 'skautis-integration/unit';

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
		// After the recruitment blocks, which register the shared scripts and styles.
		add_action( 'init', array( $this, 'register_block' ), 11 );
	}

	/**
	 * Registers the block.
	 *
	 * @return void
	 */
	public function register_block() {
		$attributes = array(
			'unit'       => array(
				'type'    => 'string',
				'default' => '',
			),
			'appearance' => array(
				'type'    => 'string',
				'default' => '',
			),
			'mapHeight'  => array(
				'type'    => 'number',
				'default' => 300,
			),
		);
		foreach ( Unit_Card::ITEMS as $item => $default ) {
			$attributes[ $item ] = array(
				'type'    => 'boolean',
				'default' => $default,
			);
		}
		// Kept without a default, so that the blocks from the first version keep what they showed.
		$attributes['showChildCards'] = array(
			'type'    => 'boolean',
			'default' => false,
		);
		foreach ( array_unique( Unit_Card::LEGACY_ITEMS ) as $legacy ) {
			$attributes[ $legacy ] = array( 'type' => 'boolean' );
		}

		register_block_type(
			self::BLOCK,
			array( // @phpstan-ignore argument.type (The WordPress stubs allow only strings as default values of attributes.)
				'api_version'           => '3',
				'title'                 => _x( 'Unit', 'block', 'skautis-integration' ),
				'description'           => __( 'The public information about a unit from SkautIS: the seat, the contacts, the statutory body, the bank account and the child units.', 'skautis-integration' ),
				'category'              => Blocks::CATEGORY,
				'icon'                  => 'id-alt',
				'attributes'            => $attributes,
				'supports'              => array(
					'align' => array( 'wide', 'full' ),
					'html'  => false,
				),
				'editor_script_handles' => array( SKAUTIS_INTEGRATION_NAME . '_modules_recruitment_blocks' ),
				'view_script_handles'   => array( SKAUTIS_INTEGRATION_NAME . '_modules_recruitment_map' ),
				'style_handles'         => array( SKAUTIS_INTEGRATION_NAME . '_leaflet', SKAUTIS_INTEGRATION_NAME . '_modules_recruitment' ),
				'render_callback'       => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Returns the HTML code of the map of the seat.
	 *
	 * @param array{name: string, address: string, lat: float, lng: float} $unit The unit.
	 * @param array<string, mixed>                                         $attributes The attributes of the block.
	 */
	private static function get_map( array $unit, array $attributes ): string {
		if ( ! Unit_Card::shows( $attributes, 'showMap' ) || 0.0 === $unit['lat'] || 0.0 === $unit['lng'] ) {
			return '';
		}
		$height = isset( $attributes['mapHeight'] ) && is_numeric( $attributes['mapHeight'] ) ? max( 150, min( 800, intval( $attributes['mapHeight'] ) ) ) : 300;
		if ( Blocks::is_editor_preview() ) {
			return '<div class="skautis-unit-map-preview" style="height: ' . esc_attr( (string) $height ) . 'px"><p>' . esc_html__( 'Map of the seat. The map is shown on the site.', 'skautis-integration' ) . '</p></div>';
		}
		$data = array(
			'markers' => array(
				array(
					'lat'   => $unit['lat'],
					'lng'   => $unit['lng'],
					'water' => false,
					'popup' => '<div class="skautis-map-unit"><strong>' . esc_html( $unit['name'] ) . '</strong><br>' . esc_html( $unit['address'] ) . '</div>',
				),
			),
			'tiles'   => Blocks::get_tiles(),
		);

		return '<div class="skautis-unit-map skautis-unit-seat-map"><div class="skautis-unit-map-canvas" style="height: ' . esc_attr( (string) $height ) . 'px;" data-skautis-map="' . esc_attr( (string) wp_json_encode( $data ) ) . '"></div></div>';
	}

	/**
	 * Renders the block.
	 *
	 * @param array<string, mixed> $attributes The attributes of the block.
	 *
	 * @return string The HTML code of the block.
	 */
	public function render( array $attributes ): string {
		$number = isset( $attributes['unit'] ) ? Recruitment_Data::sanitize_number( $attributes['unit'] ) : '';
		$unit   = $this->unit_data->get_data( $number )['unit'];
		if ( is_null( $unit ) ) {
			return Blocks::is_editor_preview() ? '<p class="skautis-recruitment-empty">' . esc_html__( 'The unit could not be loaded from SkautIS. Fill in its registration number on the "Unit" page in the SkautIS menu or in the settings of the block.', 'skautis-integration' ) . '</p>' : '';
		}

		return '<div ' . get_block_wrapper_attributes(
			array(
				'class' => 'skautis-unit-info',
				'style' => Blocks::get_appearance_style( $attributes, Appearances::UNIT ),
			)
		) . '>' . Unit_Card::render( $unit, $attributes, self::get_map( $unit, $attributes ) ) . $this->get_child_cards( $unit, $attributes ) . '</div>';
	}

	/**
	 * Returns the HTML code of the cards of the child units, e.g. of the groups (střediska) of a district, with the same items as the card of the unit, without the maps.
	 *
	 * @param array{units: array<array{name: string, registration_number: string}>} $unit The unit.
	 * @param array<string, mixed>                                                  $attributes The attributes of the block.
	 */
	private function get_child_cards( array $unit, array $attributes ): string {
		if ( ! isset( $attributes['showChildCards'] ) || true !== $attributes['showChildCards'] ) {
			return '';
		}
		$child_attributes = array_merge(
			$attributes,
			array(
				'showMap'   => false,
				'showUnits' => false,
			)
		);
		$html             = '';
		foreach ( $unit['units'] as $child ) {
			if ( '' === $child['registration_number'] ) {
				continue;
			}
			$child_unit = $this->unit_data->get_data( $child['registration_number'] )['unit'];
			if ( ! is_null( $child_unit ) ) {
				$html .= Unit_Card::render( $child_unit, $child_attributes );
			}
		}

		return '' !== $html ? '<div class="skautis-unit-children"><h2 class="skautis-recruitment-group-title">' . esc_html__( 'Units', 'skautis-integration' ) . '</h2>' . $html . '</div>' : '';
	}
}

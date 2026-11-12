<?php
/**
 * Contains the Unit_Block class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Modules\Unit\Frontend;

use Skautis_Integration\Modules\Recruitment\Frontend\Blocks;
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
	 * The sections of the block in the form `attribute => shown by default`.
	 */
	const SECTIONS = array(
		'showSeat'     => true,
		'showMap'      => true,
		'showContacts' => true,
		'showPeople'   => true,
		'showAccounts' => false,
		'showUnits'    => false,
	);

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
		foreach ( self::SECTIONS as $section => $default ) {
			$attributes[ $section ] = array(
				'type'    => 'boolean',
				'default' => $default,
			);
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
	 * Checks whether a section of the block is shown.
	 *
	 * @param array<string, mixed> $attributes The attributes of the block.
	 * @param string               $section The attribute of the section.
	 */
	private static function shows( array $attributes, string $section ): bool {
		return isset( $attributes[ $section ] ) ? true === $attributes[ $section ] : self::SECTIONS[ $section ];
	}

	/**
	 * Returns the HTML code of a contact: a link for an e-mail, a phone number or a web address.
	 *
	 * @param array{type: string, value: string, note: string} $contact The contact.
	 */
	private static function get_contact( array $contact ): string {
		$value = $contact['value'];
		if ( false !== is_email( $value ) ) {
			$link = '<a href="' . esc_url( 'mailto:' . $value ) . '">' . esc_html( $value ) . '</a>';
		} elseif ( 1 === preg_match( '~^(https?://|www\.)~i', $value ) ) {
			$link = '<a href="' . esc_url( 0 === stripos( $value, 'www.' ) ? 'https://' . $value : $value ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $value ) . '</a>';
		} elseif ( 1 === preg_match( '~^\+?[0-9 ]{9,}$~', $value ) ) {
			$link = '<a href="' . esc_url( 'tel:' . str_replace( ' ', '', $value ) ) . '">' . esc_html( $value ) . '</a>';
		} else {
			$link = esc_html( $value );
		}
		$label = '' !== $contact['type'] ? '<span class="skautis-unit-label">' . esc_html( $contact['type'] ) . ':</span> ' : '';
		$note  = '' !== $contact['note'] ? ' <span class="skautis-unit-note">(' . esc_html( $contact['note'] ) . ')</span>' : '';

		return '<dd>' . $label . $link . $note . '</dd>';
	}

	/**
	 * Returns the HTML code of the sections of the details of the unit.
	 *
	 * @param array{id: int, name: string, registration_number: string, ic: string, address: string, lat: float, lng: float, contacts: array<array{type: string, value: string, note: string}>, people: array<array{function: string, name: string}>, accounts: array<array{name: string, number: string}>, units: array<array{name: string, registration_number: string}>} $unit The unit.
	 * @param array<string, mixed>                                                                                                                                                                                                                                                                                                                                          $attributes The attributes of the block.
	 */
	private static function get_details( array $unit, array $attributes ): string {
		$html = '';
		if ( self::shows( $attributes, 'showSeat' ) ) {
			if ( '' !== $unit['address'] ) {
				$html .= '<dt>' . esc_html__( 'Seat', 'skautis-integration' ) . '</dt><dd>' . esc_html( $unit['address'] ) . '</dd>';
			}
			$html .= '<dt>' . esc_html__( 'Registration number', 'skautis-integration' ) . '</dt><dd>' . esc_html( $unit['registration_number'] ) . '</dd>';
			if ( '' !== $unit['ic'] ) {
				$html .= '<dt>' . esc_html__( 'Company ID (IČ)', 'skautis-integration' ) . '</dt><dd>' . esc_html( $unit['ic'] ) . '</dd>';
			}
		}
		if ( self::shows( $attributes, 'showContacts' ) && count( $unit['contacts'] ) > 0 ) {
			$html .= '<dt>' . esc_html__( 'Contacts', 'skautis-integration' ) . '</dt>';
			foreach ( $unit['contacts'] as $contact ) {
				$html .= self::get_contact( $contact );
			}
		}
		if ( self::shows( $attributes, 'showPeople' ) && count( $unit['people'] ) > 0 ) {
			$html .= '<dt>' . esc_html__( 'Statutory body', 'skautis-integration' ) . '</dt>';
			foreach ( $unit['people'] as $person ) {
				$html .= '<dd>' . ( '' !== $person['function'] ? '<span class="skautis-unit-label">' . esc_html( $person['function'] ) . ':</span> ' : '' ) . esc_html( $person['name'] ) . '</dd>';
			}
		}
		if ( self::shows( $attributes, 'showAccounts' ) && count( $unit['accounts'] ) > 0 ) {
			$html .= '<dt>' . esc_html( _n( 'Bank account', 'Bank accounts', count( $unit['accounts'] ), 'skautis-integration' ) ) . '</dt>';
			foreach ( $unit['accounts'] as $account ) {
				$html .= '<dd>' . esc_html( $account['number'] ) . ( '' !== $account['name'] ? ' <span class="skautis-unit-note">(' . esc_html( $account['name'] ) . ')</span>' : '' ) . '</dd>';
			}
		}
		if ( self::shows( $attributes, 'showUnits' ) && count( $unit['units'] ) > 0 ) {
			$html .= '<dt>' . esc_html__( 'Units', 'skautis-integration' ) . '</dt>';
			foreach ( $unit['units'] as $child ) {
				$html .= '<dd>' . esc_html( trim( $child['name'] . ( '' !== $child['registration_number'] ? ' (' . $child['registration_number'] . ')' : '' ) ) ) . '</dd>';
			}
		}

		return '' !== $html ? '<dl class="skautis-recruitment-details">' . $html . '</dl>' : '';
	}

	/**
	 * Returns the HTML code of the map of the seat.
	 *
	 * @param array{name: string, address: string, lat: float, lng: float} $unit The unit.
	 * @param array<string, mixed>                                         $attributes The attributes of the block.
	 */
	private static function get_map( array $unit, array $attributes ): string {
		if ( ! self::shows( $attributes, 'showMap' ) || 0.0 === $unit['lat'] || 0.0 === $unit['lng'] ) {
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
		$number = isset( $attributes['unit'] ) && is_string( $attributes['unit'] ) ? (string) preg_replace( '/[^0-9.]/u', '', $attributes['unit'] ) : '';
		$unit   = $this->unit_data->get_data( $number )['unit'];
		if ( is_null( $unit ) ) {
			return Blocks::is_editor_preview() ? '<p class="skautis-recruitment-empty">' . esc_html__( 'The unit could not be loaded from SkautIS. Fill in its registration number on the "Unit" page in the SkautIS menu or in the settings of the block.', 'skautis-integration' ) . '</p>' : '';
		}

		return '<div ' . get_block_wrapper_attributes(
			array(
				'class' => 'skautis-unit-info',
				'style' => Blocks::get_appearance_style( $attributes ),
			)
		) . '><section class="skautis-recruitment-unit"><h3>' . esc_html( $unit['name'] ) . '</h3>'
			. self::get_details( $unit, $attributes )
			. self::get_map( $unit, $attributes )
			. '</section></div>';
	}
}

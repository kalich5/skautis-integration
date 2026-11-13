<?php
/**
 * Contains the Blocks class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Modules\Recruitment\Frontend;

use Skautis_Integration\Admin\Maps_Settings;
use PHPMD\Attribute\SuppressWarnings;
use PHPMD\Rule\Design\ExcessiveClassComplexity;
use Skautis_Integration\Modules\Recruitment\Appearances;
use Skautis_Integration\Modules\Recruitment\Recruitment_Data;
use Skautis_Integration\Utils\Helpers;

/**
 * Registers and renders the "Recruitment" and "Map of the units" blocks.
 *
 * Both blocks are rendered on the server from the data loaded from SkautIS, the map is drawn by Leaflet in the browser.
 *
 * @phan-constructor-used-for-side-effects
 */
#[SuppressWarnings( ExcessiveClassComplexity::class )]
final class Blocks {

	/**
	 * The category of the blocks of the plugin in the block inserter.
	 */
	const CATEGORY = 'skautis-integration';

	/**
	 * The name of the recruitment block.
	 */
	const RECRUITMENT_BLOCK = 'skautis-integration/recruitment';

	/**
	 * The name of the map block.
	 */
	const MAP_BLOCK = 'skautis-integration/unit-map';

	/**
	 * The attributes of the recruitment blocks with the unit of the block (empty for the unit of the site) and the chosen units below it (empty for all).
	 */
	const UNIT_ATTRIBUTES = array(
		'unit'  => array(
			'type'    => 'string',
			'default' => '',
		),
		'units' => array(
			'type'    => 'array',
			'items'   => array( 'type' => 'integer' ),
			'default' => array(),
		),
	);

	/**
	 * The parts of the cards of the "Recruitment" block in the form `attribute => shown by default`.
	 */
	const RECRUITMENT_ITEMS = array(
		'showName'               => true,
		'showRegistrationNumber' => false,
		'showWater'              => true,
		'showCategories'         => true,
		'showMeetings'           => true,
		'showClubrooms'          => true,
		'showNote'               => true,
	);

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
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
		add_filter( 'block_categories_all', array( self::class, 'add_block_category' ) );
	}

	/**
	 * Adds the category of the blocks of the plugin, so that they are together in the block inserter.
	 *
	 * @param array<array<string, mixed>> $categories The block categories.
	 *
	 * @return array<array<string, mixed>> The block categories with the category of the plugin.
	 */
	public static function add_block_category( array $categories ): array {
		$categories[] = array(
			'slug'  => self::CATEGORY,
			'title' => __( 'SkautIS integration', 'skautis-integration' ),
			'icon'  => null,
		);

		return $categories;
	}

	/**
	 * Registers the REST route with the units of the recruitment, for the choice of the units in the settings of the blocks.
	 *
	 * @return void
	 */
	public function register_rest_route() {
		register_rest_route(
			SKAUTIS_INTEGRATION_NAME . '/v1',
			'/recruitment-units',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_rest_units' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'unit' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Returns the units with recruitment information of a unit, for the REST route.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return array<array{id: int, name: string, registration_number: string, group: string}>
	 */
	public function get_rest_units( $request ): array {
		$units = array();
		foreach ( $this->recruitment_data->get_units( Recruitment_Data::sanitize_number( $request->get_param( 'unit' ) ) ) as $unit ) {
			$units[] = array(
				'id'                  => $unit['id'],
				'name'                => $unit['name'],
				'registration_number' => $unit['registration_number'],
				'group'               => $unit['group_name'],
			);
		}

		return $units;
	}

	/**
	 * Registers the scripts, the styles and the blocks.
	 *
	 * @return void
	 */
	public function register_blocks() {
		Helpers::register_script( 'leaflet', 'bundled/leaflet/leaflet.js' );
		Helpers::register_style( 'leaflet', 'bundled/leaflet/leaflet.css' );
		Helpers::register_script( 'modules_recruitment_map', 'modules/Recruitment/frontend/js/skautis-modules-recruitment-map.min.js', array( SKAUTIS_INTEGRATION_NAME . '_leaflet', 'wp-dom-ready' ) );
		Helpers::register_script( 'modules_recruitment_filter', 'modules/Recruitment/frontend/js/skautis-modules-recruitment-filter.min.js', array( 'wp-dom-ready' ) );
		Helpers::register_style( 'modules_recruitment', 'modules/Recruitment/frontend/css/skautis-modules-recruitment.min.css' );
		Helpers::register_script( 'modules_recruitment_blocks', 'modules/Recruitment/admin/js/skautis-modules-recruitment-blocks.min.js', array( 'wp-api-fetch', 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render', 'wp-url' ) );
		wp_set_script_translations( SKAUTIS_INTEGRATION_NAME . '_modules_recruitment_blocks', 'skautis-integration' );
		wp_add_inline_script( SKAUTIS_INTEGRATION_NAME . '_modules_recruitment_blocks', 'var skautisRecruitmentAppearances = ' . wp_json_encode( self::get_appearance_options( Appearances::RECRUITMENT ) ) . ', skautisUnitAppearances = ' . wp_json_encode( self::get_appearance_options( Appearances::UNIT ) ) . ';', 'before' );

		$recruitment_attributes = array_merge(
			self::UNIT_ATTRIBUTES,
			array(
				'appearance' => array(
					'type'    => 'string',
					'default' => '',
				),
				'showGroups' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showFilter' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			)
		);
		foreach ( self::RECRUITMENT_ITEMS as $item => $default ) {
			$recruitment_attributes[ $item ] = array(
				'type'    => 'boolean',
				'default' => $default,
			);
		}

		register_block_type(
			self::RECRUITMENT_BLOCK,
			array( // @phpstan-ignore argument.type (The WordPress stubs allow only strings as default values of attributes.)
				'api_version'           => '3',
				'title'                 => __( 'Recruitment', 'skautis-integration' ),
				'description'           => __( 'The recruitment information of the units from SkautIS: for whom, meetings and clubrooms.', 'skautis-integration' ),
				'category'              => self::CATEGORY,
				'icon'                  => 'groups',
				'attributes'            => $recruitment_attributes,
				'supports'              => array(
					'align' => array( 'wide', 'full' ),
					'html'  => false,
				),
				'editor_script_handles' => array( SKAUTIS_INTEGRATION_NAME . '_modules_recruitment_blocks' ),
				'view_script_handles'   => array( SKAUTIS_INTEGRATION_NAME . '_modules_recruitment_filter' ),
				'style_handles'         => array( SKAUTIS_INTEGRATION_NAME . '_modules_recruitment' ),
				'render_callback'       => array( $this, 'render_recruitment' ),
			)
		);

		register_block_type(
			self::MAP_BLOCK,
			array( // @phpstan-ignore argument.type (The WordPress stubs allow only strings as default values of attributes.)
				'api_version'           => '3',
				'title'                 => __( 'Map of the units', 'skautis-integration' ),
				'description'           => __( 'A map with the clubrooms of the units from SkautIS and their recruitment information.', 'skautis-integration' ),
				'category'              => self::CATEGORY,
				'icon'                  => 'location-alt',
				'attributes'            => array_merge(
					self::UNIT_ATTRIBUTES,
					array(
						'height'     => array(
							'type'    => 'number',
							'default' => 400,
						),
						'appearance' => array(
							'type'    => 'string',
							'default' => '',
						),
					)
				),
				'supports'              => array(
					'align' => array( 'wide', 'full' ),
					'html'  => false,
				),
				'editor_script_handles' => array( SKAUTIS_INTEGRATION_NAME . '_modules_recruitment_blocks' ),
				'view_script_handles'   => array( SKAUTIS_INTEGRATION_NAME . '_modules_recruitment_map' ),
				'style_handles'         => array( SKAUTIS_INTEGRATION_NAME . '_leaflet', SKAUTIS_INTEGRATION_NAME . '_modules_recruitment' ),
				'render_callback'       => array( $this, 'render_map' ),
			)
		);
	}

	/**
	 * Returns the appearances for the select in the block settings.
	 *
	 * @param string $context The blocks: Appearances::RECRUITMENT or Appearances::UNIT.
	 *
	 * @return array<array{value: string, label: string}> The appearances, the first one is the default appearance of the blocks.
	 */
	private static function get_appearance_options( string $context ): array {
		$default = Appearances::get( Appearances::get_default_id( $context ) );
		$options = array(
			array(
				'value' => '',
				/* translators: %s: The name of the default appearance */
				'label' => sprintf( __( 'Default (%s)', 'skautis-integration' ), is_null( $default ) ? '' : $default['name'] ),
			),
		);
		foreach ( Appearances::get_all() as $id => $appearance ) {
			$options[] = array(
				'value' => $id,
				'label' => $appearance['name'],
			);
		}

		return $options;
	}

	/**
	 * Returns the inline style of a block with the CSS custom properties of its appearance.
	 *
	 * @param array<string, mixed> $attributes The attributes of the block.
	 * @param string               $context The blocks, for the default appearance: Appearances::RECRUITMENT or Appearances::UNIT.
	 */
	public static function get_appearance_style( array $attributes, string $context = Appearances::RECRUITMENT ): string {
		return Appearances::get_style( isset( $attributes['appearance'] ) && is_string( $attributes['appearance'] ) ? $attributes['appearance'] : '', $context );
	}

	/**
	 * Checks whether the block is rendered for the preview in the block editor.
	 */
	public static function is_editor_preview(): bool {
		return defined( 'REST_REQUEST' ) && true === REST_REQUEST;
	}

	/**
	 * Returns the notice shown in the block editor when there is nothing to show.
	 *
	 * @param string $number The registration number of the unit of the block, or an empty string for the unit of the site.
	 */
	private static function get_empty_notice( string $number = '' ): string {
		if ( ! self::is_editor_preview() ) {
			return '';
		}
		$message = '' === $number && '' === Recruitment_Data::get_unit_number()
			? __( 'Fill in the registration number of the unit on the "Recruitment" page in the SkautIS menu.', 'skautis-integration' )
			: __( 'No recruitment information could be loaded from SkautIS. See the "Recruitment" page in the SkautIS menu.', 'skautis-integration' );

		return '<p class="skautis-recruitment-empty">' . esc_html( $message ) . '</p>';
	}

	/**
	 * Returns the label of a recruitment category, e.g. "Boys 7–11 years".
	 *
	 * @param array{age_from: int, age_to: int, sex: string, note: string, meeting: int} $category The category.
	 */
	private static function get_category_label( array $category ): string {
		switch ( $category['sex'] ) {
			case 'male':
				$sex = __( 'Boys', 'skautis-integration' );
				break;
			case 'female':
				$sex = __( 'Girls', 'skautis-integration' );
				break;
			default:
				$sex = __( 'Boys and girls', 'skautis-integration' );
		}

		$age = '';
		if ( $category['age_from'] > 0 && $category['age_to'] > 0 ) {
			/* translators: 1: The minimal age 2: The maximal age */
			$age = sprintf( __( '%1$d–%2$d years', 'skautis-integration' ), $category['age_from'], $category['age_to'] );
		} elseif ( $category['age_from'] > 0 ) {
			/* translators: %d: The minimal age */
			$age = sprintf( __( 'from %d years', 'skautis-integration' ), $category['age_from'] );
		} elseif ( $category['age_to'] > 0 ) {
			/* translators: %d: The maximal age */
			$age = sprintf( __( 'up to %d years', 'skautis-integration' ), $category['age_to'] );
		}

		return trim( $sex . ' ' . $age );
	}

	/**
	 * Returns the description of a meeting, e.g. "Tuesday 16:30–18:00, every week (Boys 7–11 years)".
	 *
	 * @param array{id: int, day: string, day_order: int, time_from: string, time_to: string, periodicity: string, place: int, place_name: string} $meeting The meeting.
	 * @param array<array{age_from: int, age_to: int, sex: string, note: string, meeting: int}>                                                    $categories The categories of the unit.
	 */
	private static function get_meeting_label( array $meeting, array $categories ): string {
		$time  = implode( '–', array_filter( array( $meeting['time_from'], $meeting['time_to'] ) ) );
		$label = trim( $meeting['day'] . ' ' . $time );
		// The periodicity is shown only when it is a text, not an ID.
		if ( '' !== $meeting['periodicity'] && ! is_numeric( $meeting['periodicity'] ) ) {
			$label .= ', ' . $meeting['periodicity'];
		}

		$for = array();
		foreach ( $categories as $category ) {
			if ( $meeting['id'] > 0 && $category['meeting'] === $meeting['id'] ) {
				$for[] = self::get_category_label( $category );
			}
		}
		if ( count( $for ) > 0 ) {
			$label .= ' (' . implode( ', ', $for ) . ')';
		}

		return $label;
	}

	/**
	 * Returns the name and the address of a clubroom.
	 *
	 * @param array{id: int, name: string, address: string, lat: float, lng: float} $place The clubroom.
	 */
	private static function get_place_label( array $place ): string {
		return implode( ', ', array_filter( array( $place['name'], $place['address'] ) ) );
	}

	/**
	 * Checks whether a part of the cards of the "Recruitment" block is shown.
	 *
	 * @param array<string, mixed> $attributes The attributes of the block.
	 * @param string               $item The attribute of the part.
	 */
	private static function shows( array $attributes, string $item ): bool {
		return isset( $attributes[ $item ] ) ? true === $attributes[ $item ] : self::RECRUITMENT_ITEMS[ $item ];
	}

	/**
	 * Returns the HTML code of the labels of the unit: water troop and the recruitment categories.
	 *
	 * @param array{is_water: bool, categories: array<array{age_from: int, age_to: int, sex: string, note: string, meeting: int}>} $unit The unit.
	 * @param array<string, mixed>                                                                                                 $attributes The attributes of the block.
	 */
	private static function get_tags( array $unit, array $attributes ): string {
		$tags = array();
		if ( $unit['is_water'] && self::shows( $attributes, 'showWater' ) ) {
			$tags[] = '<li class="is-water">' . esc_html__( 'Water troop', 'skautis-integration' ) . '</li>';
		}
		$categories = self::shows( $attributes, 'showCategories' ) ? $unit['categories'] : array();
		foreach ( array_unique( array_map( array( self::class, 'get_category_label' ), $categories ) ) as $label ) {
			$tags[] = '<li>' . esc_html( $label ) . '</li>';
		}

		return count( $tags ) > 0 ? '<ul class="skautis-recruitment-tags">' . implode( '', $tags ) . '</ul>' : '';
	}

	/**
	 * Renders the "Recruitment" block.
	 *
	 * @param array<string, mixed> $attributes The attributes of the block.
	 *
	 * @return string The HTML code of the block.
	 */
	public function render_recruitment( array $attributes ): string {
		$number = Recruitment_List::get_number( $attributes );
		$units  = Recruitment_List::select( $this->recruitment_data->get_units( $number ), $attributes );
		if ( count( $units ) === 0 ) {
			return self::get_empty_notice( $number );
		}

		return '<div ' . get_block_wrapper_attributes(
			array(
				'class' => 'skautis-recruitment',
				'style' => self::get_appearance_style( $attributes ),
			)
		) . '>' . Recruitment_List::render( $units, $attributes ) . '</div>';
	}

	/**
	 * Returns the HTML code of the cards of the units, used by the block and by the preview of the appearances.
	 *
	 * @param array<array{id: int, name: string, registration_number: string, is_water: bool, note: string, categories: array<array{age_from: int, age_to: int, sex: string, note: string, meeting: int}>, meetings: array<array{id: int, day: string, day_order: int, time_from: string, time_to: string, periodicity: string, place: int, place_name: string}>, places: array<array{id: int, name: string, address: string, lat: float, lng: float}>}> $units The units.
	 * @param array<string, mixed>                                                                                                                                                                                                                                                                                                                                                                                                                       $attributes The attributes of the block, which choose the shown parts of the cards.
	 */
	public static function render_units( array $units, array $attributes = array() ): string {
		$html = '';
		foreach ( $units as $unit ) {
			// The categories in the form "7-11-male", for the filter of the visitors.
			$categories = array();
			foreach ( $unit['categories'] as $category ) {
				$categories[] = $category['age_from'] . '-' . $category['age_to'] . '-' . ( '' !== $category['sex'] ? $category['sex'] : 'any' );
			}
			$html .= '<section class="skautis-recruitment-unit" id="skautis-unit-' . esc_attr( (string) $unit['id'] ) . '" data-skautis-categories="' . esc_attr( implode( ' ', $categories ) ) . '">';
			$name  = self::shows( $attributes, 'showName' ) ? $unit['name'] : '';
			if ( self::shows( $attributes, 'showRegistrationNumber' ) && '' !== $unit['registration_number'] ) {
				$name = '' !== $name ? $name . ' (' . $unit['registration_number'] . ')' : $unit['registration_number'];
			}
			if ( '' !== $name ) {
				$html .= '<h3>' . esc_html( $name ) . '</h3>';
			}
			$html   .= self::get_tags( $unit, $attributes );
			$details = '';
			if ( self::shows( $attributes, 'showMeetings' ) && count( $unit['meetings'] ) > 0 ) {
				$details .= '<dt>' . esc_html__( 'Meetings', 'skautis-integration' ) . '</dt>';
				foreach ( $unit['meetings'] as $meeting ) {
					$details .= '<dd>' . esc_html( self::get_meeting_label( $meeting, $unit['categories'] ) ) . '</dd>';
				}
			}
			$places = self::shows( $attributes, 'showClubrooms' ) ? array_filter( array_map( array( self::class, 'get_place_label' ), $unit['places'] ) ) : array();
			if ( count( $places ) > 0 ) {
				$details .= '<dt>' . esc_html( _n( 'Clubroom', 'Clubrooms', count( $places ), 'skautis-integration' ) ) . '</dt>';
				foreach ( $places as $place ) {
					$details .= '<dd>' . esc_html( $place ) . '</dd>';
				}
			}
			if ( '' !== $details ) {
				$html .= '<dl class="skautis-recruitment-details">' . $details . '</dl>';
			}
			if ( self::shows( $attributes, 'showNote' ) && '' !== $unit['note'] ) {
				$html .= '<div class="skautis-recruitment-note">' . wpautop( esc_html( $unit['note'] ) ) . '</div>';
			}
			$html .= '</section>';
		}

		return $html;
	}

	/**
	 * Returns the settings of the map tiles: Mapy.com with the API key, otherwise OpenStreetMap.
	 *
	 * @return array{url: string, attribution: string, logo: bool, maxZoom: int}
	 */
	public static function get_tiles(): array {
		$key = Maps_Settings::get_mapy_api_key();
		if ( '' !== $key ) {
			return array(
				'url'         => 'https://api.mapy.com/v1/maptiles/basic/256/{z}/{x}/{y}?apikey=' . rawurlencode( $key ),
				'attribution' => '<a href="https://api.mapy.com/copyright" target="_blank" rel="noopener noreferrer">&copy; ' . esc_html__( 'Seznam.cz a.s. and others', 'skautis-integration' ) . '</a>',
				'logo'        => true,
				'maxZoom'     => 19,
			);
		}

		return array(
			'url'         => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
			'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">' . esc_html__( 'OpenStreetMap contributors', 'skautis-integration' ) . '</a>',
			'logo'        => false,
			'maxZoom'     => 19,
		);
	}

	/**
	 * Returns the markers of the map. Units sharing a clubroom share its marker.
	 *
	 * @param array<string, mixed> $attributes The attributes of the block, which choose the unit and the units below it.
	 *
	 * @return array<array{lat: float, lng: float, water: bool, popup: string}>
	 */
	private function get_markers( array $attributes ): array {
		$link    = has_block( self::RECRUITMENT_BLOCK );
		$markers = array();
		foreach ( Recruitment_List::select( $this->recruitment_data->get_units( Recruitment_List::get_number( $attributes ) ), $attributes ) as $unit ) {
			foreach ( $unit['places'] as $place ) {
				if ( 0.0 === $place['lat'] || 0.0 === $place['lng'] ) {
					continue;
				}
				$key = round( $place['lat'], 5 ) . ',' . round( $place['lng'], 5 );
				if ( ! isset( $markers[ $key ] ) ) {
					$markers[ $key ] = array(
						'lat'   => $place['lat'],
						'lng'   => $place['lng'],
						'water' => false,
						'popup' => '<p class="skautis-map-place">' . esc_html( self::get_place_label( $place ) ) . '</p>',
					);
				}
				if ( true === $unit['is_water'] ) {
					$markers[ $key ]['water'] = true;
				}

				$popup = '<div class="skautis-map-unit"><strong>' . esc_html( $unit['name'] ) . '</strong>';
				foreach ( $unit['categories'] as $category ) {
					$popup .= '<br>' . esc_html( self::get_category_label( $category ) );
				}
				foreach ( $unit['meetings'] as $meeting ) {
					if ( 0 === $meeting['place'] || $meeting['place'] === $place['id'] ) {
						$popup .= '<br>' . esc_html( self::get_meeting_label( $meeting, array() ) );
					}
				}
				if ( $link ) {
					$popup .= '<br><a href="#skautis-unit-' . esc_attr( (string) $unit['id'] ) . '">' . esc_html__( 'More about the recruitment', 'skautis-integration' ) . '</a>';
				}
				$markers[ $key ]['popup'] .= $popup . '</div>';
			}
		}

		return array_values( $markers );
	}

	/**
	 * Renders the "Map of the units" block.
	 *
	 * @param array<string, mixed> $attributes The attributes of the block.
	 *
	 * @return string The HTML code of the block.
	 */
	public function render_map( array $attributes ): string {
		$markers = $this->get_markers( $attributes );
		if ( count( $markers ) === 0 ) {
			return self::get_empty_notice( Recruitment_List::get_number( $attributes ) );
		}
		$height = isset( $attributes['height'] ) && is_numeric( $attributes['height'] ) ? max( 200, min( 1000, intval( $attributes['height'] ) ) ) : 400;

		if ( self::is_editor_preview() ) {
			/* translators: %d: The number of the clubrooms */
			$text = sprintf( _n( 'Map of the units with %d clubroom. The map is shown on the site.', 'Map of the units with %d clubrooms. The map is shown on the site.', count( $markers ), 'skautis-integration' ), count( $markers ) );

			return '<div ' . get_block_wrapper_attributes(
				array(
					'class' => 'skautis-unit-map-preview',
					'style' => 'height: ' . $height . 'px',
				)
			) . '><p>' . esc_html( $text ) . '</p></div>';
		}

		$data = array(
			'markers' => $markers,
			'tiles'   => self::get_tiles(),
		);

		return '<div ' . get_block_wrapper_attributes(
			array(
				'class' => 'skautis-unit-map',
				'style' => self::get_appearance_style( $attributes ),
			)
		) . '><div class="skautis-unit-map-canvas" style="height: ' . esc_attr( (string) $height ) . 'px;" data-skautis-map="' . esc_attr( (string) wp_json_encode( $data ) ) . '"></div></div>';
	}
}

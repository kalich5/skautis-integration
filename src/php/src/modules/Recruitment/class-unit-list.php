<?php
/**
 * Contains the Unit_List class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Modules\Recruitment;

use Skautis_Integration\Auth\Skautis_Gateway;
use Skautis_Integration\Modules\Unit\Unit_Data;

/**
 * The list of the units below the unit of the site, for the choice of the unit in the settings of the blocks.
 *
 * The recruitment blocks use the unit from the "Recruitment" page, the "Unit" block the unit from the "Unit" page. The list always shows the registration numbers, and the names when they are turned on on the page of the module.
 *
 * @phan-constructor-used-for-side-effects
 */
final class Unit_List {

	/**
	 * The option that turns on the names of the units in the list of the recruitment blocks.
	 */
	const RECRUITMENT_NAMES_OPTION = SKAUTIS_INTEGRATION_NAME . '_recruitment_list_names';

	/**
	 * The option that turns on the names of the units in the list of the "Unit" block.
	 */
	const UNIT_NAMES_OPTION = SKAUTIS_INTEGRATION_NAME . '_unit_list_names';

	/**
	 * The prefix of the transients with the lists, followed by the hash of the registration number.
	 */
	const CACHE = SKAUTIS_INTEGRATION_NAME . '_unit_list_';

	/**
	 * The option with the registration numbers of the units whose lists are stored.
	 */
	const CACHED_NUMBERS_OPTION = SKAUTIS_INTEGRATION_NAME . '_unit_list_cached';

	/**
	 * A link to the Skautis_Gateway service instance.
	 *
	 * @var Skautis_Gateway
	 */
	private $skautis_gateway;

	/**
	 * Constructs the service and saves all dependencies.
	 *
	 * @param Skautis_Gateway $skautis_gateway An injected Skautis_Gateway service instance.
	 */
	public function __construct( Skautis_Gateway $skautis_gateway ) {
		$this->skautis_gateway = $skautis_gateway;
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
	}

	/**
	 * Deletes the stored lists, e.g. after the unit of the site is changed.
	 *
	 * @return void
	 */
	public static function clear_cache() {
		$numbers = get_option( self::CACHED_NUMBERS_OPTION, array() );
		foreach ( is_array( $numbers ) ? $numbers : array() as $number ) {
			delete_transient( self::CACHE . md5( strval( $number ) ) );
		}
		delete_option( self::CACHED_NUMBERS_OPTION );
	}

	/**
	 * Adds the setting of the names in the list to the page of a module.
	 *
	 * @param string $page The settings page and group of the module.
	 * @param string $option The option, Unit_List::RECRUITMENT_NAMES_OPTION or Unit_List::UNIT_NAMES_OPTION.
	 *
	 * @return void
	 */
	public static function add_names_setting( string $page, string $option ) {
		// The pages of the modules have a section of the same name.
		$section = $page;
		add_settings_field(
			$option,
			__( 'List of the units in the blocks', 'skautis-integration' ),
			static function () use ( $option ) {
				echo '<label for="' . esc_attr( $option ) . '"><input type="checkbox" id="' . esc_attr( $option ) . '" name="' . esc_attr( $option ) . '" value="1"' . checked( '1', get_option( $option, '0' ), false ) . ' /> ' . esc_html__( 'Show the names of the units, not only their registration numbers', 'skautis-integration' ) . '</label>';
				echo '<p class="description">' . esc_html__( 'In the settings of a block, the unit is chosen from the list of the units below the unit set above.', 'skautis-integration' ) . '</p>';
			},
			$page,
			$section
		);
		register_setting(
			$page,
			$option,
			array(
				'type'              => 'string',
				'show_in_rest'      => false,
				'sanitize_callback' => static function ( $value ): string {
					return '1' === $value ? '1' : '0';
				},
			)
		);
	}

	/**
	 * Returns the units of the tree of a unit, from the cache or from SkautIS.
	 *
	 * @param string $number The registration number of the unit.
	 *
	 * @return array<array{number: string, name: string, depth: int}> The units in the order of the tree.
	 */
	public function get_units( string $number ): array {
		if ( '' === $number || ! $this->skautis_gateway->is_initialized() ) {
			return array();
		}
		$cached = get_transient( self::CACHE . md5( $number ) );
		if ( is_array( $cached ) ) {
			/**
			 * The list stored below.
			 *
			 * @var array<array{number: string, name: string, depth: int}> $cached
			 */
			return $cached;
		}

		$units = array();
		foreach ( ( new Unit_Tree( $this->skautis_gateway ) )->load( $number ) as $node ) {
			$units[] = array(
				'number' => Unit_Tree::get_string( $node['unit'], 'RegistrationNumber' ),
				'name'   => Unit_Tree::get_string( $node['unit'], 'DisplayName' ),
				'depth'  => $node['depth'],
			);
		}
		// An empty list, e.g. after a refused call, is tried again sooner.
		set_transient( self::CACHE . md5( $number ), $units, count( $units ) > 0 ? DAY_IN_SECONDS : intdiv( DAY_IN_SECONDS, 24 ) );
		$numbers = get_option( self::CACHED_NUMBERS_OPTION, array() );
		$numbers = is_array( $numbers ) ? $numbers : array();
		if ( ! in_array( $number, $numbers, true ) ) {
			$numbers[] = $number;
			update_option( self::CACHED_NUMBERS_OPTION, $numbers, false );
		}

		return $units;
	}

	/**
	 * Registers the REST route with the list of the units.
	 *
	 * @return void
	 */
	public function register_rest_route() {
		register_rest_route(
			SKAUTIS_INTEGRATION_NAME . '/v1',
			'/unit-list',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_rest_list' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'module' => array(
						'type'    => 'string',
						'enum'    => array( 'recruitment', 'unit' ),
						'default' => 'recruitment',
					),
				),
			)
		);
	}

	/**
	 * Returns the list of the units for a block, with the labels: the registration number, and the name when it is turned on.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return array{unit: string, units: array<array{value: string, label: string}>}
	 */
	public function get_rest_list( $request ): array {
		$unit   = 'unit' === $request->get_param( 'module' );
		$number = $unit ? Unit_Data::get_unit_number() : Recruitment_Data::get_unit_number();
		$names  = '1' === get_option( $unit ? self::UNIT_NAMES_OPTION : self::RECRUITMENT_NAMES_OPTION, '0' );
		$units  = array();
		foreach ( $this->get_units( $number ) as $item ) {
			$units[] = array(
				'value' => $item['number'],
				// The units below are indented, so that the list shows the tree.
				'label' => str_repeat( '— ', $item['depth'] ) . $item['number'] . ( $names && '' !== $item['name'] ? ' ' . $item['name'] : '' ),
			);
		}

		return array(
			'unit'  => $number,
			'units' => $units,
		);
	}
}

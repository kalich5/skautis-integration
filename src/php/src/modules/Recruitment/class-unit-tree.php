<?php
/**
 * Contains the Unit_Tree class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Modules\Recruitment;

use Skautis_Integration\Auth\Skautis_Gateway;

/**
 * Loads a unit and all units below it from SkautIS, e.g. a district (okres), its groups (střediska) and their troops (oddíly).
 *
 * The units are returned in the order of a tree: every unit is followed by its child units, sorted by their registration numbers.
 *
 * @phpstan-type Node array{unit: \stdClass, parent_id: int, parent_name: string, depth: int}
 */
final class Unit_Tree {

	/**
	 * The most units that are loaded, so that a large unit (e.g. a region) does not overload SkautIS or the site.
	 */
	const MAX_UNITS = 300;

	/**
	 * A link to the Skautis_Gateway service instance.
	 *
	 * @var Skautis_Gateway
	 */
	private $skautis_gateway;

	/**
	 * Whether the tree has more units than were loaded.
	 *
	 * @var bool
	 */
	private $truncated = false;

	/**
	 * Constructs the service and saves all dependencies.
	 *
	 * @param Skautis_Gateway $skautis_gateway An injected Skautis_Gateway service instance.
	 */
	public function __construct( Skautis_Gateway $skautis_gateway ) {
		$this->skautis_gateway = $skautis_gateway;
	}

	/**
	 * Checks whether the last loaded tree has more units than were loaded.
	 */
	public function is_truncated(): bool {
		return $this->truncated;
	}

	/**
	 * Returns a string property of a unit.
	 *
	 * @param \stdClass $unit The unit.
	 * @param string    $property The property.
	 */
	public static function get_string( \stdClass $unit, string $property ): string {
		return isset( $unit->$property ) && is_scalar( $unit->$property ) ? trim( strval( $unit->$property ) ) : '';
	}

	/**
	 * Returns the ID of a unit.
	 *
	 * @param \stdClass $unit The unit.
	 */
	public static function get_id( \stdClass $unit ): int {
		return isset( $unit->ID ) && is_numeric( $unit->ID ) ? intval( $unit->ID ) : 0;
	}

	/**
	 * Finds a unit by its registration number.
	 *
	 * @param string $number The registration number, e.g. "112.31".
	 *
	 * @return \stdClass|null The unit, or null when it was not found or SkautIS refused the call.
	 */
	public function find( string $number ) {
		foreach ( $this->skautis_gateway->call_list( 'OrganizationUnit', 'UnitAll', array( 'RegistrationNumber' => $number ) ) as $unit ) {
			if ( self::get_string( $unit, 'RegistrationNumber' ) === $number ) {
				return $unit;
			}
		}

		return null;
	}

	/**
	 * Returns the child units of a unit, sorted by their registration numbers.
	 *
	 * @param int $unit_id The ID of the unit.
	 *
	 * @return array<\stdClass>
	 */
	private function get_children( int $unit_id ): array {
		$children = $this->skautis_gateway->call_list( 'OrganizationUnit', 'UnitAll', array( 'ID_UnitParent' => $unit_id ) );
		usort(
			$children,
			static function ( \stdClass $first, \stdClass $second ): int {
				return strnatcmp( self::get_string( $first, 'RegistrationNumber' ), self::get_string( $second, 'RegistrationNumber' ) );
			}
		);

		return $children;
	}

	/**
	 * Loads a unit and all units below it.
	 *
	 * @param string $number The registration number of the unit.
	 *
	 * @phpstan-return array<Node>
	 * @return array<array<string, mixed>> The units in the order of the tree, an empty list when the unit was not found.
	 */
	public function load( string $number ): array {
		$this->truncated = false;
		$root            = $this->find( $number );
		if ( is_null( $root ) ) {
			return array();
		}

		$nodes = array();
		$stack = array(
			array(
				'unit'        => $root,
				'parent_id'   => 0,
				'parent_name' => '',
				'depth'       => 0,
			),
		);
		$node  = array_pop( $stack );
		while ( ! is_null( $node ) ) {
			if ( count( $nodes ) >= self::MAX_UNITS ) {
				$this->truncated = true;
				break;
			}
			$nodes[] = $node;
			// The children are pushed in reverse, so that they are taken in the order of their registration numbers.
			foreach ( array_reverse( $this->get_children( self::get_id( $node['unit'] ) ) ) as $child ) {
				$stack[] = array(
					'unit'        => $child,
					'parent_id'   => self::get_id( $node['unit'] ),
					'parent_name' => self::get_string( $node['unit'], 'DisplayName' ),
					'depth'       => $node['depth'] + 1,
				);
			}
			$node = array_pop( $stack );
		}

		return $nodes;
	}
}

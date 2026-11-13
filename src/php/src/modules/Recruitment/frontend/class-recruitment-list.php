<?php
/**
 * Contains the Recruitment_List class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Modules\Recruitment\Frontend;

use Skautis_Integration\Modules\Recruitment\Recruitment_Data;

/**
 * Chooses and arranges the cards of the "Recruitment" block: the units chosen in the block, grouped by their parent units, with the filter for the visitors.
 *
 * @phpstan-type Unit array{id: int, name: string, registration_number: string, is_water: bool, note: string, categories: array<array{age_from: int, age_to: int, sex: string, note: string, meeting: int}>, meetings: array<array{id: int, day: string, day_order: int, time_from: string, time_to: string, periodicity: string, place: int, place_name: string}>, places: array<array{id: int, name: string, address: string, lat: float, lng: float}>, group_id: int, group_name: string}
 */
final class Recruitment_List {

	/**
	 * Returns the registration number of the unit set in a block, or an empty string for the unit of the site.
	 *
	 * @param array<string, mixed> $attributes The attributes of the block.
	 */
	public static function get_number( array $attributes ): string {
		return isset( $attributes['unit'] ) ? Recruitment_Data::sanitize_number( $attributes['unit'] ) : '';
	}

	/**
	 * Returns the units chosen in a block: all units, or the ones in the `units` attribute.
	 *
	 * @phpstan-param array<Unit> $units
	 * @param array<array<string, mixed>> $units The units.
	 * @param array<string, mixed>        $attributes The attributes of the block.
	 *
	 * @phpstan-return array<Unit>
	 * @return array<array<string, mixed>>
	 */
	public static function select( array $units, array $attributes ): array {
		$chosen = isset( $attributes['units'] ) && is_array( $attributes['units'] ) ? array_map( 'intval', $attributes['units'] ) : array();
		if ( count( $chosen ) === 0 ) {
			return $units;
		}

		return array_values(
			array_filter(
				$units,
				static function ( array $unit ) use ( $chosen ): bool {
					return in_array( $unit['id'], $chosen, true );
				}
			)
		);
	}

	/**
	 * Returns the units in groups by their parent units, in the order of the units.
	 *
	 * @phpstan-param array<Unit> $units
	 * @param array<array<string, mixed>> $units The units.
	 *
	 * @phpstan-return array<int, array{name: string, units: array<Unit>}>
	 * @return array<int, array{name: string, units: array<array<string, mixed>>}>
	 */
	private static function group( array $units ): array {
		$groups = array();
		foreach ( $units as $unit ) {
			if ( ! isset( $groups[ $unit['group_id'] ] ) ) {
				$groups[ $unit['group_id'] ] = array(
					'name'  => $unit['group_name'],
					'units' => array(),
				);
			}
			$groups[ $unit['group_id'] ]['units'][] = $unit;
		}

		return $groups;
	}

	/**
	 * Returns the HTML code of the filter for the visitors: by the group, the age and boys or girls.
	 *
	 * @param array<int, array{name: string, units: array<array<string, mixed>>}> $groups The groups.
	 */
	private static function get_filter( array $groups ): string {
		// The filter needs the script of the page, which does not run in the preview of the block editor.
		$html = '<form class="skautis-recruitment-filter" data-skautis-recruitment-filter' . ( Blocks::is_editor_preview() ? '' : ' hidden' ) . '>';
		if ( count( $groups ) > 1 ) {
			$html .= '<label>' . esc_html__( 'Unit', 'skautis-integration' ) . ' <select name="group"><option value="">' . esc_html__( 'All', 'skautis-integration' ) . '</option>';
			foreach ( $groups as $group_id => $group ) {
				$html .= '<option value="' . esc_attr( (string) $group_id ) . '">' . esc_html( $group['name'] ) . '</option>';
			}
			$html .= '</select></label> ';
		}
		$html .= '<label>' . esc_html__( 'Age of the child', 'skautis-integration' ) . ' <input type="number" name="age" min="3" max="99" inputmode="numeric" /></label> ';
		$html .= '<label>' . esc_html__( 'For', 'skautis-integration' ) . ' <select name="sex"><option value="">' . esc_html__( 'Boys and girls', 'skautis-integration' ) . '</option><option value="male">' . esc_html__( 'Boys', 'skautis-integration' ) . '</option><option value="female">' . esc_html__( 'Girls', 'skautis-integration' ) . '</option></select></label>';
		$html .= '</form><p class="skautis-recruitment-no-match" hidden>' . esc_html__( 'No unit matches the filter.', 'skautis-integration' ) . '</p>';

		return $html;
	}

	/**
	 * Returns the HTML code of the cards of a block, in groups by their parent units when there are more of them.
	 *
	 * @phpstan-param array<Unit> $units
	 * @param array<array<string, mixed>> $units The units chosen in the block.
	 * @param array<string, mixed>        $attributes The attributes of the block.
	 */
	public static function render( array $units, array $attributes ): string {
		$groups      = self::group( $units );
		$show_groups = count( $groups ) > 1 && ( ! isset( $attributes['showGroups'] ) || true === $attributes['showGroups'] );
		$html        = isset( $attributes['showFilter'] ) && true === $attributes['showFilter'] ? self::get_filter( $groups ) : '';
		foreach ( $groups as $group_id => $group ) {
			$cards = Blocks::render_units( $group['units'], $attributes );
			$html .= '<div class="skautis-recruitment-group" data-skautis-group="' . esc_attr( (string) $group_id ) . '">'
				. ( $show_groups ? '<h2 class="skautis-recruitment-group-title">' . esc_html( $group['name'] ) . '</h2>' : '' )
				. $cards . '</div>';
		}

		return $html;
	}
}

<?php
/**
 * Contains the Unit_Card class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Modules\Unit\Frontend;

use Skautis_Integration\Modules\Unit\Unit_Data;

/**
 * Renders the card of a unit for the "Unit" block and for the previews of the appearances, with the items chosen in the block.
 */
final class Unit_Card {

	/**
	 * The items of the block in the form `attribute => shown by default`.
	 */
	const ITEMS = array(
		'showName'               => true,
		'showAddress'            => true,
		'showRegistrationNumber' => true,
		'showIc'                 => true,
		'showMap'                => true,
		'showEmail'              => true,
		'showPhone'              => true,
		'showWeb'                => true,
		'showOtherContacts'      => true,
		'showStatutory'          => true,
		'showAssistant'          => true,
		'showContactPerson'      => true,
		'showAccounts'           => false,
		'showUnits'              => false,
	);

	/**
	 * The attributes of the first version of the block, which turned on or off several items at once, in the form `item => old attribute`.
	 */
	const LEGACY_ITEMS = array(
		'showAddress'            => 'showSeat',
		'showRegistrationNumber' => 'showSeat',
		'showIc'                 => 'showSeat',
		'showEmail'              => 'showContacts',
		'showPhone'              => 'showContacts',
		'showWeb'                => 'showContacts',
		'showOtherContacts'      => 'showContacts',
		'showStatutory'          => 'showPeople',
		'showAssistant'          => 'showPeople',
		'showContactPerson'      => 'showPeople',
	);

	/**
	 * The item that shows a contact, by its kind.
	 */
	const CONTACT_ITEMS = array(
		'email' => 'showEmail',
		'phone' => 'showPhone',
		'web'   => 'showWeb',
		'other' => 'showOtherContacts',
	);

	/**
	 * The item that shows a person, by their role.
	 */
	const PERSON_ITEMS = array(
		Unit_Data::STATUTORY => 'showStatutory',
		Unit_Data::ASSISTANT => 'showAssistant',
		Unit_Data::CONTACT   => 'showContactPerson',
	);

	/**
	 * Checks whether an item of the block is shown.
	 *
	 * A block from the first version has only the old attributes, which are used when it is saved without the new ones.
	 *
	 * @param array<string, mixed> $attributes The attributes of the block.
	 * @param string               $item The attribute of the item.
	 */
	public static function shows( array $attributes, string $item ): bool {
		$legacy = self::LEGACY_ITEMS[ $item ] ?? '';
		if ( '' !== $legacy && isset( $attributes[ $legacy ] ) && false === $attributes[ $legacy ] && ( ! isset( $attributes[ $item ] ) || self::ITEMS[ $item ] === $attributes[ $item ] ) ) {
			return false;
		}

		return isset( $attributes[ $item ] ) ? true === $attributes[ $item ] : self::ITEMS[ $item ];
	}

	/**
	 * Returns the kind of a contact: email, phone, web or other.
	 *
	 * @param string $value The value of the contact.
	 */
	private static function get_contact_kind( string $value ): string {
		if ( false !== is_email( $value ) ) {
			return 'email';
		}
		if ( 1 === preg_match( '~^(https?://|www\.)~i', $value ) ) {
			return 'web';
		}
		if ( 1 === preg_match( '~^\+?[0-9 ]{9,}$~', $value ) ) {
			return 'phone';
		}

		return 'other';
	}

	/**
	 * Returns the HTML code of a contact: a link for an e-mail, a phone number or a web address.
	 *
	 * @param array{type: string, value: string, note: string} $contact The contact.
	 */
	private static function get_contact( array $contact ): string {
		$value = $contact['value'];
		switch ( self::get_contact_kind( $value ) ) {
			case 'email':
				$link = '<a href="' . esc_url( 'mailto:' . $value ) . '">' . esc_html( $value ) . '</a>';
				break;
			case 'web':
				$link = '<a href="' . esc_url( 0 === stripos( $value, 'www.' ) ? 'https://' . $value : $value ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $value ) . '</a>';
				break;
			case 'phone':
				$link = '<a href="' . esc_url( 'tel:' . str_replace( ' ', '', $value ) ) . '">' . esc_html( $value ) . '</a>';
				break;
			default:
				$link = esc_html( $value );
		}
		$label = '' !== $contact['type'] ? '<span class="skautis-unit-label">' . esc_html( $contact['type'] ) . ':</span> ' : '';
		$note  = '' !== $contact['note'] ? ' <span class="skautis-unit-note">(' . esc_html( $contact['note'] ) . ')</span>' : '';

		return '<dd>' . $label . $link . $note . '</dd>';
	}

	/**
	 * Returns the HTML code of the details of the unit.
	 *
	 * @param array{id: int, name: string, registration_number: string, ic: string, address: string, lat: float, lng: float, contacts: array<array{type: string, value: string, note: string}>, people: array<array{role: string, function: string, name: string}>, accounts: array<array{name: string, number: string}>, units: array<array{name: string, registration_number: string}>} $unit The unit.
	 * @param array<string, mixed>                                                                                                                                                                                                                                                                                                                                                        $attributes The attributes of the block.
	 */
	private static function get_details( array $unit, array $attributes ): string {
		$html = '';
		if ( self::shows( $attributes, 'showAddress' ) && '' !== $unit['address'] ) {
			$html .= '<dt>' . esc_html__( 'Seat', 'skautis-integration' ) . '</dt><dd>' . esc_html( $unit['address'] ) . '</dd>';
		}
		if ( self::shows( $attributes, 'showRegistrationNumber' ) && '' !== $unit['registration_number'] ) {
			$html .= '<dt>' . esc_html__( 'Registration number', 'skautis-integration' ) . '</dt><dd>' . esc_html( $unit['registration_number'] ) . '</dd>';
		}
		if ( self::shows( $attributes, 'showIc' ) && '' !== $unit['ic'] ) {
			$html .= '<dt>' . esc_html__( 'Company ID (IČ)', 'skautis-integration' ) . '</dt><dd>' . esc_html( $unit['ic'] ) . '</dd>';
		}
		$contacts = '';
		foreach ( $unit['contacts'] as $contact ) {
			if ( self::shows( $attributes, self::CONTACT_ITEMS[ self::get_contact_kind( $contact['value'] ) ] ) ) {
				$contacts .= self::get_contact( $contact );
			}
		}
		if ( '' !== $contacts ) {
			$html .= '<dt>' . esc_html__( 'Contacts', 'skautis-integration' ) . '</dt>' . $contacts;
		}
		$people = array(
			'statutory' => '',
			'contact'   => '',
		);
		foreach ( $unit['people'] as $person ) {
			if ( self::shows( $attributes, self::PERSON_ITEMS[ $person['role'] ] ?? 'showStatutory' ) ) {
				$people[ Unit_Data::CONTACT === $person['role'] ? 'contact' : 'statutory' ] .= '<dd>' . ( '' !== $person['function'] ? '<span class="skautis-unit-label">' . esc_html( $person['function'] ) . ':</span> ' : '' ) . esc_html( $person['name'] ) . '</dd>';
			}
		}
		if ( '' !== $people['statutory'] ) {
			$html .= '<dt>' . esc_html__( 'Statutory body', 'skautis-integration' ) . '</dt>' . $people['statutory'];
		}
		if ( '' !== $people['contact'] ) {
			$html .= '<dt>' . esc_html__( 'Contact person', 'skautis-integration' ) . '</dt>' . $people['contact'];
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
	 * Returns the HTML code of the card of the unit, without the map.
	 *
	 * Used by the block and by the previews of the appearances.
	 *
	 * @param array{id: int, name: string, registration_number: string, ic: string, address: string, lat: float, lng: float, contacts: array<array{type: string, value: string, note: string}>, people: array<array{role: string, function: string, name: string}>, accounts: array<array{name: string, number: string}>, units: array<array{name: string, registration_number: string}>} $unit The unit.
	 * @param array<string, mixed>                                                                                                                                                                                                                                                                                                                                                        $attributes The attributes of the block.
	 * @param string                                                                                                                                                                                                                                                                                                                                                                      $map The HTML code of the map.
	 */
	public static function render( array $unit, array $attributes, string $map = '' ): string {
		return '<section class="skautis-recruitment-unit">'
			. ( self::shows( $attributes, 'showName' ) ? '<h3>' . esc_html( $unit['name'] ) . '</h3>' : '' )
			. self::get_details( $unit, $attributes )
			. $map
			. '</section>';
	}
}

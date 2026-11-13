<?php
/**
 * Contains the Status class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Admin;

use Skautis_Integration\Auth\Skautis_Gateway;
use Skautis_Integration\Auth\Skautis_Login;
use Skautis_Integration\Auth\WP_Login_Logout;

/**
 * The "Status" tab of the settings page: the check of the environment of the site and the test of the SkautIS methods that the plugin uses.
 *
 * @phan-constructor-used-for-side-effects
 */
final class Status {

	/**
	 * A link to the Skautis_Gateway service instance.
	 *
	 * @var Skautis_Gateway
	 */
	private $skautis_gateway;

	/**
	 * A link to the Skautis_Login service instance.
	 *
	 * @var Skautis_Login
	 */
	private $skautis_login;

	/**
	 * A link to the WP_Login_Logout service instance.
	 *
	 * @var WP_Login_Logout
	 */
	private $wp_login_logout;

	/**
	 * Constructs the service and saves all dependencies.
	 *
	 * @param Skautis_Gateway $skautis_gateway An injected Skautis_Gateway service instance.
	 * @param Skautis_Login   $skautis_login An injected Skautis_Login service instance.
	 * @param WP_Login_Logout $wp_login_logout An injected WP_Login_Logout service instance.
	 */
	public function __construct( Skautis_Gateway $skautis_gateway, Skautis_Login $skautis_login, WP_Login_Logout $wp_login_logout ) {
		$this->skautis_gateway = $skautis_gateway;
		$this->skautis_login   = $skautis_login;
		$this->wp_login_logout = $wp_login_logout;
		add_action( SKAUTIS_INTEGRATION_NAME . '_settings_tab_status', array( $this, 'print_tab' ) );
	}

	/**
	 * Returns the icon of a status.
	 *
	 * @param string $status The status.
	 */
	private static function get_icon( string $status ): string {
		$icons = array(
			Status_Checks::OK         => array( 'yes-alt', __( 'OK', 'skautis-integration' ) ),
			Status_Checks::WARNING    => array( 'warning', __( 'Needs attention', 'skautis-integration' ) ),
			Status_Checks::ERROR      => array( 'dismiss', __( 'Error', 'skautis-integration' ) ),
			Skautis_Methods::UNTESTED => array( 'minus', __( 'Not tested', 'skautis-integration' ) ),
		);
		$icon  = $icons[ $status ] ?? $icons[ Skautis_Methods::UNTESTED ];

		return '<span class="skautis-status-icon is-' . esc_attr( $status ) . ' dashicons dashicons-' . esc_attr( $icon[0] ) . '" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html( $icon[1] ) . '</span>';
	}

	/**
	 * Prints a card with checks.
	 *
	 * @param string                                                     $title The title of the card.
	 * @param array<array{label: string, status: string, value: string}> $checks The checks.
	 *
	 * @return void
	 */
	private static function print_checks( string $title, array $checks ) {
		echo '<div class="skautis-settings-card"><h2>' . esc_html( $title ) . '</h2><table class="widefat striped skautis-status-table"><tbody>';
		foreach ( $checks as $check ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in get_icon().
			echo '<tr><td class="skautis-status-cell">' . self::get_icon( $check['status'] ) . '</td><th scope="row">' . esc_html( $check['label'] ) . '</th><td>' . esc_html( $check['value'] ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Prints the groups of the methods.
	 *
	 * @param array<array<string, mixed>> $groups The tested groups.
	 * @param bool                        $login Whether to print the groups of the methods with a logged-in user.
	 *
	 * @return void
	 */
	private static function print_groups( array $groups, bool $login ) {
		foreach ( $groups as $tested ) {
			if ( ! isset( $tested['group']['login'], $tested['group']['label'], $tested['results'] ) || $login !== $tested['group']['login'] || ! is_array( $tested['results'] ) ) {
				continue;
			}
			echo '<h3>' . esc_html( strval( $tested['group']['label'] ) ) . '</h3><table class="widefat striped skautis-status-table"><tbody>';
			foreach ( $tested['results'] as $method => $result ) {
				$status  = is_array( $result ) && isset( $result['status'] ) ? strval( $result['status'] ) : Skautis_Methods::UNTESTED;
				$message = is_array( $result ) && isset( $result['message'] ) ? strval( $result['message'] ) : '';
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in get_icon().
				echo '<tr><td class="skautis-status-cell">' . self::get_icon( $status ) . '</td><th scope="row"><code>' . esc_html( strval( $method ) ) . '</code></th><td>' . esc_html( $message ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
	}

	/**
	 * Prints the "Status" tab.
	 *
	 * @return void
	 */
	public function print_tab() {
		self::print_checks( __( 'Server and WordPress', 'skautis-integration' ), Status_Checks::get_server_checks() );
		self::print_checks( __( 'Plugin and SkautIS', 'skautis-integration' ), Status_Checks::get_plugin_checks( $this->skautis_gateway ) );

		// The methods are tested every time the tab is opened, so it always shows the current state.
		$logged_in = $this->skautis_login->is_user_logged_in_skautis();
		$groups    = ( new Skautis_Methods( $this->skautis_gateway ) )->test( $logged_in );
		$tab_url   = Settings::get_tab_url( 'status' );
		$login_url = $this->wp_login_logout->get_login_url( add_query_arg( 'noWpLogin', '1', $tab_url ) );

		echo '<div class="skautis-settings-card"><h2>' . esc_html__( 'SkautIS methods', 'skautis-integration' ) . '</h2>';
		echo '<p>' . esc_html__( 'The methods of SkautIS that the plugin uses, by the features that need them. They are called every time this tab is opened. The App ID of the site must be allowed to use them in SkautIS. A method with a red cross is refused by SkautIS: ask the administrators of SkautIS to allow it for the App ID, see the file "APP ID - povolení služeb.md" of the plugin.', 'skautis-integration' ) . '</p>';
		if ( ! $logged_in && $this->skautis_gateway->is_initialized() ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'You are not logged in to SkautIS, so the methods that need a logged-in user cannot be tested. Log in to SkautIS and this tab tests them all.', 'skautis-integration' ) . '</p><p><a class="button button-primary" href="' . esc_url( $login_url ) . '">' . esc_html__( 'Log in to SkautIS and test them', 'skautis-integration' ) . '</a></p></div>';
		}
		/* translators: %s: The date and time */
		echo '<p class="description">' . esc_html( sprintf( __( 'Tested on %s.', 'skautis-integration' ), wp_date( strval( get_option( 'date_format' ) ) . ' H:i' ) ) ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( $tab_url ) . '">' . esc_html__( 'Test the methods again', 'skautis-integration' ) . '</a></p>';

		echo '<h2 class="skautis-status-subtitle">' . esc_html__( 'Without a logged-in user', 'skautis-integration' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Called with the App ID of the site only, e.g. for the blocks shown to all visitors.', 'skautis-integration' ) . '</p>';
		self::print_groups( $groups, false );

		echo '<h2 class="skautis-status-subtitle">' . esc_html__( 'With a logged-in user', 'skautis-integration' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Called with the login of the user, e.g. for the login, the registration, the rules and the user management. They are tested with your login to SkautIS.', 'skautis-integration' ) . '</p>';
		if ( ! $logged_in && $this->skautis_gateway->is_initialized() ) {
			echo '<p><a class="button button-primary" href="' . esc_url( $login_url ) . '">' . esc_html__( 'Log in to SkautIS and test them', 'skautis-integration' ) . '</a></p>';
		}
		self::print_groups( $groups, true );
		echo '</div>';
	}
}

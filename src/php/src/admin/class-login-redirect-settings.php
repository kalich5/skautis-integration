<?php
/**
 * Contains the Login_Redirect_Settings class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Admin;

use Skautis_Integration\Auth\Login_Redirect;
use Skautis_Integration\Rules\Rules_Init;
use Skautis_Integration\Rules\Rules_Manager;
use Skautis_Integration\Utils\Helpers;

/**
 * Adds the settings of the redirect after the login to the Login tab of the settings page.
 *
 * @phan-constructor-used-for-side-effects
 */
final class Login_Redirect_Settings {

	/**
	 * The settings group and page of the Login tab.
	 */
	const PAGE = SKAUTIS_INTEGRATION_NAME . '_login';

	/**
	 * The settings section of the redirect.
	 */
	const SECTION = SKAUTIS_INTEGRATION_NAME . '_login_redirect_section';

	/**
	 * A link to the Rules_Manager service instance.
	 *
	 * @var Rules_Manager
	 */
	private $rules_manager;

	/**
	 * Constructs the service and saves all dependencies.
	 *
	 * @param Rules_Manager $rules_manager An injected Rules_Manager service instance.
	 */
	public function __construct( Rules_Manager $rules_manager ) {
		$this->rules_manager = $rules_manager;
		add_action( 'admin_init', array( $this, 'setup_setting_fields' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueues the script of the redirect rules on the Login tab.
	 *
	 * @return void
	 */
	public static function enqueue_scripts() {
		if ( Settings::is_settings_tab( 'login' ) ) {
			wp_enqueue_script( 'jquery-ui-sortable' );
			Helpers::enqueue_script( 'admin_login_redirect', 'admin/js/skautis-admin-login-redirect.min.js', array( 'jquery-ui-sortable', SKAUTIS_INTEGRATION_NAME . '_select2' ) );
		}
	}

	/**
	 * Adds the settings to WordPress.
	 *
	 * @return void
	 */
	public function setup_setting_fields() {
		add_settings_section(
			self::SECTION,
			__( 'Redirect after login', 'skautis-integration' ),
			// The rules use the whole width of the card, so they are printed in the section instead of a settings field.
			function () {
				echo '<p>' . esc_html__( 'Users who log in with SkautIS on a page of the site return to that page. Users who log in on the login screen of WordPress or on the login page above would land on the dashboard; choose where they go instead.', 'skautis-integration' ) . '</p>';
				$this->print_rules();
			},
			self::PAGE
		);

		add_settings_field(
			Login_Redirect::OPTION,
			__( 'Default page', 'skautis-integration' ),
			array( self::class, 'field_default_target' ),
			self::PAGE,
			self::SECTION,
			array( 'label_for' => Login_Redirect::OPTION )
		);

		register_setting(
			self::PAGE,
			Login_Redirect::OPTION,
			array(
				'type'              => 'integer',
				'show_in_rest'      => false,
				'sanitize_callback' => array( Login_Redirect::class, 'sanitize_target' ),
			)
		);
		register_setting(
			self::PAGE,
			Login_Redirect::RULES_OPTION,
			array(
				'type'              => 'array',
				'show_in_rest'      => false,
				'sanitize_callback' => static function ( $value ) {
					return Login_Redirect::sanitize_rules( is_array( $value ) ? array_values( $value ) : array() );
				},
			)
		);
	}

	/**
	 * Prints the field for the default target.
	 *
	 * @return void
	 */
	public static function field_default_target() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::get_target_select( Login_Redirect::OPTION, Login_Redirect::OPTION, Login_Redirect::get_default_target() );
		echo '<p class="description">' . esc_html__( 'For the users who pass none of the rules. Only published pages are offered; when the page is deleted or no longer published, the users go to the dashboard.', 'skautis-integration' ) . '</p>';
	}

	/**
	 * Prints the table of the redirect rules.
	 *
	 * @return void
	 */
	private function print_rules() {
		$rules = array();
		foreach ( $this->rules_manager->get_all_rules() as $rule ) {
			$rules[ $rule->ID ] = $rule->post_title;
		}
		if ( count( $rules ) === 0 ) {
			/* translators: 1: Start of a link to the rules 2: End of the link to the rules */
			echo '<p>' . sprintf( esc_html__( 'To send different users to different pages, first create rules in %1$sManage rules%2$s.', 'skautis-integration' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . Rules_Init::RULES_TYPE_SLUG ) ) . '">', '</a>' ) . '</p>';
			return;
		}

		echo '<p>' . esc_html__( 'Users who pass a rule go to its page. The rules are evaluated from top to bottom and the first rule the user passes is used.', 'skautis-integration' ) . '</p>';
		// The rules look like the registration rules: one rule per line, ordered by dragging.
		echo '<div class="skautis-rule-rows skautis-login-redirect-rules">';
		foreach ( Login_Redirect::get_rules() as $index => $rule ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo self::get_rule_row( (string) $index, $rules, $rule['rule'], $rule['target'] );
		}
		// An empty row for a new rule; rows without a rule are not saved.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::get_rule_row( (string) count( Login_Redirect::get_rules() ), $rules, 0, Login_Redirect::DASHBOARD );
		echo '</div>';
		// The script adds more rows from the template.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<template id="skautis-login-redirect-rule">' . self::get_rule_row( '__index__', $rules, 0, Login_Redirect::DASHBOARD ) . '</template>';
		echo '<p><button type="button" class="button" id="skautis-login-redirect-add" hidden>' . esc_html__( 'Add rule', 'skautis-integration' ) . '</button></p>';
	}

	/**
	 * Returns the HTML code of a row of the redirect rules.
	 *
	 * @param string             $index The index of the row in the form.
	 * @param array<int, string> $rules The names of all rules, by their IDs.
	 * @param int                $rule The selected rule.
	 * @param int                $target The selected target.
	 */
	private static function get_rule_row( string $index, array $rules, int $rule, int $target ): string {
		$name = Login_Redirect::RULES_OPTION . '[' . $index . ']';
		$html = '<div class="skautis-rule-row"><span class="dashicons dashicons-move handle" aria-hidden="true"></span>'
			. '<label class="skautis-rule-field"><span>' . esc_html__( 'If the rule is passed:', 'skautis-integration' ) . '</span>'
			. '<select name="' . esc_attr( $name . '[rule]' ) . '" class="select2"><option value="0">' . esc_html__( '— Choose a rule —', 'skautis-integration' ) . '</option>';
		foreach ( $rules as $rule_id => $title ) {
			$html .= '<option value="' . esc_attr( (string) $rule_id ) . '"' . selected( $rule, $rule_id, false ) . '>' . esc_html( '' !== $title ? $title : '#' . $rule_id ) . '</option>';
		}
		$html .= '</select></label>'
			. '<label class="skautis-rule-field"><span>' . esc_html__( 'Go to:', 'skautis-integration' ) . '</span>' . self::get_target_select( $name . '[target]', '', $target ) . '</label>'
			. '<button type="button" class="button skautis-rule-remove" data-skautis-remove>' . esc_html__( 'Remove', 'skautis-integration' ) . '</button></div>';

		return $html;
	}

	/**
	 * Returns the HTML code of a select of a target: the dashboard, the home page or a published page.
	 *
	 * @param string $name The name of the field.
	 * @param string $field_id The ID of the field, or an empty string.
	 * @param int    $selected The selected target.
	 */
	private static function get_target_select( string $name, string $field_id, int $selected ): string {
		$html  = '<select name="' . esc_attr( $name ) . '"' . ( '' !== $field_id ? ' id="' . esc_attr( $field_id ) . '"' : '' ) . '>';
		$html .= '<option value="' . esc_attr( (string) Login_Redirect::DASHBOARD ) . '"' . selected( $selected, Login_Redirect::DASHBOARD, false ) . '>' . esc_html__( 'Dashboard (default)', 'skautis-integration' ) . '</option>';
		$html .= '<option value="' . esc_attr( (string) Login_Redirect::HOME ) . '"' . selected( $selected, Login_Redirect::HOME, false ) . '>' . esc_html__( 'Home page of the site', 'skautis-integration' ) . '</option>';
		$pages = get_pages(
			array(
				'post_status' => 'publish',
				'sort_column' => 'menu_order,post_title',
			)
		);
		if ( is_array( $pages ) && count( $pages ) > 0 ) {
			$html .= '<optgroup label="' . esc_attr__( 'Pages', 'skautis-integration' ) . '">' . walk_page_dropdown_tree( $pages, 0, array( 'selected' => $selected ) ) . '</optgroup>';
		}

		return $html . '</select>';
	}
}

=== SkautIS integration ===
Contributors: skaut, davidulus, marekdedic, kalich5
Tags: SkautIS, login, registration, skaut
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.2.0-beta.1
License: GPLv3 or later
License URI: https://github.com/skaut/skautis-integration/blob/master/LICENSE

Login, registration, user management and other features connecting SkautIS to WordPress.

== Description ==

Login, registration, user management and other features connecting SkautIS to WordPress.

=== Features ===
* Login to WordPress with a SkautIS account
* Manage SkautIS account connections and import users to your site
* Enable registration of users to your site based on their SkautIS functions, memberships or other criteria
* Show pages or blocks only to users with selected SkautIS roles, memberships, functions, qualifications or events
* Show the recruitment information, the map of the units and the public information about a unit from SkautIS
* Let users use their SkautIS photo as their profile picture

=== Minimal requirements ===
- WordPress 7.0 or higher
- PHP 8.3 or higher

=== External services ===
The plugin connects to these services. Nothing is sent to them before the plugin is set up.

**SkautIS** (is.skaut.cz, or test-is.skaut.cz for the test environment) is the information system of Junák – český skaut, operated by Junák – český skaut, z. s. The plugin needs it for all its features:

* When a user logs in, registers or connects their account, they are sent to the SkautIS login page and SkautIS returns their login token to the site. The site then loads their user detail, roles, memberships, functions, qualifications or events, to log them in and to check the rules of registration and content visibility.
* The user management page loads the people of the current role of the manager.
* When a user chooses to use their SkautIS photo as their profile picture, the site loads the photo of that user.
* The recruitment, map and unit blocks load the public information about the units set in the settings, with the App ID of the site only, without any user data, at most once a day.

Privacy policy of Junák – český skaut: https://www.skaut.cz/osobniudaje

**Mapy.com** (api.mapy.com), operated by Seznam.cz, a.s., is used only when its API key is set on the "Maps" tab of the settings. Visitors' browsers load the map tiles and the Mapy.com logo when they see a map block, which sends their IP address to Mapy.com. The server of the site sends the address of the seat of a unit to the Mapy.com geocoding to find its position on the map (at most once a day per unit).
Terms: https://developer.mapy.com/terms-and-conditions/ , privacy policy: https://o-seznam.cz/ochrana-udaju/

**OpenStreetMap** (tile.openstreetmap.org and nominatim.openstreetmap.org), operated by the OpenStreetMap Foundation, is used when no Mapy.com API key is set. Visitors' browsers load the map tiles when they see a map block, which sends their IP address to OpenStreetMap. The server of the site sends the address of the seat of a unit to Nominatim to find its position on the map (at most once a day per unit).
Tile usage policy: https://operations.osmfoundation.org/policies/tiles/ , Nominatim usage policy: https://operations.osmfoundation.org/policies/nominatim/ , privacy policy: https://osmfoundation.org/wiki/Privacy_Policy

=== Privacy ===
The plugin stores the SkautIS user ID with the WordPress account of a user, the name and e-mail address of a user registered through SkautIS (as a regular WordPress user), and the SkautIS photo of users who chose to use it. It sets a technical cookie for 40 minutes during the login through SkautIS. It suggests a text for the privacy policy of the site (Settings → Privacy) and supports the export and erasure of personal data (Tools → Export Personal Data and Erase Personal Data).

When `WP_DEBUG` and `WP_DEBUG_LOG` are on, the plugin writes some SkautIS responses, which may contain names of people, to the debug log. Do not leave the debug log on in production.

=== GitHub ===
All the sources for the plugin and the build process are detailed in our [GitHub repo](https://github.com/skaut/skautis-integration/).

== Installation ==
1. Download and install the plugin from the WordPress plugin directory or from [GitHub](https://github.com/skaut/skautis-integration/releases)
2. Activate the plugin
3. Request an APP ID for SkautIS by following the [docs](https://napoveda.skaut.cz/skautis/skautis-integration) (only in Czech)
4. In the admin interface, open the plugin settings, enter the provided APP ID and register the plugin
5. The plugin is now fully functional

== Frequently Asked Questions ==

= How to configure this plugin? =
See our [documentation](https://napoveda.skaut.cz/skautis/skautis-integration) (only in Czech).

== Screenshots ==


== Changelog ==

= 1.2.0-beta.1 =
* Requires WordPress 7.0 and PHP 8.3 or newer
* Content visibility rules for individual blocks in the block editor, rules of a whole page in the document sidebar; the shortcode was removed
* All settings on one page with tabs; the modules were removed: content visibility is always available and registration has its own setting
* New user management page with search and a filter, without DataTables and Thickbox
* The list of rules shows where each rule is used, the rule editor matches the WordPress administration
* The registration rules use the whole width of the screen
* New "Recruitment" and "Map of the units" blocks show the recruitment information of the units from SkautIS, with maps from Mapy.com or OpenStreetMap
* New "Unit" block shows the public information about a unit from SkautIS: the seat with a map, the public contacts, the statutory body, the bank account and the child units
* Appearances of the recruitment and unit blocks for the Dobrý skautský web templates (DSW1, DSW2, DSW3) and custom appearances, chosen for the whole site or for each block
* The "Recruitment" and "Unit" blocks can turn each of their parts on or off
* The SkautIS login is shown in its own card below the login form
* Each user can use their photo from SkautIS as their profile picture; it is downloaded only when the user chooses it in their profile
* Privacy: a suggested privacy policy text, export and erasure of the personal data stored by the plugin, the external services described in the readme
* A SkautIS method that the App ID is not allowed to use no longer breaks the site; the administration shows which methods to allow
* English source texts, translatable on translate.wordpress.org
* SVG menu icon
* Restricted posts and their comments are also hidden in the REST API and in feeds
* Security fixes: logout nonce, user actions sent by POST, escaped output, random session cookies
* Fixed plugin activation, login on multisite and the default login page
* Fixed the registration of new users and of event participants
* Fixed the "not in" operator of role and function rules and function rules for people with a single function
* Fixed the rule editor in languages other than Czech
* Fixed the check of the registration conditions on login running when it was turned off
* All plugin data is removed on uninstall
* No deprecation notices from the SkautIS library on PHP 8.4

= 1.1.33 =
* Fixed the cleanup of duplicated autosave post meta running out of memory on large sites

= 1.1.32 =
* Support for WordPress 6.9, 7.0 and 7.1
* Updated DataTables to version 3
* Fixed autosaves duplicating post meta
* Fixed the TinyMCE shortcode button failing to load
* Security hardening against direct file access
* Dropped support for browsers without ES2017 support

= 1.1.31 =
* Disabled SkautIS ID check due to issues in SkautIS

= 1.1.30 =
* Fixed errors in rule editor

= 1.1.29 =
* Support for WordPress 6.6
* DataTables updates
* Fixed possible clashes with other plugins

= 1.1.28 =
* Fixed page visibility rules not being saved

= 1.1.27 =
* Fixed role switcher on the user management page
* Fixed an error on PHP 7

= 1.1.26 =
* Security fixes
* Improved error handling
* Improved translations
* Enabled asset minification, leading to faster loading
* Improved asset caching, leading to faster loading
* Fixed corner-case issues in rule processing

= 1.1.25 =
* Security fixes

= 1.1.24 =
* Added support for PHP 8.1
* Raised minimum PHP version to 7.4

= 1.1.23 =
* Optimized dependency loading
* Fixed a typo in code

= 1.1.22 =
* Fixed an issue where the plugin would cause a fatal error on yet some other sites

= 1.1.21 =
* Fixed an issue from 1.1.19 where the plugin would cause a fatal error on some sites

= 1.1.20 =
* Re-released version 1.1.18

= 1.1.19 =
* Switched from session to WP transients for SkautIS user management

= 1.1.18 =
* Fixed security issues
* Replaced dependencies loaded from CDN with bundled ones

= 1.1.17 =
* oprava chyb

= 1.1.16 =
* oprave kompatibility s PHP 8.x

= 1.1.15 =
* Nejnižší požadovaná verze změněna na 4.9.6

= 1.1.14 =
* Ve výběru rolí na stránce "Správa uživatelů" se nyní zobrazují jen aktivní role podle SkautISu

= 1.1.13 =
* Oprava zpracování funkcí při přihlášení/registraci

= 1.1.12 =
* Nové pravidlo: kvalifikace
* Pravidla: u členství, rolí a funkcí lze nyní zvolit možnost "jakékoliv" u evidenčního čísla jednotky
* Správa uživatelů - vyšší výchozí počet záznamů na stránku, ukládání stavu tabulky = po znovunačtení stránky se tabulka zobrazí v původním stavu
* Modul Viditelnost - pravidla převzatá z nadřazených stránek se u podřízených stránek označí ve výběru jako vybraná (disabled)
* Aktualizace JS knihoven

= 1.1.11 =
* Opravy chyb

= 1.1.10 =
* Novinka - na podřízené stránky se nyní použijí pravidla z celého hierarchického stromu nadřízených stránek, nejen z nejvýše nadřazené stránky
* Výpis pravidel z nadřazených stránek
* Drobné opravy

= 1.1.9 =
* Při použití pravidla "Všichni bez omezení" se mohou nově přihlašovat jen ti, kteří mají propojený účet ve SkautISu. Aby se zabránilo tomu, že si někdo vytvoří jen tak účet a pak se přihlásí třeba do uzavřené sekce nějakého skautského webu.

= 1.1.8 =
* Oprava logování přihlášení/odhlášení pro pluginy, které hlídají co se na webu děje. (př.: Simple History)

= 1.1.7 =
* Změna rozesílání emailů, nyní se odesílají vždy podle globálního nastavení

= 1.1.6 =
* Opraveno vytváření uživatelů

= 1.1.5 =
* Opraveno zobrazování stránky se správou uživatelů při zvolení špatné role

= 1.1.4 =
* změna vytváření uživatelského jména při registraci - místo emailu uživatele se nyní nastaví jeho login do SkautISu
* oprava propojování účtů
* oprava načítání query editoru
* oprava ukládání nastavení na PHP 7.0.x

= 1.1.3 =
* možnost vytvářet manuálně nové uživatele
* lepší vyhledávání na stránce "Správa uživatelů" (řeší omezení limitu 500 uživatelů naráz ze SkautISu)

= 1.1.2 =
* opravy textů v pluginu

= 1.1.1 =
* dopnění překladů, frontend je nyní AJ a CZ

= 1.1 =
* vyžadováno PHP 7.0 a vyšší
* nové pravidlo: funkce
* viditelnost stránek/příspěvků/custom type
* shortcode pro vymezení obsahu
* lepší rozhraní na stránce "Správa uživatelů"

= 1.0 =
* podpora přihlášení přes SkautIS
* podpora registrace přes SkautIS
* nastavování pravidel
* propojování již registrovaných uživatelů
* nastavování propojování pravidel a rolí ve WordPressu
* vyžadován WordPress 4.8 a vyšší

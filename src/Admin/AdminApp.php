<?php
/**
 * Admin single-page app: menu, section routing, the app shell and its assets.
 *
 * Sections are AdminPage objects; form/link targets live in Admin\Actions.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\Pages\DashboardPage;
use YadamBiar\Admin\Pages\DiagnosePage;
use YadamBiar\Admin\Pages\ForecastPage;
use YadamBiar\Admin\Pages\HelpPage;
use YadamBiar\Admin\Pages\LogsPage;
use YadamBiar\Admin\Pages\ReportsPage;
use YadamBiar\Admin\Pages\RequestsPage;
use YadamBiar\Admin\Pages\SettingsPage;
use YadamBiar\Admin\Ui\Hero;
use YadamBiar\Contracts\AdminPage;
use YadamBiar\Contracts\Module;
use YadamBiar\Settings\Settings;
use YadamBiar\Subscriptions\SubscriptionRepository;
use YadamBiar\Support\Icon;

/**
 * Admin app.
 */
final class AdminApp implements Module {

	const LEGACY_PAGE_PREFIX = 'yadambiar-';

	/**
	 * Sections by slug.
	 *
	 * @var AdminPage[]|null
	 */
	private $pages;

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_filter( 'submenu_file', array( $this, 'submenu_file' ) );
		add_action( 'admin_menu', array( $this, 'legacy_redirect' ), 0 ); // Before WordPress refuses the unknown page slug.
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'menu_assets' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/**
	 * Sections of the app, filterable with "yadambiar_admin_pages".
	 *
	 * @return AdminPage[] slug => page.
	 */
	public function pages() {
		if ( null === $this->pages ) {
			$list = apply_filters(
				'yadambiar_admin_pages',
				array(
					new DashboardPage(),
					new RequestsPage(),
					new ReportsPage(),
					new LogsPage(),
					new ForecastPage(),
					new SettingsPage(),
					new HelpPage(),
					new DiagnosePage(),
				)
			);
			$this->pages = array();
			foreach ( $list as $page ) {
				if ( $page instanceof AdminPage ) {
					$this->pages[ $page->slug() ] = $page;
				}
			}
		}
		return $this->pages;
	}

	/**
	 * Open section.
	 *
	 * @return AdminPage
	 */
	public function current_page() {
		$pages = $this->pages();
		$view  = isset( $_GET['view'] ) && is_string( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification
		return isset( $pages[ $view ] ) ? $pages[ $view ] : $pages['dashboard'];
	}

	/**
	 * Menu: one entry; the sections live inside the page (loaded with AJAX) and get sidebar shortcuts.
	 */
	public function menu() {
		$active = SubscriptionRepository::active_count();
		$bubble = $active ? ' <span class="awaiting-mod">' . esc_html( number_format_i18n( $active ) ) . '</span>' : '';
		$label  = '<span class="yadambiar-mn"><b>' . esc_html__( 'یادم بیار', 'yadambiar' ) . '</b>' . $bubble . '</span>';
		$hook   = add_menu_page( __( 'یادم بیار', 'yadambiar' ), $label, Access::CAP, AdminUrl::PAGE, array( $this, 'render' ), 'none', 56 ); // Icon drawn by admin-menu.css (assets/img/yadambiar-icon.svg).
		add_action( 'load-' . $hook, array( $this, 'load' ) );

		foreach ( $this->pages() as $slug => $page ) {
			if ( ! $page->in_menu() ) {
				continue;
			}
			$is_home = 'dashboard' === $slug;
			add_submenu_page( AdminUrl::PAGE, $page->title(), $page->title(), Access::CAP, $this->submenu_slug( $slug ), $is_home ? array( $this, 'render' ) : '' );
		}
	}

	/**
	 * Sidebar shortcut slug of a section.
	 *
	 * @param string $slug Section.
	 * @return string
	 */
	private function submenu_slug( $slug ) {
		return 'dashboard' === $slug ? AdminUrl::PAGE : 'admin.php?page=' . AdminUrl::PAGE . '&view=' . $slug;
	}

	/**
	 * Highlight the sidebar shortcut of the open section.
	 *
	 * @param string|null $file Submenu file.
	 * @return string|null
	 */
	public function submenu_file( $file ) {
		return $this->on_app_page() ? $this->submenu_slug( $this->current_page()->slug() ) : $file;
	}

	/**
	 * Is the current request the app page?
	 *
	 * @return bool
	 */
	private function on_app_page() {
		return isset( $_GET['page'] ) && AdminUrl::PAGE === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Old per-section URLs (page=yadambiar-settings …) redirect to the single page.
	 */
	public function legacy_redirect() {
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 0 !== strpos( $page, self::LEGACY_PAGE_PREFIX ) ) {
			return;
		}
		$args = array();
		foreach ( wp_unslash( $_GET ) as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
			if ( is_string( $value ) ) {
				$args[ sanitize_key( $key ) ] = sanitize_text_field( $value );
			}
		}
		unset( $args['page'], $args['view'] );
		wp_safe_redirect( AdminUrl::view( substr( $page, strlen( self::LEGACY_PAGE_PREFIX ) ), $args ) );
		exit;
	}

	/**
	 * Before any output on the app page: section pre-processing and, for AJAX navigation, the bare fragment.
	 */
	public function load() {
		$this->silence_foreign_notices();
		$page = $this->current_page();
		$page->load();
		if ( isset( $_SERVER['HTTP_X_YADAMBIAR_AJAX'] ) ) {
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'X-YadamBiar-Fragment: 1' );
			header( 'X-YadamBiar-View: ' . $page->slug() );
			Flash::render();
			$page->render();
			exit;
		}
	}

	/**
	 * Keep the app page clean: drop notices other plugins/WordPress print (reviews, promos, nags).
	 * The plugin's own flash notices are printed inside the app.
	 */
	private function silence_foreign_notices() {
		foreach ( array( 'admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices' ) as $hook ) {
			remove_all_actions( $hook );
		}
	}

	/**
	 * App shell: hero with section tabs and the (AJAX-swapped) body.
	 */
	public function render() {
		$current = $this->current_page();
		?>
		<div class="wrap yadambiar-admin" id="yadambiar-app">
			<?php Hero::render( __( 'اعلان موجودی و قیمت', 'yadambiar' ), $this->tabs( $current ) ); ?>
			<div id="yadambiar-body" aria-live="polite">
				<?php
				Flash::render();
				$current->render();
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Section tabs markup.
	 *
	 * @param AdminPage $current Open section.
	 * @return string
	 */
	private function tabs( AdminPage $current ) {
		$links = '';
		foreach ( $this->pages() as $slug => $page ) {
			if ( ! $page->in_menu() ) {
				continue;
			}
			$links .= sprintf(
				'<a class="nav-tab%1$s" data-view="%2$s" href="%3$s">%4$s<span>%5$s</span></a>',
				$page === $current ? ' nav-tab-active' : '',
				esc_attr( $slug ),
				esc_url( AdminUrl::view( $slug ) ),
				Icon::svg( $page->icon() ),
				esc_html( $page->title() )
			);
		}
		return '<nav class="yadambiar-tabs yadambiar-nav" aria-label="' . esc_attr__( 'بخش‌های یادم بیار', 'yadambiar' ) . '">' . $links . '</nav>';
	}

	/**
	 * Flash notices on other admin screens (the app prints its own).
	 */
	public function notices() {
		if ( ! $this->on_app_page() ) {
			Flash::render();
		}
	}

	/**
	 * App and product screen assets.
	 *
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( false === strpos( $hook, AdminUrl::PAGE ) && ! in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'yadambiar-admin', YADAMBIAR_URL . 'assets/css/admin.css', array(), YADAMBIAR_VERSION );
		$accent = sanitize_hex_color( (string) Settings::get( 'color_accent', '' ) );
		wp_add_inline_style( 'yadambiar-admin', '.yadambiar-admin{--ka:' . ( $accent ? $accent : '#f4511e' ) . ';}' );
		if ( 'toplevel_page_' . AdminUrl::PAGE === $hook ) {
			wp_enqueue_media(); // Media picker for image settings (sections load via AJAX, so always on the app page).
		}
		wp_enqueue_script( 'yadambiar-admin', YADAMBIAR_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), YADAMBIAR_VERSION, true );
	}

	/**
	 * Font/style of the plugin's sidebar menu (every admin page).
	 */
	public function menu_assets() {
		wp_enqueue_style( 'yadambiar-admin-menu', YADAMBIAR_URL . 'assets/css/admin-menu.css', array(), YADAMBIAR_VERSION );
	}
}

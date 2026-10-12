<?php
/**
 * A section of the single-page admin app.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Contracts;

defined( 'ABSPATH' ) || exit;

interface AdminPage {

	/**
	 * Section slug (the "view" query arg).
	 *
	 * @return string
	 */
	public function slug();

	/**
	 * Tab / menu label.
	 *
	 * @return string
	 */
	public function title();

	/**
	 * Icon name (see Support\Icon).
	 *
	 * @return string
	 */
	public function icon();

	/**
	 * Shown in the tabs and the sidebar menu?
	 *
	 * @return bool
	 */
	public function in_menu();

	/**
	 * Runs before any output (handle submitted list actions, redirects).
	 */
	public function load();

	/**
	 * Print the section.
	 */
	public function render();
}

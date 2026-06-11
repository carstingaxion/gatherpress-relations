/**
 * Date formatting utility for the Recent Roles block.
 *
 * HOW this module works:
 * ──────────────────────
 * Mirrors the PHP `GatherPress_Relations_Recent_Roles_Renderer::format_date()`
 * method, formatting a date string according to the block's `dateFormat`
 * attribute. This ensures the editor preview matches the frontend output.
 *
 * WHY a dedicated utility?
 * The PHP renderer formats dates with gmdate/wp_date. The editor JS
 * was previously hardcoded to extract only the year. This utility
 * replicates the three PHP format modes ('Y', 'M Y', 'full') so the
 * editor preview reflects the user's Date Format setting in real time.
 *
 * @package GatherPressRelations
 */

import { dateI18n, getSettings } from '@wordpress/date';

/**
 * Short month names used for the 'M Y' format.
 *
 * WHY not Intl.DateTimeFormat?
 * WordPress ships its own i18n date system (`@wordpress/date`) that
 * respects the site's locale settings. We use `dateI18n` for the
 * 'full' format. For 'M Y' we use a simple abbreviated month lookup
 * that matches PHP's `gmdate('M Y')` output (always English, 3-char).
 *
 * @type {string[]}
 */
const SHORT_MONTHS = [
	'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
	'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
];

/**
 * Formats a date string for the Recent Roles block preview.
 *
 * @param {string} dateString - A date string parseable by `new Date()`
 *                              (e.g., "2023-06-05 18:00:00" or ISO 8601).
 * @param {string} format     - The dateFormat attribute: 'Y', 'M Y', or 'full'.
 * @return {string} The formatted date, or '' if the input is invalid.
 */
export function formatRoleDate( dateString, format ) {
	if ( ! dateString ) {
		return '';
	}

	const date = new Date( dateString );

	if ( isNaN( date.getTime() ) ) {
		return '';
	}

	switch ( format ) {
		case 'Y':
			return String( date.getFullYear() );

		case 'M Y':
			return SHORT_MONTHS[ date.getMonth() ] + ' ' + date.getFullYear();

		case 'full': {
			/*
			 * Use @wordpress/date's dateI18n with the site's configured
			 * date format for locale-aware full-date rendering.
			 *
			 * WHY getSettings().formats.date?
			 * This reads the site's date_format option (e.g., 'F j, Y')
			 * as exposed by @wordpress/date, matching what wp_date() uses
			 * on the PHP side.
			 */
			const siteFormat = getSettings()?.formats?.date || 'F j, Y';
			return dateI18n( siteFormat, date );
		}

		default:
			return String( date.getFullYear() );
	}
}

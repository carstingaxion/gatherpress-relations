/**
 * Custom webpack configuration for the GatherPress Relations plugin.
 *
 * HOW this file works:
 * ────────────────────
 * wp-scripts uses a default webpack configuration that assumes a single
 * block with source files in `src/`. This plugin organises blocks under
 * `src/blocks/<slug>/`, so we override the entry points and output paths
 * to tell webpack where to find each block's source and where to emit
 * the compiled assets.
 *
 * WHY a custom webpack config?
 * The default wp-scripts config uses `src/index.js` as its single entry
 * point. With blocks nested under `src/blocks/roadmap/`, we need to
 * explicitly define each block as a separate entry so that:
 * 1. Each block gets its own `build/blocks/<slug>/` output directory.
 * 2. `block.json` asset paths (`file:./index.js`, `file:./view.js`)
 *    resolve correctly relative to the build output.
 * 3. Future blocks (cast-crew-list, person-profile-card, etc.) can be
 *    added as new entries without changing the build command.
 *
 * HOW to add a new block:
 * ───────────────────────
 * 1. Create the block source under `src/blocks/<new-slug>/`.
 * 2. Add a new entry in the `entry` object below:
 *    ```js
 *    'blocks/<new-slug>/index': './src/blocks/<new-slug>/index.js',
 *    ```
 * 3. If the block has a `viewScript`, add its entry too:
 *    ```js
 *    'blocks/<new-slug>/view': './src/blocks/<new-slug>/view.js',
 *    ```
 * 4. Run `npm run build` — the new block appears in `build/blocks/<new-slug>/`.
 *
 * @package GatherPressRelations
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,

	/**
	 * Entry points — one per block asset.
	 *
	 * WHY separate entries for index and view?
	 * `block.json` declares `editorScript: "file:./index.js"` and
	 * `viewScript: "file:./view.js"` as sibling paths. Webpack must
	 * emit each as a separate file in the block's output directory.
	 * A single entry would bundle everything into one file, breaking
	 * the `file:./view.js` reference.
	 *
	 * The keys use path separators (e.g., `blocks/roadmap/index`)
	 * so webpack emits the files into subdirectories matching the
	 * block structure.
	 */
	entry: {
		'blocks/roadmap/index': './src/blocks/roadmap/index.js',
		'blocks/roadmap/view': './src/blocks/roadmap/view.js',
		'blocks/cast-crew-list/index': './src/blocks/cast-crew-list/index.js',
		'blocks/recent-roles/index': './src/blocks/recent-roles/index.js',
		'plugins/cast-crew-sidebar/index': './src/plugins/cast-crew-sidebar/index.js',
	},

	/**
	 * Output configuration.
	 *
	 * WHY `path: build/`?
	 * All compiled assets land in `build/` at the plugin root. The
	 * entry keys create the subdirectory structure:
	 *   build/blocks/roadmap/index.js
	 *   build/blocks/roadmap/view.js
	 *   build/blocks/roadmap/index.css       (editor styles)
	 *   build/blocks/roadmap/style-index.css  (shared styles)
	 *   build/blocks/roadmap/index.asset.php  (dependency manifest)
	 *
	 * `register_block_type()` in the plugin bootstrap points to
	 * `build/blocks/roadmap/` so WordPress reads `block.json` and
	 * resolves all `file:./` paths from that directory.
	 */
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};

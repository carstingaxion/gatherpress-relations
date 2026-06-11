/**
 * Frontend interactivity for the GatherPress Relations Roadmap block.
 *
 * WHAT this script does:
 * ──────────────────────
 * 1. **Collapsible sections** — Each section header is a <button> that
 *    toggles the `--collapsed` modifier on its parent. The CSS transition
 *    on `max-height` and `opacity` produces a smooth expand/collapse.
 *
 * 2. **Smooth-scroll TOC links** — Clicking a TOC link scrolls the
 *    target section into view with `scrollIntoView({ behavior: 'smooth' })`.
 *
 * 3. **Active TOC highlighting** — An IntersectionObserver watches each
 *    section. When a section enters the viewport, its corresponding TOC
 *    link receives the `--active` modifier class.
 *
 * WHY plain JavaScript?
 * WordPress coding standards for blocks recommend plain JS with standard
 * DOM APIs for frontend interactivity. This avoids framework dependencies,
 * keeps the bundle tiny, and ensures compatibility with any theme.
 *
 * HOW multiple block instances are handled:
 * `querySelectorAll` finds all block instances on the page. Each instance
 * is initialised independently inside a `forEach` loop, so multiple
 * roadmap blocks on the same page do not interfere with each other.
 *
 * @package GatherPressRelations
 */

document.addEventListener( 'DOMContentLoaded', () => {
	/**
	 * Find every instance of the roadmap block on the page.
	 *
	 * WHY querySelectorAll?
	 * A page may contain multiple roadmap blocks (unlikely but possible).
	 * querySelectorAll returns a NodeList of all matches, and we
	 * initialise each one independently.
	 *
	 * @type {NodeListOf<HTMLElement>}
	 */
	const blocks = document.querySelectorAll(
		'.wp-block-gatherpress-relations-roadmap'
	);

	blocks.forEach( ( block ) => {
		initCollapsibleSections( block );
		initTocNavigation( block );
		initTocHighlighting( block );
	} );
} );

/**
 * Initialises collapsible section toggling for a single block instance.
 *
 * HOW it works:
 * ─────────────
 * Each section header is a <button> with `aria-expanded` and
 * `aria-controls` attributes (set by render.php). Clicking the button:
 * 1. Toggles the `--collapsed` CSS modifier on the section wrapper.
 * 2. Flips the `aria-expanded` attribute for screen readers.
 *
 * WHY aria-expanded?
 * It communicates the expand/collapse state to assistive technology.
 * Screen readers announce "collapsed" or "expanded" when the user
 * focuses the button.
 *
 * @param {HTMLElement} block - The block root element.
 * @returns {void}
 */
function initCollapsibleSections( block ) {
	const headers = block.querySelectorAll(
		'.gatherpress-relations__section-header'
	);

	headers.forEach( ( header ) => {
		header.addEventListener( 'click', () => {
			/**
			 * The section wrapper is the parent of the <button>.
			 *
			 * DOM structure (from render.php):
			 * ```html
			 * <div class="gatherpress-relations__section" id="ttr-section-1">
			 *   <button class="gatherpress-relations__section-header" aria-expanded="true" aria-controls="ttr-body-1">
			 *     ...
			 *   </button>
			 *   <div class="gatherpress-relations__section-body" id="ttr-body-1">
			 *     ...
			 *   </div>
			 * </div>
			 * ```
			 */
			const section = header.closest(
				'.gatherpress-relations__section'
			);

			if ( ! section ) {
				return;
			}

			const isCollapsed = section.classList.toggle(
				'gatherpress-relations__section--collapsed'
			);

			/**
			 * Update ARIA state.
			 *
			 * WHY string 'true'/'false'?
			 * `aria-expanded` is a string attribute, not a boolean
			 * DOM property. Setting it to the boolean `true` would
			 * work in most browsers but the spec defines it as an
			 * enumerated string.
			 */
			header.setAttribute(
				'aria-expanded',
				isCollapsed ? 'false' : 'true'
			);
		} );
	} );
}

/**
 * Initialises smooth-scroll behaviour for TOC links.
 *
 * HOW it works:
 * ─────────────
 * Each TOC link has an `href` like `#ttr-section-1`. Clicking it:
 * 1. Prevents the default hash-jump.
 * 2. Finds the target element by ID.
 * 3. Scrolls it into view with `behavior: 'smooth'`.
 * 4. Updates the URL hash without a jump (via `history.pushState`).
 *
 * WHY pushState instead of default hash navigation?
 * Default hash navigation causes an instant jump to the target.
 * `pushState` + `scrollIntoView` gives us smooth scrolling while
 * still updating the URL for shareability and back-button support.
 *
 * @param {HTMLElement} block - The block root element.
 * @returns {void}
 */
function initTocNavigation( block ) {
	const tocLinks = block.querySelectorAll(
		'.gatherpress-relations__toc-link'
	);

	tocLinks.forEach( ( link ) => {
		link.addEventListener( 'click', ( event ) => {
			event.preventDefault();

			const targetId = link.getAttribute( 'href' );

			if ( ! targetId ) {
				return;
			}

			/**
			 * Find the target section within the document (not scoped
			 * to the block) because IDs are document-global.
			 *
			 * @type {HTMLElement|null}
			 */
			const target = document.querySelector( targetId );

			if ( ! target ) {
				return;
			}

			/**
			 * Scroll with an offset to account for the admin bar
			 * and any sticky headers.
			 *
			 * WHY 'smooth'?
			 * Smooth scrolling is a better UX than an instant jump
			 * for intra-page navigation. The browser handles the
			 * easing natively.
			 */
			target.scrollIntoView( {
				behavior: 'smooth',
				block: 'start',
			} );

			/**
			 * Update the URL hash so the section is linkable.
			 */
			if ( window.history && window.history.pushState ) {
				window.history.pushState( null, '', targetId );
			}

			/**
			 * If the section is collapsed, expand it so the user
			 * sees the content they navigated to.
			 */
			if (
				target.classList.contains(
					'gatherpress-relations__section--collapsed'
				)
			) {
				target.classList.remove(
					'gatherpress-relations__section--collapsed'
				);
				const headerBtn = target.querySelector(
					'.gatherpress-relations__section-header'
				);
				if ( headerBtn ) {
					headerBtn.setAttribute( 'aria-expanded', 'true' );
				}
			}
		} );
	} );
}

/**
 * Highlights the active TOC link based on scroll position.
 *
 * HOW it works:
 * ─────────────
 * An IntersectionObserver watches each section element. When a section's
 * top edge crosses the 20% line from the top of the viewport (the
 * `rootMargin`), the observer fires and we mark its TOC link as active.
 *
 * WHY IntersectionObserver instead of scroll events?
 * 1. Performance — IO is optimised by the browser and does not fire
 *    on every scroll pixel.
 * 2. Accuracy — threshold-based detection is more reliable than manual
 *    offset calculations, especially with sticky headers or admin bars.
 * 3. Battery — fewer CPU wake-ups on mobile devices.
 *
 * PAYLOAD (IntersectionObserverEntry):
 * ```
 * {
 *   target:          HTMLElement,    // the observed section
 *   isIntersecting:  boolean,       // whether it crosses the root margin
 *   intersectionRatio: number,      // 0–1, how much is visible
 *   boundingClientRect: DOMRect,
 *   rootBounds:      DOMRect | null
 * }
 * ```
 *
 * @param {HTMLElement} block - The block root element.
 * @returns {void}
 */
function initTocHighlighting( block ) {
	const sections = block.querySelectorAll(
		'.gatherpress-relations__section'
	);
	const tocLinks = block.querySelectorAll(
		'.gatherpress-relations__toc-link'
	);

	if ( sections.length === 0 || tocLinks.length === 0 ) {
		return;
	}

	/**
	 * Observer options.
	 *
	 * WHY rootMargin: '-20% 0px -70% 0px'?
	 * This creates an observation zone that spans from 20% below the
	 * top of the viewport to 30% above the bottom. When a section's
	 * top edge enters this band, it's considered "in view". This
	 * compensates for the admin bar and ensures the active link
	 * updates before the section fills the screen.
	 *
	 * @type {IntersectionObserverInit}
	 */
	const observerOptions = {
		root: null,
		rootMargin: '-20% 0px -70% 0px',
		threshold: 0,
	};

	const observer = new IntersectionObserver( ( entries ) => {
		entries.forEach( ( entry ) => {
			if ( ! entry.isIntersecting ) {
				return;
			}

			const sectionId = entry.target.getAttribute( 'id' );

			if ( ! sectionId ) {
				return;
			}

			/**
			 * Remove the active class from all TOC links in this
			 * block instance, then add it to the matching link.
			 *
			 * WHY scoped to the block?
			 * Multiple block instances should not interfere with
			 * each other's TOC highlighting.
			 */
			tocLinks.forEach( ( link ) => {
				link.classList.remove(
					'gatherpress-relations__toc-link--active'
				);
			} );

			const activeLink = block.querySelector(
				`.gatherpress-relations__toc-link[href="#${ sectionId }"]`
			);

			if ( activeLink ) {
				activeLink.classList.add(
					'gatherpress-relations__toc-link--active'
				);
			}
		} );
	}, observerOptions );

	/**
	 * Observe each section element.
	 */
	sections.forEach( ( section ) => {
		observer.observe( section );
	} );
}

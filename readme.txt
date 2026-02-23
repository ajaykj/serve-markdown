=== Serve Markdown — Markdown for AI Agents ===
Contributors: akumarjain
Tags: markdown, ai, crawlers, seo, content-negotiation, geo, aeo
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.1-beta
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Serve Markdown versions of your posts and pages to AI agents and crawlers. Content negotiation, .md URLs, auto-discovery, crawler logging, and full admin controls.

== Description ==

**AI agents and crawlers** from ChatGPT, Claude, Perplexity, Google AI, and others are now reading your content to generate answers. They don't need your CSS, JavaScript, or navigation — they need your content in a clean, structured format.

Serve Markdown makes your WordPress site ready for these AI consumers by serving Markdown versions of your content — the format AI systems understand best.

= The Problem =

AI crawlers visit your site and parse through complex HTML, navigation menus, sidebars, footers, and JavaScript just to extract your actual content. Much of what makes your site look good to humans is noise to a machine. The result: AI systems may misinterpret, truncate, or ignore your content entirely.

= The Solution =

Serve Markdown gives AI agents a direct path to your content. When a crawler requests Markdown — either by sending an `Accept: text/markdown` header or by visiting a `.md` URL — your site responds with clean, well-structured Markdown complete with metadata. No noise, no guesswork.

= How It Works =

Serve Markdown adds three capabilities to your WordPress site:

**1. Content Negotiation**
When an AI crawler sends `Accept: text/markdown` in its HTTP headers, your site returns Markdown instead of HTML. This is the standard web mechanism for requesting alternative content formats — no URL changes needed.

**2. .md URL Suffix**
Append `.md` to any post or page URL to get the Markdown version. If your post lives at `example.com/my-article/`, the Markdown version is at `example.com/my-article.md`. Simple, predictable, bookmarkable.

**3. Markdown Auto-Discovery**
Every page includes a `<link rel="alternate" type="text/markdown">` tag in the HTML head — similar to how RSS feeds are discovered. AI crawlers that look for alternative formats will find your Markdown automatically.

= What the Markdown Output Looks Like =

Every Markdown response includes YAML frontmatter with structured metadata followed by your post content. Here is a real example from a live WordPress site running the plugin:

`---`
`url: 'https://akumarjain.com/textexpander-year-in-review-2025/'`
`title: TextExpander Year in Review 2025`
`author:`
`  name: Ajay`
`  url: 'https://akumarjain.com/author/akumarjain/'`
`date: '2026-01-05T09:05:00+05:30'`
`modified: '2026-01-10T14:30:06+05:30'`
`type: post`
`categories:`
`  - 2025`
`  - Year in Review`
`tags:`
`  - '#ToolsIUse'`
`  - Productivity`
`  - Remote Work`
`image: 'https://akumarjain.com/wp-content/uploads/text-expander-year-in-review-2025.webp'`
`published: true`
`---`

`# TextExpander Year in Review 2025`

`In my daily work, I need to type the same words and phrases repeatedly...`

You can see the full output at `https://akumarjain.com/textexpander-year-in-review-2025.md`. You control exactly which metadata fields appear, and you can add custom fields like a license URL or language code.

= Features =

**Content Serving**

* Content negotiation via `Accept: text/markdown` header
* `.md` URL suffix on any post or page
* Auto-discovery `<link rel="alternate">` tag in page head
* Each feature can be toggled independently
* Choose which post types are exposed (posts, pages, custom post types)

**Frontmatter and Metadata**

* YAML frontmatter with title, author, date, categories, tags, featured image, and more
* Toggle individual metadata fields on or off
* Add custom static key-value pairs (e.g., `license`, `language`)
* Map WordPress custom fields (post meta) into the frontmatter

**Access Control**

* Per-post opt-out via a sidebar checkbox in the editor
* Exclude entire categories from Markdown serving
* Exclude entire tags from Markdown serving
* Only published posts are served — drafts and private posts are never exposed

**Crawler Insights**

* Request log showing every Markdown request with timestamp, URL, bot name, and method
* Automatic bot detection: ClaudeBot, GPTBot, ChatGPT, OAI-SearchBot, PerplexityBot, Googlebot, Bingbot, Cohere, Meta AI, Bytespider, Applebot, and more
* Stats dashboard: total requests, requests today, unique bots
* Filter log by individual bot
* Configurable retention period with automatic pruning
* One-click log clearing

**Editor Integration**

* "Preview Markdown" button in the post editor sidebar
* "View Markdown" link in the Posts and Pages list table
* Meta box shows on all enabled post types

= Who Is This For? =

* **Bloggers and publishers** who want their content accurately represented in AI-generated answers
* **Content marketers** practicing Generative Engine Optimization (GEO) or Answer Engine Optimization (AEO)
* **Site owners** who want to understand which AI bots are reading their content and how often
* **Developers** building sites that serve structured content to both humans and machines

= Performance =

Serve Markdown has zero impact on your normal site performance. Markdown conversion only runs when specifically requested — your regular visitors see the same HTML pages they always have. The crawler log uses a lightweight custom database table with three independent safeguards against unbounded growth: time-based retention, a max row count cap, and a max table size cap.

= Privacy =

The crawler log stores IP addresses and user-agent strings from Markdown requests. This data stays in your WordPress database and is never sent to external services. You can disable logging entirely, configure a retention period, or clear the log at any time from the settings page.

== Installation ==

1. Upload the `serve-markdown` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings > Serve Markdown** to configure.
4. Visit any published post URL with `.md` appended to verify it works.

No additional server configuration is required. The plugin works with all standard WordPress permalink structures.

== Frequently Asked Questions ==

= Do I need to configure anything? =

The plugin works out of the box with sensible defaults: posts and pages are served as Markdown with all metadata fields enabled and crawler logging turned on. Visit **Settings > Serve Markdown** to customize.

= Will this affect my site speed? =

No. Markdown is generated on demand only when requested via the `.md` URL or `Accept: text/markdown` header. Normal page loads are completely unaffected.

= Does it work with custom post types? =

Yes. Any public post type registered on your site will appear as a checkbox on the General settings tab.

= Can I exclude specific posts? =

Yes, in two ways. You can check "Disable Markdown for this post" in the editor sidebar for individual posts. You can also exclude entire categories or tags from the Exclusions settings tab.

= What metadata is included? =

By default: URL, title, author name and URL, publish date, last modified date, post type, excerpt, categories, tags, featured image URL, and published status. Each field can be toggled off individually. You can also add custom static fields and map WordPress custom fields.

= What AI crawlers are detected? =

ClaudeBot (Anthropic), GPTBot and OAI-SearchBot (OpenAI), ChatGPT-User, Google-Extended (Google AI), PerplexityBot, Bingbot, Cohere, Meta AI, Bytespider (ByteDance), Applebot, CCBot (Common Crawl), YouBot, and a catch-all for unrecognized bots.

= Does the crawler log slow down Markdown responses? =

The log insert is a single database write that adds negligible latency. If you prefer, logging can be disabled entirely from the Crawler Log settings tab.

= Is this compatible with caching plugins? =

Yes. Serve Markdown sets the `DONOTCACHEPAGE` constant when serving Markdown responses, which is respected by WP Super Cache, W3 Total Cache, WP Rocket, and most other caching plugins.

= Does this work on WordPress.com? =

Yes. The plugin uses `WPINC` for environment detection instead of `ABSPATH`, making it compatible with WordPress.com Business plans, WordPress VIP, Pantheon, and other managed hosting platforms.

== Screenshots ==

1. General settings tab — toggle features and select post types.
2. Frontmatter settings — control metadata fields and add custom fields.
3. Exclusions — block categories, tags, or individual posts.
4. Crawler log — see which AI bots are reading your content.
5. Post editor meta box with disable toggle and preview button.
6. "View Markdown" link in the post list.

== Changelog ==

= 0.1-beta =
* Initial beta release.
* Content negotiation, .md URL suffix, and Markdown auto-discovery.
* Admin settings panel with four tabs (General, Frontmatter, Exclusions, Crawler Log).
* Configurable post type support for posts, pages, and custom post types.
* Independent feature toggles for content negotiation, .md URLs, and auto-discovery.
* Frontmatter field controls with per-field on/off toggles.
* Custom static fields and post meta key mapping for frontmatter.
* Category and tag exclusion rules.
* Per-post opt-out via editor meta box.
* Password-protected posts are blocked from Markdown serving and discovery.
* .md URLs work with all permalink structures (post-name, date-based, numeric, pages) via url_to_postid().
* RFC-compliant Accept header parsing (case-insensitive, rejects q=0, ignores partial media type matches).
* Crawler request logging with automatic bot detection.
* Stats dashboard with total, daily, and per-bot request counts.
* Log filtering by bot name with pagination.
* Configurable log retention with automatic pruning.
* Max entries and max table size caps for crawler log.
* Markdown preview from post list row actions and editor meta box.
* HTML table to Markdown table conversion.
* WPINC-based environment detection for managed hosting compatibility.

== Upgrade Notice ==

= 0.1-beta =
Initial beta release.

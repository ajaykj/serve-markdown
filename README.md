# Serve Markdown

A WordPress plugin that serves clean Markdown versions of your content to AI agents and crawlers.

![WordPress 6.5+](https://img.shields.io/badge/WordPress-6.5%2B-blue) ![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-777BB4) ![License: GPL-3.0](https://img.shields.io/badge/License-GPL--3.0-green)

## Overview

AI agents from OpenAI, Anthropic, Google, Perplexity, and others are crawling the web constantly. They read your content not to index it for a search results page, but to understand it — so they can answer questions, summarize topics, and generate responses. These AI consumers don't care about your CSS or JavaScript. What they want is your content: clean, structured, and easy to parse.

Serve Markdown adds three access methods to your WordPress site so AI crawlers get exactly that. Every Markdown response includes YAML frontmatter with structured metadata — title, author, date, categories, tags, featured image — followed by your post content converted from WordPress HTML to clean Markdown. Zero configuration required: install, activate, and it works.

## Features

- **Content Negotiation** — Responds with Markdown when a request sends `Accept: text/markdown`
- **.md URLs** — Append `.md` to any post or page URL to get the Markdown version
- **Auto-discovery** — Adds `<link rel="alternate" type="text/markdown">` to every page head
- **Frontmatter** — YAML metadata block with configurable fields on every response
- **Crawler Log** — Identifies and logs AI bot requests with per-bot dashboard
- **Admin Controls** — Toggle features, choose post types, exclude categories/tags, map custom fields

## Requirements

- WordPress 6.5+
- PHP 8.0+

## Installation

1. Upload the `serve-markdown` folder to `wp-content/plugins/`
2. Activate the plugin in **Plugins > Installed Plugins**
3. Visit **Settings > Serve Markdown** to review defaults
4. Test by appending `.md` to any published post URL

## Changelog

### 0.1-beta

Initial release. Content negotiation, .md URLs, auto-discovery link tags, YAML frontmatter, crawler logging with bot identification, and full admin controls.

## License

GPL-3.0-or-later — see [LICENSE](LICENSE) for the full text.

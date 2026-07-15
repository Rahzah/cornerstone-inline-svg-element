# Eubuleus Inline SVG for Cornerstone

![Version](https://img.shields.io/badge/version-1.0.1-3c4043)
![WordPress](https://img.shields.io/badge/WordPress-6.6%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)

An unofficial plugin for [Theme.co Pro and Cornerstone](https://theme.co/pro)
that adds a native SVG element to the builder. It lets you select SVG files
from the WordPress Media Library and render them as true, sanitized inline SVGs
instead of `<img>` elements.

The element integrates with the familiar Cornerstone workflow, including
responsive design controls, Customize CSS, parameters, Dynamic Content,
Loopers, links, effects, custom attributes, and accessibility settings.

> **Unofficial project:** This plugin is not affiliated with, endorsed by, or
> supported by Themeco.

## Why inline SVG?

An SVG loaded through an `<img>` element is isolated from the page. Its internal
paths, groups, fills, strokes, gradients, and masks cannot be targeted directly
with the page's CSS.

This plugin inserts the sanitized `<svg>` markup into the document, making it
possible to:

- style individual SVG parts with Cornerstone Customize CSS;
- inherit colors through `currentColor`;
- animate paths, groups, fills, and strokes;
- provide meaningful `<title>` and `<desc>` accessibility information;
- use Dynamic Content and parameters for the source and text fields;
- reuse Media Library SVGs without manually copying their markup.

## Features

- Native **Inline SVG** element in Cornerstone's **Media** group
- WordPress Media Library integration restricted to SVG files
- True inline `<svg>` output—never an `<img>` fallback
- Absolute Media Library URLs and native references such as `5501:raw`
- Cornerstone Dynamic Content, parameters, and Looper Consumer support
- Responsive width, height, maximum size, and aspect-ratio controls
- Margin, padding, background, border, radius, and box-shadow controls
- Links, interaction colors, effects, custom attributes, IDs, and classes
- Accessible title, description, decorative mode, and link label
- Per-instance ID namespacing for gradients, masks, clip paths, and references
- Fail-closed SVG validation and sanitization through Safe SVG
- Removal of remote references from inline SVG markup

## Requirements

| Dependency | Minimum |
| --- | --- |
| WordPress | 6.6 |
| PHP | 7.4 with DOM/XML |
| Theme.co | Pro or Cornerstone with the Element API |
| SVG uploads | [Safe SVG](https://wordpress.org/plugins/safe-svg/) must be active |

The element remains disabled when Safe SVG or the PHP DOM/XML extension is not
available. Safe SVG sanitizes files during upload; this plugin deliberately
sanitizes them again immediately before inline rendering.

## Installation

### WordPress Admin

1. Download the latest plugin ZIP.
2. Open **Plugins → Add New Plugin → Upload Plugin** in WordPress.
3. Select the ZIP and choose **Install Now**.
4. Activate **Safe SVG** and **Eubuleus Inline SVG for Cornerstone**.

### Manual installation

1. Copy the `eubuleus-inline-svg` directory to `wp-content/plugins/`.
2. Activate **Safe SVG**.
3. Activate **Eubuleus Inline SVG for Cornerstone**.

## Usage

1. Open a page, component, header, footer, or layout in Cornerstone.
2. Add **Inline SVG** from the **Media** element group.
3. Open **Source → SVG File** and select an SVG from the Media Library.
4. Configure sizing and appearance under **Setup**, **Size**, and **Design**.
5. Configure semantics under **Accessibility**.
6. Use **Customize → CSS** to target the inline SVG or its internal elements.

### Supported source formats

The source may be stored as:

```text
https://example.com/wp-content/uploads/logo.svg
5501:raw
5501:full
5501
```

For `5501:raw`, `5501` is the WordPress attachment ID. The size suffix is
irrelevant for an inline SVG and is intentionally ignored after the attachment
has been validated.

Remote URLs and SVG files outside the local WordPress uploads directory are
rejected.

## Accessibility

### Informative SVGs

Leave **Decorative** disabled and provide an **Accessible Title**. A description
can be added when the graphic conveys more detail.

If the title field is empty, the plugin uses this fallback order:

1. Media Library alt text
2. the original `<title>` inside the SVG
3. the Media Library attachment title

The rendered SVG receives `role="img"` with correctly associated `<title>` and
`<desc>` elements.

### Decorative SVGs

Enable **Decorative** for graphics that do not communicate content. The SVG is
then rendered with `aria-hidden="true"` and without image semantics.

### Linked SVGs

Use **Link Label** to give the link an accessible name. When it is empty, the
plugin uses the same title fallback chain automatically. This also keeps links
containing decorative SVGs accessible.

## Styling with Cornerstone

Because the SVG is inline, Cornerstone Element CSS can address its internal
markup directly through `$el`.

### Inherit the element color

```css
$el {
  color: #7c3aed;
}

$el svg .brand-primary {
  fill: currentColor;
}
```

### Animate paths on hover

```css
$el svg path {
  transition: fill 200ms ease, stroke 200ms ease;
}

$el:hover svg path {
  fill: #7c3aed;
}
```

### Target an original SVG ID

Internal IDs are prefixed at render time to prevent collisions. Prefer stable
classes inside the source SVG when adding custom styles:

```css
$el svg .icon-accent {
  fill: currentColor;
}
```

If a source SVG contains highly specific inline `style` attributes, a more
specific selector—or occasionally `!important`—may be required to override
them.

## Dynamic Content and parameters

The SVG source, accessible title, description, and link label are Cornerstone
element values and can use Dynamic Content or component parameters. A Dynamic
Content source must ultimately resolve to a local Media Library attachment ID,
an `ID:size` reference, or its local attachment URL.

Examples:

```text
5501:raw
{{dc:acf:post_field field="company_logo"}}
{{dc:p:company_svg}}
```

The exact Dynamic Content expression depends on the provider and the return
format configured in WordPress. For ACF image/file fields, an attachment ID or
URL return value is recommended.

## Security model

Inline SVG must be treated as active document markup. The renderer therefore
uses a deliberately restrictive pipeline:

1. Resolve the source to a local WordPress attachment.
2. Confirm the attachment MIME type is `image/svg+xml`.
3. Confirm the attached file uses the `.svg` extension.
4. Confirm the real file is inside the WordPress uploads directory.
5. Reject files larger than 2 MiB by default.
6. Sanitize the markup again with Safe SVG's sanitizer.
7. Remove remote references.
8. Parse the result as XML without network access.
9. Prefix internal IDs and their local references per rendered instance.

Unsafe, invalid, remote, or unreadable sources are not rendered on the front
end. In the builder preview, a diagnostic placeholder is shown instead.

### Adjust the file-size limit

```php
add_filter( 'eub_inline_svg_max_file_size', function () {
    return 4 * MB_IN_BYTES;
} );
```

## Troubleshooting

### The element is missing from Cornerstone

- Confirm that Pro/Cornerstone is active.
- Confirm that Safe SVG is active.
- Confirm that PHP has the DOM/XML extension enabled.
- Reload the builder after activating the dependencies.

### An absolute URL works, but `5501:raw` does not

Update to version 1.0.1 or newer. Native `attachment-id:size` references were
added in version 1.0.1.

### CSS does not change a path or fill

Inspect the SVG for inline `style`, `fill`, or `stroke` declarations. Target a
class on the relevant SVG node and increase selector specificity if necessary.

### An SVG disappears on the front end

The source did not pass the local attachment, file-type, size, XML, or sanitizer
checks. Open the element in Cornerstone to view its diagnostic placeholder.

## Project structure

```text
eubuleus-inline-svg/
├── assets/
│   └── inline-svg.css
├── includes/
│   ├── class-eub-inline-svg.php
│   └── element-inline-svg.php
├── eubuleus-inline-svg.php
└── README.md
```

- `eubuleus-inline-svg.php` boots the plugin and declares its requirements.
- `element-inline-svg.php` registers the native Cornerstone element, controls,
  values, styles, and integration options.
- `class-eub-inline-svg.php` resolves attachments, sanitizes and transforms SVG
  markup, applies accessibility semantics, and renders the element.

## Contributing

Issues and pull requests are welcome. When reporting a problem, include:

- WordPress, PHP, and Pro/Cornerstone versions;
- Safe SVG version;
- whether the issue occurs in the builder, front end, or both;
- the source format used, such as an absolute URL or `5501:raw`;
- a minimal SVG reproducer when it can be shared safely.

Do not include Pro, Cornerstone, license-key, customer, or private site files in
issues or pull requests.

## License

Eubuleus Inline SVG for Cornerstone is licensed under the
[GNU General Public License v2.0 or later](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
(`GPL-2.0-or-later`).

The repository must not include or redistribute Theme.co Pro or Cornerstone
files. Users are responsible for obtaining and maintaining the appropriate
Theme.co license for their installation.

## Trademark notice

Theme.co, Pro, and Cornerstone are names or trademarks of their respective
owners. Their use here is solely to describe compatibility. This project is an
independent, unofficial extension and is not affiliated with, endorsed by, or
supported by Themeco.

## Changelog

### 1.0.1

- Added support for Cornerstone attachment references such as `5501:raw`.
- Added Dynamic Content resolution before SVG source validation.

### 1.0.0

- Initial release.

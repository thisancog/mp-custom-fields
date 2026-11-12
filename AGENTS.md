# MP Custom Fields – guide for LLMs and coding agents

This file is for AI assistants and coding agents that **use** the plugin (register fields, read values) or **change** it. It is a compact reference; [README.md](README.md) has the full documentation for people. If the two disagree, the code is right; please fix the docs.

---

## 1. What the plugin is

A WordPress plugin that renders custom fields as a panelled GUI in the admin: meta boxes on posts, module boxes, term, user, post type archive and options pages. Fields are defined in PHP arrays. Values are stored in the normal WordPress meta/option tables and read with the `mpcf_get_field()` family.

The plugin runs on many production sites. **Every change must be backwards compatible**, see [Section 6](#6-rules-for-changing-the-plugin).

---

## 2. Using the API

### 2.1 Registering fields

Register on `init` (priority 20 or later, after post types and taxonomies), and guard against the plugin being inactive:

```php
add_action('init', function() {
	if (!function_exists('mpcf_add_custom_fields')) return;

	mpcf_add_custom_fields('page', 'page-options', array(
		'title'  => 'Page options',
		'panels' => array(
			array(
				'name'   => 'General',
				'icon'   => 'dashicons-admin-generic',
				'fields' => array(
					array('name' => 'subtitle', 'title' => 'Subtitle', 'type' => 'text'),
					array('name' => 'hero',     'title' => 'Hero image', 'type' => 'media')
				)
			)
		)
	));
}, 20);
```

| Where | Register | Remove | Stored in |
|---|---|---|---|
| Posts, pages, CPTs | `mpcf_add_custom_fields($postType, $id, $args)` | `mpcf_remove_custom_fields($id)` | post meta, one key per top-level field |
| Module box | `mpcf_add_module_metabox($postType, $id, $args)` (`base_name`, `modules`) | `mpcf_remove_module_metabox($id)` | one post meta key (`base_name`, default `modules`): array of modules with `module_type` |
| Terms | `mpcf_add_custom_fields_taxonomy($taxonomy, $id, $args)` | `mpcf_remove_custom_fields_taxonomy($id)` | term meta |
| Users | `mpcf_add_custom_fields_user($roles, $id, $args)` (`$roles`: role, array or `'all'`) | `mpcf_remove_custom_fields_user($id)` | user meta |
| Post type archive | `mpcf_add_custom_fields_archive($postType, $id, $args)` | `mpcf_remove_custom_fields_archive($postType)` (**deletes the values**) | option `mpcf_archive_meta` |
| Options page | `mpcf_register_singleton($singletonID, $args)` + `mpcf_add_custom_fields_singleton($singletonID, $id, $args)` | `mpcf_remove_custom_fields_singleton($id)`, `mpcf_remove_singleton($singletonID)` | option `mpcf_{singletonID}` |

Box `$args` for posts: `title`, `context` (`normal`/`side`/`advanced`), `priority`, `post_format`, `page_template` (comma list or array; a leading `-` excludes; `frontpage`, `postspage`), `panels`. Box definitions are cached in options and only rewritten when they change, so registering on every request is cheap.

A box stays registered until it is removed. Removing a box from your code is **not** enough; call the matching `mpcf_remove_*` function.

### 2.2 Field definition

```php
array(
	'name'        => 'price',        // required: storage key, unique within its level
	'type'        => 'number',       // required: see 2.3
	'title'       => 'Price',
	'description' => 'Help text',
	'default'     => 10,
	'required'    => false,
	'inputClass'  => '',
	'actions'     => array(          // optional callbacks
		'get_default'    => function($postID, $name, $value) { return $value; },
		'display_before' => function($postID, $name, $value) { return $value; },   // also applied by mpcf_get_field()
		'save_before'    => function($postID, $name, $value) { return $value; },
		'save_after'     => function($postID, $name, $value, $oldValue) {}
	),
	'show_if'     => array('currency' => 'EUR')   // optional, see 2.5
	// + type-specific settings
)
```

### 2.3 Field types

| Group | Types (type-specific settings) |
|---|---|
| Text | `text` (`placeholder`, `minlength`, `maxlength`, `list`), `textarea` (`placeholder`, `rows`, `addparagraphs`), `editor` (`height`, `rows`, `mediabuttons`, `minimaleditor`, `dragdrop`, `addparagraphs`, `css`), `email`, `password` |
| Numbers | `number` (`min`, `max`, `step`), `range` |
| Date/time | `date`, `datetime`, `month`, `week`, `time` (`min`, `max`, `step`) |
| Choices (`options` = `value => label`) | `select` (`multiple`, `size`), `radio`, `buttongroup` (`multiple`), `imagebuttongroup` (`value => image URL or Dashicons class`), `checkbox` (`label`, stores `checked`), `truefalse` (stores `true`/`false`), `dragdroplist`, `postselect` (`query`, `filter`), `colorselect` |
| Media | `media`, `file` (attachment ID), `bulkmedia` (attachment IDs, `extensions`) |
| Other | `color`, `grid`, `table`, `map`, `hidden`, `divider` (stores nothing), `custom` (`callback`) |
| Nested | `repeater` (`fields`, `maxrows`), `conditional` (`options` = `value => array('title', 'fields')`), `conditionalpanels` (`options` = `value => array('title', 'panel' => panel)`) |

Nested types can be combined freely (conditionals in repeaters, repeaters in conditional panels, …).

Options may be dynamic (lists of posts etc.). The plugin never caches them beyond one page load, so don't add caching for options either.

### 2.4 Reading values

```php
mpcf_get_field('subtitle');                       // current post
mpcf_get_field('subtitle', $postID);
mpcf_get_field('color', $termID, 'tax');
mpcf_get_field('twitter', $userID, 'user');
mpcf_get_field('intro', 'project', 'archive');    // archive: the post type instead of an ID
mpcf_has_field('subtitle');                       // '0' counts as a value
mpcf_the_field('subtitle');                       // echo, current post
mpcf_get_option('phone', 'mpcf_site-options');    // options page
```

Shortcuts: `mpcf_get_tax_field()`, `mpcf_has_tax_field()`, `mpcf_get_user_field()`, `mpcf_has_user_field()`, `mpcf_has_option()`, `mpcf_get_archive_meta()`.

Value shapes:
- **repeater:** an array of rows, each an array of `name => value`.
- **conditional:** `array('type' => chosenOption, <fields of that option>)`.
- **conditionalpanels:** the same, plus `panel_id`.
- **media/file:** an attachment ID.
- **select with `multiple`:** an array.

Prefer these functions over `get_post_meta()`, because they apply `display_before` and know where each location stores its values.

### 2.5 `show_if`: showing fields depending on other fields

`show_if` works on any field and on panels. Hidden fields are **only hidden**: their values are still submitted and saved, and a hidden field's value still counts in other conditions. Code that reads values must check the condition itself if a hidden value should be ignored.

Forms:
- **One condition:** `array('field' => 'url', 'operator' => 'not_empty')`.
- **Operators:** `=` (default, also `==`), `!=` (also `<>`), `in`, `not_in`, `empty`, `not_empty`, `checked`, `not_checked`, `>`, `<`, `>=`, `<=`, `contains`.
- **Arrays as values:** `=` and `!=` with an array value become `in` and `not_in`.
- **Shorthand:** `array('layout' => 'gallery')`, or `array('layout' => array('a', 'b'))` for one of several values. Several keys mean all of them must hold. Field names `field`, `any` and `all` can't be used in the shorthand.
- **A list** of conditions means all of them must hold. `array('all' => array(...))` is the same.
- **`array('any' => array(...))`** means at least one must hold. Both can be nested.

Comparison:
- Values are compared as strings.
- PHP `true`/`false` match the checkbox and truefalse values (`true`, `1`, `on`, `checked`, `yes` and `false`, `0`, empty, `no`).
- Numeric operators are false if either side isn't a number.

Which field a name refers to:
- The name refers to the field **at the same level**: the same box (across panels), the same repeater row, conditional field, conditional panel or module.
- Each leading `../` goes up one level, e.g. `'../layout'` from inside a repeater row.

Error handling:
- Invalid conditions are logged with `WP_DEBUG` and left out. A field never gets hidden by a mistake.
- A condition on a field that doesn't exist counts as met, and is reported in the console with the prefix `[MPCF]`.
- Hidden fields lose `required` until they are shown again.

For your own scripts:
- The JS event `mpcf:visibility` (`event.detail.visible`) bubbles up from the field or panel.
- The class `mpcf-hidden-by-condition` marks hidden elements.

### 2.6 Filters

| Filter | Purpose |
|---|---|
| `mpcf_options_store_enabled` | `false`: print the options of conditional fields inline again |
| `mpcf_ajax_definition_allowed` | `($allowed, $definition)`: allow AJAX rendering of definitions that aren't part of a registered box (custom admin pages) |
| `mpcf_enable_bulk_copypaste_panels` | `false`: hide the copy/paste panel |
| `mpcf_warn_unsaved_changes` | `false`: no "leave page?" warning |

### 2.7 Own field types

Add a class extending `MPCFModule` in `inc/modules/`: the files there are loaded and registered automatically by `public $name`.

- **`build_field($args)`** echoes the field's HTML and returns `true` if it contains required inputs.
- **Names and IDs** always come from `mpcf_input_name($this)`, `mpcf_input_id($this)` and `mpcf_input_own_name($this)`. Otherwise the field breaks inside repeaters and conditionals.
- **Optional methods:** `get_default`, `display_before`, `save_before`, `save_after`.

---

## 3. File map

| Path | Content |
|---|---|
| `mp-custom-fields.php` | Plugin header, script/style loading, `localizedmpcf` (translations, AJAX nonce, `warnUnsaved`) |
| `inc/includes.php` | Requires everything else |
| `inc/gui.php` | Rendering: panels, `mpcf_build_gui_from_fields()`, `show_if` normalisation (`mpcf_normalize_show_if()`, `mpcf_show_if_attribute()`), options store, conditional panels (`MPCFRecursiveConditionalPanelsAdder`), AJAX handlers for repeater rows/conditionals, saving (`mpcf_save_meta_boxes()`), input name/ID helpers |
| `inc/mpcf-ajax.php` | Nonce, `mpcf_ajax_get_verified_definition()` (AJAX definitions must belong to a registered box the user may edit), `mpcf_log()` |
| `inc/mpcf-submission.php` | Saving only complete forms: the `mpcf-interface-ready` marker, `mpcf_submission_is_complete()`, notice |
| `inc/mpcf-register-*.php` | Registration/rendering/saving per location (posts, modules, taxonomies, users, archives, singletons) |
| `inc/mpcf-modules.php`, `inc/modules/*.php` | Field type registry and one class per field type |
| `inc/mpcf-pluginsettings.php` | Settings page (Maps key, bulk copy/paste, …) |
| `inc/js/admin.js` | The whole client: building, renaming, repeaters, conditionals, modules, editors, media, saving guard, unsaved-changes warning, `show_if`. Sections are documented at the top of the file. |
| `inc/admin.css` | Admin styles |
| `tests/php/`, `tests/browser/` | Tests (see Section 5) |
| `gulpfile.js`, `package.json` | Build check and commit tasks |

---

## 4. How the client works (inc/js/admin.js)

- **Initialisation:** `init()` runs on `DOMContentLoaded` and waits for `wp.svgPainter`. Then `batchRenames(build)` registers all components and renames once. After that, the interface counts as ready and saving is allowed.
- **Field names:**
  - They are composed in the browser from `data-basename` on ancestors, the repeater row index and each input's own name (`data-name`). `generateName()` and `composeName()` do this.
  - They are recomputed after adding, moving or removing rows and after loading fields, by `renameDynamicFields()`, `batchRenames()` and `flushRenames()`.
  - These names **are the storage keys**, so they must never change for existing definitions.
  - `flushRenames()` also restores checked radio buttons: while rows are being renamed, two rows can share a radio group for a moment. It then runs `show_if`.
- **AJAX:**
  - Repeater rows, conditional fields, conditional panels and modules are rendered by the server via `admin-ajax`, posting the field definition.
  - The server only renders definitions that belong to a registered box.
  - Each request is wrapped in `trackRequest()`. Saving is blocked while any request is pending.
- **Saving only complete forms:**
  - The hidden `mpcf-interface-ready` input is `1` only when the interface is built and no requests are pending.
  - A submit listener (capture phase, attached at script load) blocks submits, and the block editor is locked via `wp.data`.
  - The server skips saving the custom fields if the marker is `0`, because missing fields would be saved as deletions.
- **Options store:** the large `options` of conditional fields and panels are printed once per page as `<script type="application/json" class="mpcf-options-store">` and referenced with `data-options-ref`.
- **Editors:**
  - They are rendered with TinyMCE off and initialised lazily when visible (IntersectionObserver).
  - Before an editor's DOM is moved, `detachEditors()` must run, and `registerEditors()` after it, because TinyMCE iframes don't survive DOM moves.
- **Repeater rows:** moved with buttons, pointer drag, or arrow keys on the handle. All listeners are delegated per rows wrapper.
- **Unsaved changes:**
  - A snapshot of all field values is taken after init and compared on `beforeunload`.
  - Editors are tracked via TinyMCE's dirty state.
  - The snapshot is reset after saving without a page reload (block editor, "add term").
- **Errors:**
  - `reportError()` logs with the prefix `[MPCF]`.
  - `runSafely()` also blocks saving, so use it only where a failure can leave fields incomplete.

---

## 5. Building and testing

```sh
npm install && npx playwright install chromium   # once
npx gulp build     # php -l, node --check, all tests; must pass before committing
npx gulp test      # tests only
php tests/php/run.php
node tests/browser/run.js                        # CHROME_PATH=/path/to/chrome to use a local Chrome
```

- **PHP tests** (`tests/php/test-*.php`):
  - Each file runs in its own process, with WordPress stand-ins from `bootstrap.php`.
  - Helpers: `test($name, fn)`, `assert_same`, `assert_true`, `assert_snapshot`.
- **Browser tests** (`tests/browser/tests/*.test.js`):
  - They run in Chromium on generated pages (`fixtures.js`: `buildPage()`, `ROW_CONTROLS`), using the real `admin.js` and `admin.css`.
  - `ctx.openPage({ body, ajax, bodyEnd })` returns `{ page, errors, dialogs, requests, submitted }`.
  - Use `ctx.dumpFields(page)` to compare names.
- **Snapshots** (`tests/*/snapshots/`):
  - The field names, IDs and conditional-panel results were generated with the **original** implementation.
  - **Never update the field name snapshots to make a test pass.** A difference means stored data would no longer be found.
  - `MPCF_ADMIN_JS` and `MPCF_GUI_PHP` run the tests against another version for comparison.

---

## 6. Rules for changing the plugin

1. **No breaking changes.** The registration API, the definition format, the reading functions, the filters and the actions must keep working for existing sites. New features are opt-in (new optional settings, new filters with the old behaviour as default).
2. **Never change how field names are built.** Values in the database are keyed by them. Renaming a key strategy, adding a level, or changing the repeater indexing makes existing data unreadable.
3. **Never let incomplete forms save.** Any new asynchronous loading must go through `trackRequest()` (JS). Any new save path must check `mpcf_submission_is_complete()` (PHP).
4. **No long-lived caches of field definitions or options.** Options are often dynamic (posts, terms). A cache is fine for one request or page load.
5. **AJAX:** new handlers must check the nonce (`check_ajax_referer(MPCF_AJAX_NONCE_ACTION, 'nonce')`) and capabilities, and only render definitions verified with `mpcf_ajax_get_verified_definition()`.
6. **Hidden ≠ removed.** Anything that hides fields must keep them submitted, never remove or disable them.
7. **Errors:** report them, don't swallow them. JS: `reportError()`. PHP: `mpcf_log()` (only with `WP_DEBUG`).
8. **Deliverables for the maintainer:** complete files or complete copy-paste snippets. Don't resend unchanged files, and say which files changed.
9. **Code style:**
   - Tabs, aligned assignments, and comments where the reason isn't obvious.
   - Old-style `var x = function() {...};` is used in `admin.js`, with `class` for components.
   - Keep comments in the existing tone; the field type modules don't need comments.

### Pitfalls found in this code base

- **Missing semicolons after function expressions.** A `var f = function() {}` followed by a line starting with `[` or `(` is parsed as a call or index of the function. This has broken the whole interface twice. Always end function expressions with `};`.
- **`esc_attr()` doesn't double-encode entities.** JSON in attributes must be encoded with `JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG` first (see `mpcf_options_data_attribute()` and `mpcf_show_if_attribute()`).
- **Radio groups and renaming.** Giving a radio the name of an existing group unchecks one of them. Restore the selection after renaming (see `flushRenames()`).
- **Moving DOM that contains editors.** Detach and re-register editors, as described in Section 4.
- **`mpcf_get_archive_boxes()` loses its keys** (usort). Read `get_option('mpcf_archive_boxes')` directly if you need the IDs.
- **Duplicate IDs.** Field wrappers use `id="mpcf-field-{own name}"`, which repeats in repeater rows. Search within a scope, never with `document.getElementById()`.
- **Headless Chrome doesn't show `beforeunload` dialogs.** Test the handler by dispatching a cancelable `beforeunload` event and checking `defaultPrevented`.

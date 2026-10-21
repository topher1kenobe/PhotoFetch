# PhotoFetch — Documentation

Version 2.0.0 · Last reviewed 2026-10-07

This file contains three documents:

1. [User Guide](#user-guide) — for site owners and editors
2. [Troubleshooting Guide](#troubleshooting-guide) — internal reference for technical support
3. [Feature Overview](#feature-overview) — client handoff, usually for marketing

---
---

# User Guide

## Introduction

The PhotoFetch adds a photo search tool to the WordPress admin. It connects to the public WordPress Photo Directory at wordpress.org/photos, where every photo is released under the CC0 license. Site editors can search that library, pick one photo or several, and import them into the site's own Media Library without leaving the admin.

Once a photo is imported, it becomes a normal attachment. It works as a featured image, inside blocks, in galleries, and anywhere else an uploaded image works.

## How It Works

The plugin adds three ways to reach the photo search.

| Entry Point | Where It Appears | What It Does |
|---|---|---|
| **Media > Photo Directory** | A page under the **Media** menu | Opens a full browse screen with filters and bulk import |
| **Photo Directory** tab | Inside the standard WordPress media picker, next to **Upload files** and **Media Library** | Opens a two-pane picker inside the window that is already open |
| **Photo Directory** button | Next to **Add Media** in the classic editor | Opens a search grid in a pop-up window |

All three connect to the same photo library. They differ in how much control they offer and in what happens after an import finishes.

### The Browse Screen

**Media > Photo Directory** is the fullest version of the tool. It opens on a set of recently added photos and offers these controls:

| Control | What It Does |
|---|---|
| Search field | Filters the library by subject, place, or tag. Results refresh shortly after typing stops, and **Search** or the Enter key runs the search right away |
| **All categories** | Limits results to one category from the Photo Directory |
| **Any orientation** | Limits results to one orientation, such as landscape or portrait |
| **Color** swatches | Limits results to photos matching one color. Selecting the active swatch again clears it |
| Sort menu | Orders results by **Most relevant** or **Newest** |

Active filters appear below the controls as removable chips, next to a **Clear all** option. A running count shows how many photos match.

Each result card shows a thumbnail, the photo's title, and the photographer's name with the pixel dimensions. Cards for photos already imported show an **In library** badge and a **View in library** link in place of the **Import** button.

### Importing One Photo At A Time

The **Import** button on a card imports that single photo right away, at full size, with a photographer credit in the caption. This is the quickest route when only one photo is needed. A confirmation message appears with a link to the Media Library.

### Importing Several Photos At Once

Selecting a card, rather than its **Import** button, adds the photo to a selection. A tray opens along the bottom of the screen holding every selected photo. The tray offers these options:

| Option | What It Does |
|---|---|
| Thumbnail row | Shows each selected photo. The × on a thumbnail removes that photo from the selection |
| **Import size** | Chooses the size to download: **Full size (up to 2560px)**, **Large (1024px)**, or **Medium (600px)** |
| **Add photographer credit to caption** | Controls whether a credit line is written into each photo's caption. Selected by default |
| **Edit alt text & captions** | Opens a row of **Title**, **Alt text**, and **Caption** fields for every selected photo |
| **Import** | Starts the import. The label reflects how many photos are selected |

Imports run one photo at a time. A progress bar shows which photo is in flight, and **Cancel** stops the run before the next photo starts. Photos already imported at that point stay in the Media Library.

When the run finishes, a message reports how many photos were imported and links to the Media Library. If some photos could not be imported, those photos stay selected so the import can be tried again.

### Inside The Media Picker

The **Photo Directory** tab appears inside the standard WordPress media picker, which is the window that opens from **Set featured image**, from **Add Media**, and from the **Media Library** button on an Image block. It works in both the classic and block editor, because both use the same picker.

The tab has two panes. The left pane holds a search field, category and orientation menus, a sort menu, and the photo grid. The right pane, headed **Photo details**, reflects the last photo selected.

Selecting a photo in the grid adds it to the selection and fills the details pane with a larger preview, the photographer's name, the pixel dimensions, the file type, and the file size. Below that sit editable **Title**, **Alt text**, and **Caption** fields, plus an **Import size** menu. Edits made here apply to that photo when it is imported.

Two buttons sit at the bottom of the tab:

- **Import only** saves the selected photos to the Media Library and leaves the picker open, with the new photos staged in the picker's own selection.
- The primary button carries whatever label the picker itself uses — **Insert into post**, **Set featured image**, or **Select**. It imports the selected photos and then completes the picker's own action.

In short, an imported photo behaves from that point on exactly like a photo that was already in the library.

### The Classic Editor Pop-Up

The **Photo Directory** button next to **Add Media** opens a pop-up window holding a search field and a grid of photos, each with its own **Import** button. Imported cards show an **Imported** label, a **View in Media Library** link that opens the attachment in a new browser tab, an **Insert into post** button that places the photo at the cursor in the editor, and a **Use as featured image** button that sets the photo as the featured image for the post being edited.

**Insert into post** works the same way as **Add Media**: it uses the site's default image size, alignment and link settings (falling back to the Large size), and adds the photographer credit as a caption when the attachment has one.

This pop-up appears only in the classic editor, and its **Insert into post** and **Use as featured image** buttons work because the window is tied to the editor and post being edited.

### What Lands In The Media Library

Imported photos appear in **Media > Library** alongside regular uploads. Each attachment is filled in as follows:

| Field | What It Holds |
|---|---|
| **Title** | The photo's own title from the Photo Directory, or a fallback when no real title exists |
| **Description** | The photo's descriptive sentence from the Photo Directory |
| **Alternative Text** | The same descriptive sentence, unless the Photo Directory supplies dedicated alt text |
| **Caption** | A photographer credit line, such as "Photo by Jane Smith, via the WordPress Photo Directory." |

The caption is written only when the Photo Directory supplies an author name and crediting is switched on. Any value typed into the **Title**, **Alt text**, or **Caption** fields before importing replaces the value above.

Some photos on the Photo Directory carry only a generic placeholder title, because the original uploader never set a real one. The plugin recognizes those placeholders and builds a title from the photo's descriptive sentence instead, shortened at a word boundary. When there is no usable description either, it falls back to the photo's URL slug, and then to "Untitled photo". A generated title is a best-effort guess, so it is worth a glance before publishing.

If **Settings > Photo Directory** has an image format other than "Keep original format" selected, the photo's file is converted to that format immediately after download, before it's added to the Media Library — so every generated thumbnail size is produced from the converted file, not the original. A quality field (1–100, default 82) controls how that conversion is encoded; it only applies to WebP/AVIF conversion and has no effect when keeping the original format. A conversion failure for any reason falls back to importing the original file untouched.

The plugin also saves hidden values on each attachment so the original photo can be traced later:

| Stored Value | What It Holds |
|---|---|
| `_photofetch_source_id` | The photo's ID on the Photo Directory |
| `_photofetch_source_url` | The photo's page address on wordpress.org/photos |
| `_photofetch_source_author` | The name of the person who uploaded the photo, when the Photo Directory provides it |
| `_photofetch_imported` | A marker showing the attachment came from the Photo Directory |

The plugin uses `_photofetch_source_id` to prevent duplicates. Importing the same photo a second time returns the attachment that already exists instead of downloading a second copy.

Photos on the WordPress Photo Directory are released under CC0, so credit is optional. The stored author name and source address make it straightforward to add credit when a site chooses to.

## Before You Begin

- The site runs WordPress 5.8 or later and PHP 7.4 or later.
- The server can make outbound HTTP requests to wordpress.org. The plugin reads from a public address, so no API key or account is needed.
- The user account has the **Upload Files** capability. Administrators, Editors, and Authors have this capability by default. Accounts without it will not see the photo search.
- The **Photo Directory** button next to **Add Media** appears only on sites that use the classic editor. Sites running the block editor alone will use the media picker tab or the Media page instead.

## Steps To Set Up

1. Copy the `photofetch` folder into the site's `wp-content/plugins/` directory.
2. Sign in to the WordPress admin.
3. Go to **Plugins > Installed Plugins**.
4. Find **PhotoFetch** and select **Activate**.

The plugin has no settings screen. It is ready to use as soon as it is active.

## Steps To Test

### Test The Browse Screen

1. Go to **Media > Photo Directory**. A page headed **Photo Directory** opens, and a grid of recently added photos loads.
2. Type a common search term, such as `flower`, into the search field. Matching photos appear shortly after typing stops.
3. Choose a category, an orientation, and a color swatch. The grid narrows, and a chip appears for each active filter.
4. Select **Clear all**. Every chip is removed and the wider set of results returns.
5. Select **Load more photos** at the bottom of the grid. A further set of photos is added below the first.
6. Select **Import** on any photo. The button changes to **Importing…**, then a confirmation message appears with a link to the Media Library.
7. Confirm the card now shows an **In library** badge and a **View in library** link.
8. Select three photos by clicking the cards themselves. The tray opens at the bottom showing all three.
9. Set **Import size** to **Medium (600px)** and select **Edit alt text & captions**. Change the alt text on one photo.
10. Select the import button in the tray. A progress bar tracks each photo, and a message reports the total when the run finishes.
11. Go to **Media > Library** and confirm all imported photos appear alongside regular uploads. Open the photo edited in step 9 and confirm the alt text matches what was typed.
12. Return to **Media > Photo Directory**, search for a photo already imported, and import it again. The plugin returns the existing attachment rather than adding a duplicate.

### Test The Media Picker Tab

1. Open a post in the block editor.
2. In the sidebar, open the **Featured image** panel and select **Set featured image**. The standard WordPress media picker opens.
3. Select the **Photo Directory** tab at the top of the picker, next to **Upload files** and **Media Library**.
4. Search for a photo and select it in the grid. The **Photo details** pane fills with a preview, the photographer's name, and the photo's dimensions, file type, and file size.
5. Change the **Alt text** field and set **Import size** to **Large (1024px)**.
6. Select **Set featured image** at the bottom of the tab. The photo is imported and set as the featured image, and the picker closes.
7. Publish or update the post, then view it on the front end to confirm the featured image displays.
8. Reopen the picker, select the **Photo Directory** tab, choose a photo, and select **Import only**. The photo is saved to the Media Library and the picker stays open.

### Test The Classic Editor Button

1. Open a post in the classic editor.
2. Select the **Photo Directory** button next to **Add Media**. A pop-up window opens with a search grid.
3. Place the cursor in the post content, import a photo, then select **Insert into post**. The pop-up closes and the photo appears at the cursor, with the credit as its caption.
4. Open the pop-up again, import a different photo, then select **Use as featured image**. The pop-up closes and the featured image is replaced.

After testing, any unwanted photos can be removed from **Media > Library** the same way as any other attachment.

---
---

# Troubleshooting Guide

Internal reference for technical support. Every issue below has been traced to the plugin's own code paths and verified against version 2.0.0.

## Problem: An Imported Photo's Title Does Not Match The Photo Directory

### Cause

This is expected behavior, not a broken install. Some photos on the Photo Directory carry only a generic placeholder title (currently `Photo Detail`, `Untitled`, or `Untitled Photo`) because the original uploader never set a real one. `PhotoFetch_API::normalize_item()` in `includes/class-photofetch-api.php` treats those placeholders as no title at all and works through a fallback chain instead:

1. A title built from the photo's descriptive sentence, cut at a word boundary and capped at 60 characters.
2. A title built from the photo's URL slug, used only when the slug reads like words. Most Photo Directory slugs are short hex strings such as `6836a813f7`, which are skipped.
3. The literal string "Untitled photo".

### Solution

1. This is not a bug to fix — it is a best-effort fallback for photos with no real title upstream. Confirm the description and alt text fields hold a real, specific sentence. If they do, the import worked correctly and only the title is a generated guess.
2. If the generated title reads awkwardly, editors can retitle the attachment by hand, or type a title into the **Title** field before importing.
3. The character cap on description-derived titles is filterable via `photofetch_description_title_length`.
4. The list of recognized placeholder strings is filterable via `photofetch_generic_title_placeholders`, in case the Photo Directory introduces a placeholder this plugin does not yet recognize.

## Problem: The Photo Directory Page, Tab, Or Button Does Not Appear

### Cause

Every entry point is gated on the `upload_files` capability. The **Media > Photo Directory** page, the classic editor button, the block editor assets, and all three AJAX handlers check it. Accounts below Author level, or roles with the capability removed by another plugin, will not see the tool. A second possibility is that the plugin is installed but not active.

### Solution

1. Confirm **PhotoFetch** is listed as active under **Plugins > Installed Plugins**.
2. Confirm the reporting user's role includes **Upload Files**. Administrator, Editor, and Author roles include it by default.
3. If a role editor plugin is in use, check whether `upload_files` has been removed from the affected role.
4. If only the classic editor button is missing, check whether the site actually uses the classic editor. The button prints on the `media_buttons` hook, which the block editor never fires. On a block editor site this button is unreachable by design.

## Problem: The Browse Screen Is Blank Under The Page Heading

### Cause

**Media > Photo Directory** renders an empty container and builds the whole screen in `assets/js/photo-browser.js`. That script is built against `wp-element`, WordPress's own copy of React, and mounts into the `#photofetch-browser` element on `DOMContentLoaded`. If `wp.element` is unavailable, the script exits without rendering and the page stays empty.

### Solution

1. Open the browser console and check for a JavaScript error from another plugin or theme that halted script execution before the browse app ran.
2. Confirm `photo-browser.js` loads on the page and that WordPress's `wp-element` script loads before it.
3. Confirm no optimization plugin is combining, deferring, or minifying admin scripts in a way that breaks the dependency order.

## Problem: The Photo Directory Tab Is Missing From The Media Picker

### Cause

The tab is added by `assets/js/media-modal.js`, which patches `wp.media.view.MediaFrame.Select` and `wp.media.view.MediaFrame.Post`. It is enqueued on `post.php` and `post-new.php`, and again through `enqueue_block_editor_assets`, and it depends on WordPress's own `media-views` script. The file also guards itself: if `wp.media`, `wp.media.view`, or `wp.media.view.MediaFrame` is unavailable when it runs, it exits without doing anything.

### Solution

1. Confirm the screen is a post edit screen. The tab is not added on other admin screens, including **Media > Library**.
2. Open the browser console and look for the warning `PhotoFetch: could not add media modal tab.` That message means `wp.media`'s internals changed shape and the patch was skipped. The plugin fails quietly here on purpose, so the rest of the media picker keeps working.
3. Confirm `media-modal.js` loads on the page, along with the `media-views` script it depends on.
4. If a custom media frame is in use, check whether it extends `MediaFrame.Select` or `MediaFrame.Post`. Frames built from other base classes will not receive the tab.

## Problem: The Category, Orientation, Or Color Filters Are Empty

### Cause

The filter menus are populated from the directory itself, through the `photofetch_terms` AJAX action, which reads the `photo-categories`, `photo-orientations`, and `photo-colors` taxonomies from wordpress.org. `PhotoFetch_API::get_filter_terms()` caches the whole set in the `photofetch_filter_terms` transient for one day. Any taxonomy that fails to load returns an empty list rather than an error, so one unreachable taxonomy empties a single menu instead of breaking the screen.

### Solution

1. Confirm the site can reach `https://wordpress.org/photos/wp-json/wp/v2/photo-categories` from the server.
2. Delete the `photofetch_filter_terms` transient to force a fresh fetch, rather than waiting out the one-day cache.
3. Open the browser network tab and inspect the `admin-ajax.php` request with `action=photofetch_terms`. An empty list for one taxonomy points at that upstream endpoint.
4. Note that the color swatches appear only on the browse screen. The media picker tab offers category, orientation, and sort, and does not include a color filter.

## Problem: The Search Grid Reports That WordPress.org Could Not Be Reached

### Cause

This is the generic error state used when the AJAX request fails, when the server returns a non-200 response, or when the browser request itself is rejected. The most common root causes are a blocked outbound request, an expired security token, or a network timeout. The plugin allows 15 seconds for a search request.

### Solution

1. Select **Try again** in the error notice. A stale `photofetch_nonce` returns a failure that clears on reload, so reloading the admin page also resolves it.
2. Open the browser network tab and inspect the `admin-ajax.php` request with `action=photofetch_search`. The response body carries the real message.
3. A response of `-1` with HTTP 403 is a rejected security token. A JSON body with a permission message is a capability problem; see the section above.
4. If the response reports an HTTP status from the Photo Directory, the upstream API returned an error. Confirm the site can reach `https://wordpress.org/photos/wp-json/wp/v2/photos` from the server, not just from the browser.
5. Check whether the site defines `WP_HTTP_BLOCK_EXTERNAL` in `wp-config.php`. If it does, add `wordpress.org` to `WP_ACCESSIBLE_HOSTS`.
6. Check for a firewall, proxy, or security plugin that blocks outbound requests from PHP.

## Problem: A Search Returns No Photos For A Term That Should Match

### Cause

The plugin passes the search term and any active filters straight to the upstream API and reports whatever comes back. It also caches each search response in a transient for five minutes, keyed on the search term, page number, results per page, and the active filters. A search run during a brief upstream outage can return an empty result that is then served from the cache for up to five minutes.

### Solution

1. Wait five minutes and repeat the search, or clear the site's transients to expire the cached result immediately.
2. Remove the active filters using **Clear all**. A category, orientation, and color applied together narrows results sharply.
3. Compare the result against a direct browser request to `https://wordpress.org/photos/wp-json/wp/v2/photos?search=TERM`. A matching empty result confirms the upstream library has no photos for that term.
4. Note that the plugin searches only the Photo Directory's own text fields. Categories, orientations, and colors are applied as filters rather than as search terms.

## Problem: Sorting By Most Relevant Has No Effect While Browsing

### Cause

This is expected behavior. The upstream API rejects a relevance ordering when no search term is supplied, returning HTTP 400. `PhotoFetch_API::normalize_filters()` therefore degrades relevance to a date ordering whenever the search box is empty, which keeps the browse screen working instead of erroring.

### Solution

1. Enter a search term. Relevance ordering applies from that point on.
2. Note that the Photo Directory exposes no popularity signal of any kind — no view, download, or favorite count — so relevance and date are the only orderings available.

## Problem: Thumbnails Are Blank Or Imports Report That No Downloadable Image Was Found

### Cause

The Photo Directory REST API is public but not formally documented, so its response shape can change. `PhotoFetch_API::normalize_item()` in `includes/class-photofetch-api.php` checks four possible locations for image size data, in order:

1. `media_details.sizes` on the item itself
2. `media_details.sizes` on the embedded `wp:featuredmedia` object, then that object's bare `source_url`
3. A top-level `sizes` field on the item
4. A bare `source_url` on the item

When none of these produce a URL, the photo has no thumbnail in the grid, and an import attempt returns the "no downloadable image" error.

### Solution

1. Request a live response from `https://wordpress.org/photos/wp-json/wp/v2/photos?_embed=1` and inspect the JSON structure.
2. Compare that structure against the four checks in `normalize_item()`.
3. If the API has moved its image data to a new location, add a matching check to `normalize_item()`. This is the single function that maps upstream JSON into the `sizes`, `alt`, and `author` fields the rest of the plugin uses.
4. If only some photos are affected, the issue is limited to those upstream records rather than the API shape. Import a different photo to confirm.

## Problem: An Import Fails With "Source URL Is Not On A Trusted Host"

### Cause

`PhotoFetch_Importer::is_allowed_image_host()` requires an image's URL to be on `wordpress.org`, `wp.com`, or `w.org` (or a subdomain of any of them) before it's downloaded — a deliberate security check, not a bug. The Photo Directory's images are actually served from `pd.w.org`, WordPress.org's own short domain (the same family as `s.w.org`). The check only fires if a photo's image URL resolves to some other host entirely.

### Solution

1. The error message includes the actual rejected hostname — no need to inspect network requests to find it.
2. If that host is a legitimate part of the Photo Directory's infrastructure, add it via the `photofetch_allowed_image_hosts` filter:
   ```php
   add_filter( 'photofetch_allowed_image_hosts', function ( $hosts ) {
       $hosts[] = 'the-new-host.example';
       return $hosts;
   } );
   ```
3. Don't add a host you don't recognize or can't verify belongs to wordpress.org's own infrastructure — this check exists specifically to prevent a compromised or malicious API response from causing an arbitrary download.

## Problem: Settings > Photo Directory Shows No Format Picker

### Cause

This is expected on a server whose image editor (GD or Imagick, whichever core registered) can't produce WebP — checked live via `wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) )`, not assumed from PHP version. `PhotoFetch_Settings::supported_formats()` deliberately gates on WebP specifically: if it isn't available, no picker is shown at all (not even for AVIF alone), and imported photos keep their original format, same as before this feature existed.

### Solution

1. Confirm the page says WebP conversion isn't available, rather than showing an empty or broken form.
2. This usually means an older GD build without WebP support compiled in, or an Imagick build linked against an ImageMagick without WebP delegate support. Check `phpinfo()` for GD's "WebP Support" line, or `Imagick::queryFormats('WEBP')` for Imagick.
3. Upgrading the server's image library (or switching which one WordPress uses, if both are installed) is an infrastructure change outside the plugin's control — there's no setting in the plugin itself to force WebP support that isn't really there.

## Problem: An Import Starts But Never Completes

### Cause

The plugin allows 20 seconds to fetch a photo's details and 30 seconds to download the file. Slow connections, low PHP memory limits, or a low `max_execution_time` can cut the process short. A failed download leaves no attachment behind, since the plugin removes the temporary file when sideloading fails.

### Solution

1. Check the server error log for a PHP fatal error, memory exhaustion, or a timeout during the import attempt.
2. Confirm `wp-content/uploads` is writable by the web server user.
3. Raise `memory_limit` and `max_execution_time` if the log points to either limit.
4. Set **Import size** to **Medium (600px)** and retry, to confirm whether file size is the deciding factor. The per-card **Import** button always requests full size, so use the selection tray to control the size.

## Problem: A Bulk Import Stops Part Way Through

### Cause

Bulk imports run one request at a time rather than in parallel, so a single slow or failed download holds up the rest of the queue. Selecting **Cancel** stops the queue before the next photo starts. Photos already imported stay in the Media Library, and photos that failed or never started remain selected.

### Solution

1. Check which photos still carry an **In library** badge. Those completed successfully.
2. Select the import button in the tray again to retry whatever is still selected.
3. If the same photo fails repeatedly, import it on its own to surface the specific error, then work through the import timeout steps above.

## Problem: The Imported File Is Smaller Than The Original Photo

### Cause

Two things can reduce the stored size. The first is the **Import size** setting, which downloads the large or medium rendition when either is chosen. The second is standard WordPress behavior: even at full size, WordPress applies its own large-image threshold, which is 2560 pixels on the longest side by default, and stores a scaled copy as the main attachment file. In a verified import, a 2873 by 2154 original was stored as 2560 by 1919.

### Solution

1. Confirm **Import size** was set to **Full size (up to 2560px)** for the import in question.
2. Confirm the attachment metadata includes an `original_image` value. When it does, the untouched original is on disk next to the scaled copy, and nothing was lost.
3. To keep full-resolution files as the main attachment, return `false` from the `big_image_size_threshold` filter. Note this affects every upload on the site, not only Photo Directory imports.

## Problem: A Photo Imports Twice Instead Of Reusing The Existing Attachment

### Cause

Duplicate protection relies on a lookup against the `_photofetch_source_id` attachment meta key. If that meta value was removed, changed, or lost during a migration, the plugin treats the photo as new and downloads it again.

### Solution

1. Query the `postmeta` table for `_photofetch_source_id` and confirm the value matches the upstream photo ID.
2. If the meta is missing on a previously imported attachment, re-adding `_photofetch_source_id` with the correct photo ID restores duplicate detection for that photo.
3. Remove any extra copies from **Media > Library**.

## Problem: A Caption Is Empty Or Holds No Photographer Credit

### Cause

The credit line is written only when the Photo Directory supplies an author name for that photo. Three other cases produce the same result: **Add photographer credit to caption** was cleared before the import, a caption was typed into the details fields and replaced the credit, or the import ran from the per-card **Import** button, which always applies the default credit setting rather than the tray's.

### Solution

1. Confirm the photo has an author name on its page at wordpress.org/photos. Photos with no author name receive no credit line.
2. Check the `_photofetch_source_author` meta on the attachment. When it holds a name, the caption can be filled in by hand.
3. Confirm **Add photographer credit to caption** is selected in the tray before running the import.
4. The wording of the credit line is filterable via `photofetch_credit_line`.

## Problem: The "Use As Featured Image" Button Is Missing

### Cause

That button appears only in the classic editor's pop-up window, and only because that window is tied to the post being edited. Three cases explain every report:

- On the **Media > Photo Directory** browse screen, the button is correctly absent, because that page is not tied to a post.
- Inside the media picker tab, there is no such button at all. The tab's own primary button borrows the picker's label — **Insert into post**, **Set featured image**, or **Select** — and does the job instead.
- In the classic editor's pop-up window, opened from the button next to **Add Media**, the button should be present.

### Solution

1. Confirm which entry point the user opened, and compare it against the three cases above.
2. If the button is missing from the classic editor's pop-up window, check the browser console — the action depends on `wp.media.featuredImage` being available.

## Known Documentation Gaps In The Plugin Package

These affect the plugin's own bundled files, not site behavior. They are worth correcting before any public release.

| File | Issue |
|---|---|
| `photofetch.php` | The **Plugin URI** and **Author URI** headers still contain the placeholder `your-username`. |
| `readme.txt` | The **Contributors** list omits `michelleames`, who is named in the plugin header's **Author** field. |
| `readme.txt` | The developer notes describe the title fallback as slug-derived and state that the browse grid does not display titles. Both describe earlier behavior. |
| `assets/js/block-editor.js` | The file is never registered or enqueued, so the block editor sidebar panel it defines is unreachable. |

## Additional Resources

- PhotoFetch User Guide
- PhotoFetch Feature Overview
- WordPress Photo Directory: `https://wordpress.org/photos`
- Upstream API endpoint: `https://wordpress.org/photos/wp-json/wp/v2/photos`
- Key files: `includes/class-photofetch-api.php`, `includes/class-photofetch-importer.php`, `includes/class-photofetch-plugin.php`, `assets/js/photo-browser.js`, `assets/js/media-modal.js`

---
---

# Feature Overview

## Overview

The PhotoFetch brings the WordPress Photo Directory into the WordPress admin. The Photo Directory is a community photo library hosted at wordpress.org/photos, and every photo in it is released under the CC0 license. CC0 photos can be used for any purpose, including commercial work, with no license fee and no required credit.

The plugin lets site editors search that library and add photos to their own site without visiting an external site, downloading a file, and uploading it again. Imported photos become standard Media Library items and behave like any other uploaded image.

The plugin is free, requires no account, and requires no API key.

## Target User

The plugin fits teams that publish regularly and need supporting imagery without a stock photo budget or a licensing review step. That includes:

- **Content and editorial teams** who add images to posts on a weekly or daily rhythm and want a source that is cleared for use in advance.
- **Small businesses and solo site owners** who maintain their own sites and do not have a photographer or a paid image subscription.
- **Agencies and site builders** who need placeholder or supporting imagery during a build, with no risk of a licensing problem carrying into launch.
- **Nonprofits, schools, and community organizations** working with limited budgets.
- **WordPress community sites** that prefer to source material from the WordPress project's own ecosystem.

The plugin is a strong fit for sites where the barrier to good imagery is process and licensing rather than design skill. It fits less well for sites that need highly specific subject matter, since the Photo Directory is a community library rather than a commercial catalog.

## How It Works

The clearest way to see the feature is through the standard WordPress image picker. When someone sets a featured image or adds an image to a page, the familiar picker window opens with its usual **Upload files** and **Media Library** tabs. This plugin adds a third tab: **Photo Directory**.

Selecting that tab turns the picker into a photo search. A grid of photos fills the left side, and a details pane on the right shows a larger preview of whatever is selected, along with the photographer's name, the photo's dimensions, and its file size. Editable title, alt text, and caption fields sit in that pane, so the wording that lands in the Media Library can be set before anything is downloaded. Finishing the job uses the same button the picker always had. The photo is now an ordinary library item, so it behaves from that point on exactly like a photo that had been uploaded weeks ago.

For browsing rather than picking, a dedicated page under the **Media** menu shows a fuller version of the same search. Photos can be narrowed by category, orientation, and color, and sorted by relevance or by date. Selecting several photos gathers them into a tray along the bottom of the screen, where one action imports the whole set, with a progress bar tracking each file. A third entry point sits next to **Add Media** in the classic editor, for people who prefer a dedicated button over the picker tab.

Every import records where the photo came from and who uploaded it, so credit can be added when a site chooses to give it. Importing the same photo twice reuses the copy already in the library, which keeps things tidy.

## Key Benefits

- **A free, pre-cleared image source.** Every photo on the WordPress Photo Directory is CC0, so it can be used commercially with no license fee and no required attribution.
- **It appears inside the picker people already use.** The **Photo Directory** tab sits in the standard WordPress image picker, so finding a photo happens in the same window and the same moment as choosing one. There is no separate trip to a search page and back.
- **Three entry points that match how people work.** The media picker tab (available to both the classic and block editor), a standalone Media page for browsing, and a dedicated button in the classic editor toolbar.
- **Filtered browsing.** Photos can be narrowed by category, orientation, and color, and ordered by relevance or by date, which shortens the path to a photo that fits a specific layout.
- **Bulk import.** Several photos can be selected and imported in one action, with a progress indicator for the run, so gathering imagery for a whole article is a single step.
- **Editable details before import.** Title, alt text, and caption can be set for each photo ahead of time, so files arrive named and described the way the site needs them.
- **Selectable file size.** Photos can be imported at full, large, or medium size, which keeps page weight and storage under control on image-heavy sites.
- **A workflow contained inside WordPress.** Search, preview, and import happen in the admin, replacing the visit-download-upload cycle that an external stock site requires.
- **Real Media Library attachments.** Imported photos are ordinary attachments, so they work with existing themes, page builders, image optimization plugins, and CDNs with no special handling.
- **Photo descriptions carried over.** Each photo's descriptive sentence is saved as both the attachment's description and its alt text, giving screen reader users real coverage on arrival rather than requiring manual cleanup first.
- **Duplicate protection.** Repeat imports of the same photo reuse the existing attachment instead of adding another copy, and photos already in the library are marked as such while browsing.
- **Source tracking on every import.** The original photo address and the uploader's name are stored with the attachment, and a credit line is added to the caption automatically when an author name is available.
- **No account, no key, no setup screen.** The plugin works as soon as it is activated.
- **Complements existing media tools.** The plugin adds a source of images. It does not replace the Media Library, image optimization plugins, or a digital asset manager, and it runs alongside them.
- **Audience reach.** WordPress powers a large share of the web, and the Photo Directory is maintained by the WordPress community itself, which makes it a natural fit for sites already invested in that ecosystem.

### Note For Planning

Photo titles occasionally arrive as a generated fallback rather than a hand-written title, for photos the original uploader never titled — worth a glance before publishing, and editable in the import panel before the file is saved. Descriptions and alt text arrive populated and usable on import.

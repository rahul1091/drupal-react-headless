# Content Translate

Batch-translate Drupal 10/11 **node** and **custom block** content into
other languages configured on your site, using an external translation
API. Ships with **LibreTranslate** (free / self-hostable) support, and
is built so additional providers (Google Cloud Translation, DeepL,
Azure Translator, etc.) can be added later as plugins with no changes
to the admin UI or batch logic.

## Requirements

- Drupal 10 or 11
- Core modules: `content_translation`, `language`, `locale` (declared
  as dependencies, will be enabled automatically)
- At least one additional language configured at
  `/admin/config/regional/language`
- Content types / custom block types you want to translate must have
  translation enabled at `/admin/config/regional/content-language`

## Installation

1. Copy this `content_translate` directory into `modules/custom/`.
2. Enable it: `drush en content_translate -y` (or via Extend UI).
3. Go to **Configuration > Regional and language > Content Translate**
   (`/admin/config/regional/content-translate`) and confirm/enter your
   LibreTranslate API URL and API key (the public instance at
   `https://libretranslate.com/translate` requires an API key for most
   usage; a self-hosted instance typically does not).
4. Go to the **Translate content** tab
   (`/admin/config/regional/content-translate/translate`) to run a
   batch job:
   - Choose **Content (nodes)** or **Custom blocks**.
   - Choose one or more content types / block types.
   - Choose the **source language** (defaults to the site's default
     language) and one or more **target languages** — both are
     populated from the languages configured on your site.
   - Optionally skip items that already have a translation, and
     restrict nodes to published only.
   - Click **Run batch translation**.

## What gets translated

Only text-bearing fields are sent to the translation API:

- Plain text fields (`string`, `string_long`) — e.g. node title
- Formatted text fields (`text`, `text_long`, `text_with_summary`) —
  e.g. body, with HTML markup preserved via LibreTranslate's `html`
  format mode

The following are **never** sent for translation: images, files,
entity reference fields (including taxonomy term references), the
block's admin **label** (`info`), and other structural/machine fields
(`uuid`, `nid`, `vid`, `uid`, `type`, `langcode`, `status`, `created`,
`changed`, etc).

## Permissions

- **Administer content translate** — configure the API provider
- **Translate content via content translate** — run batch translation
  jobs

## Translation providers included

- **LibreTranslate** — free / self-hostable, open source
- **Google Cloud Translation** (v2, API key auth) — free monthly quota, then billed
- **DeepL** (Free or Pro API) — free tier has a monthly character quota
- **Azure AI Translator** — free (F0) tier available

Pick the active provider and enter its credentials under **Configuration
> Regional and language > Content Translate**
(`/admin/config/regional/content-translate`). Only the active
provider's fields are billed/used; the others' settings are simply
stored until you switch to them. Always check each vendor's current
pricing/quota terms before relying on them, as these change over time.

## Adding another translation provider later

1. Create `src/Plugin/ContentTranslateTranslator/YourProvider.php`
   implementing `Drupal\content_translate\TranslatorInterface`
   (easiest: extend `TranslatorPluginBase`), annotated with
   `@ContentTranslateTranslator`.
2. Implement `translate()` to call the new API, and
   `buildConfigurationForm()` to expose its settings (API key, region,
   etc).
3. Clear caches. The new provider automatically appears in the
   **Translation API provider** dropdown on the settings page — no
   other code changes required.

## Notes / limitations

- Translation calls happen synchronously during the batch, one field
  value at a time. For very large content sets on a free/rate-limited
  API tier, expect the batch to take a while — this is expected and
  the batch API will show progress and can be safely left running.
- Failures for an individual field/entity are logged to
  `/admin/reports/dblog` and reported in the batch summary; the batch
  continues rather than aborting.
- This module translates **content values**, not field labels, menus,
  views, or configuration strings — use core's Interface Translation
  (`locale`) for UI strings.

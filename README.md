# Afterburner Meetings

Team-scoped meetings (AGM, council, special) for Afterburner Jetstream applications. Meetings orchestrate schedules, attendance, document attachments, and optional ballot links — vote mechanics remain in `laravel-afterburner/voting`.

## Installation

```bash
composer require laravel-afterburner/meetings
php artisan afterburner:meetings:install
```

### Tailwind CSS (required for production builds)

The calendar and other UI rely on Tailwind classes defined in this package’s Blade views (including arbitrary values like `min-h-[9rem]` and `text-[11px]`). Your app’s production CSS build only includes classes Tailwind finds in its `content` paths.

Add the package paths to `tailwind.config.js` in the host application (adjust if you use a different vendor layout):

```js
content: [
    // ...existing paths...
    './vendor/laravel-afterburner/meetings/resources/views/**/*.blade.php',
    './vendor/laravel-afterburner/meetings/src/**/*.php',
    './resources/views/vendor/afterburner-meetings/**/*.blade.php',
],
```

Then rebuild frontend assets (`npm run build`) and redeploy. Without this, the calendar may look correct in local `npm run dev` (compiled views under `storage/framework/views` can be scanned) but break on production where assets are built before those files exist.

If you use several Afterburner packages, you can scan all of them at once:

```js
'./vendor/laravel-afterburner/*/resources/views/**/*.blade.php',
'./vendor/laravel-afterburner/*/src/**/*.php',
'./resources/views/vendor/afterburner-*/**/*.blade.php',
```

Add the `HasMeetings` trait to your `App\Models\Team` model:

```php
use Afterburner\Meetings\Concerns\HasMeetings;

class Team extends JetstreamTeam
{
    use HasMeetings;
}
```

## Permissions

Uses the existing `manage_meetings` slug from Afterburner role templates. The install seeder ensures the permission exists and assigns it to team owner roles.

## Optional integrations

- **Documents:** attach agenda materials via `LinkDocument` (same pattern as ballot document links).
- **Voting:** link existing ballots to meetings via a pivot table; listens to `BallotPublished` / `BallotClosed` for meeting context only.

## Strata apps

Implement attendance against property/lot voter units in the host app if needed. The package stores attendance keyed to a morph `voter_unit` without built-in Property models.

## Out of scope

- Ballot casting, proxy votes, quorum, and tally (owned by the voting package).
- BC-specific legal notice generation (defer to host app).

## License

MIT License — see [LICENSE](LICENSE) for details.

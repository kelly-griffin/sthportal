
---

# `docs/site-map.md`

# UHA Portal — Site Map (Human Map)

## FRONT-END ##

## Root

- `box.php` - 
- `player-stats.php` -
- `ProBoxScore.php` -
- `ProGameLog.php` -
- `recap.php` -
- `team-stats.php` -


## Top Bar (`includes/topbar.php`)
- Leagues (`leagues.php`) - list of leagues on the portal ~ dropdown links to `leagues/` pages
    - Pro League (NHL) (`missing`) - **build** `leagues/teams.php` page [ ]
    - Farm League (AHL) (`missing`) - see above
    - Development League (ECHL) (`missing`) - **build** `leagues/dev-teams.php` page [ ]
    - International (Multiple) (`missing`) - **build** `leagues/int-teams.php` page [ ]
    - Junior Leagues (Multiple) (`missing`) - **build** `leagues/jr-teams.php` page [ ]
- Players (`players.php`) - Player Spotlight ~ dropdown links to `players/` pages
    - All Players (`missing`) - **build** `players/players.php` page [ ]
    - Free Agents (`missing`) - **build** `players/free-agents.php` page [ ]
    - Waiver Wire (`missing`) - **build** `players/waivers.php` page [ ]
    - Prospect List (`missing`) - **build** `players/prospects.php` page [ ]
    - Compare Players (`missing`) - **build** `players/compare-players.php` page [ ]
- Front Office (`front-office.php`) - general manager's office ~ dropdown links to `office/` pages
    - Team Dashboard (`missing`) - **build** `office/dashboard.php` page [ ]
    - Roster Management (`missing`) - **build** `office/roster.php` page [ ]
    - Lines and Strategy (`missing`) - **build** `office/lines-strats.php` page [ ]
    - Depth Charts (`missing`) - **build** `office/depth-charts.php` page [ ]
    - Personnel/Staff (`missing`) - **build** `office/personnel.php` page [ ]
    - Financial Management (`missing`) - **build** `office/finances.php` page [ ]
    - Scouting Assignments (`missing`) - **build** `office/scouting.php` page [ ]
    - Cap Management Tools (`missing`) - **build** `office/salary-cap.php` page [ ]
    - Upload Lines (`missing`) - **build** `office/lines-upload.php` page [ ]
- Tournaments (`tournaments.php`) - list of tournaments in the UHA ~ dropdown links to `tournaments/` pages
    - World Cup of Hockey (`missing`) - **build** `tournaments/WCoH.php` page [ ]
    - 2026 Olympics (`missing`) - **build** `tournaments/olympics.php` page [ ]
    - IIHF World Juniors (`missing`) - **build** `tournaments/world-juniors.php` page [ ]
- Media (`media-hub.php`) ~ dropdown links to `media/` pages
    - Media Hub (`media-hub.php`) - hub with quick links to important media 
    - News (`media/news.php`) - news index for users
    - Press Releases (`media/press-releases.php`) - articles from the UHA / leagues
    - Weekly Recaps (`media/weekly-recaps.php`) - a weekly update on the league's results for the previous week
    - Power Rankings (`media/power-rankings.php`) - a weekly/monthly ranking of teams (not standings) and the change from previous week/month
    - Player of the Week (`media/potw.php`) - a weekly pick for the best forward, defenseman and goaltender for that week
    - Team of the Week (`media/totw.php`) - a weekly pick for the best team in the league for that week
    - Social Hub (`media/social.php`) - hub with links to social features such as chat and dm
    - Chat (`media/chat.php`) - built in chat room with channels 
    - Direct Messaging (`media/messages.php`) - built in direct messaging
- Options (`options-hub.php`) ~ dropdown links to `options/` pages
    - Download Latest League File (`download.php`) - for managers to download the latest file for their client
    - Options Hub (`options-hub.php`) - hub with quick links to important options/settings
    - Appearance (`options/appearance.php`) - users can change their avatar and theme settings
    - Defaults (`options/defaults.php`) - users can set their default settings for the portal
    - Notifications (`options/notifications.php`) - users can set/change their notification settings 
    - Privacy & Data (`options/privacy.php`) - users can set/change privacy and data collection settings
    - Profile & Account (`options/profile.php`) - users can set/change their profile settings like display name, email, password, etc.
    - GM Settings (`options/gm-settings.php`) - users can change settings related to gm features
    - About Us (`options/about.php`) - a standard about us page (version, credits, notes, support)
- Admin (`admin/`) ~ dropdown links to `admin/` pages
    - Upload League File (`admin/assets-hub.php?do=upload-league`) - commissioner can quickly upload latest league file here
    - GM Management (`missing`) - **build** `admin/gm-management.php` page [ ]
    - Trade Approvals (`missing`) - **build** `admin/trade-approvals.php` page [ ]
    - League Settings & Toggles (`missing`) - **build** `admin/settings.php` page [ ]
    - Pipeline Quickstart (`admin/pipeline-quickstart.php`) - quick access to tools to update/fix the portal
    - Data Pipeline Hub (`admin/data-pipeline.php`) - tasks/tools to update/patch parts of the portal after simulations 
        - Update Schedule (`tools/build-schedule-json.php`) - 
        - Build Schedule (`tools/build-schedule-full.php`) -
        - Enrich Schedule (`tools/enrich-schedule.php`) -
        - Reconcile Schedule (`tools/reconcile-schedule-from-boxscores.php`) -
        - Audit Schedule Links (`tools/audit-schedule-links.php`) -
        - Fix Schedule Links (`tools/fix-schedule-links.php`) -
        - Patch Boxscores Teams (`tools/patch-boxscores-teams-from-schedule.php`) -
        - Build Boxscores JSON (`tools/build-boxscores-json.php`) -
        - Rebuild Box JSON (`tools/rebuild-boxjson-from-html.php`) -
        - Build PBP Stats (`tools/build-pbp-stats.php`) -
        - STHS Importer (`admin/sths-importer.php`) -
        - Generate Recaps (`tools/generate-recaps.php`) -
        - Build Home JSON (`tools/build-home-json.php`) -
        - Build Ticker JSON (`tools/build-ticker-json.php`) -
        - Headshot Cache (`tools/cache_headshots.php`) -
        - Fetch Headshots (`tools/fetch_headshots_bulk.php`) -
        - Build Team Map (`tools/build-team-map.php`) -
    - News Manager (`admin/news.php`) - hub for maintaining all news articles
        - New (`admin/news-new.php`) - create a new article
        - Auto Recap (`admin/news-auto-recap.php`) - use the generator to create a recap article for the gm
    - Devlog (`admin/devlog.php`) - a log for keeping development notes, pairs with `DEVLOG.md`
    - Assets Hub (`admin/assets-hub.php`) - hub for uploading other portal assets
    - Users / Roles **(Admin Dashboard - Users / Roles)**
    - Account Locks **(Admin Dashboard - Account Locks)**
    - Login Attempts **(Admin Dashboard - Login Attempts)**
    - System Hub (`admin/system-hub.php`) - hub for all tools for maintenance, audits/logs, backup, etc.
        - Audit Log **(Admin Dashboard - Audit Log)**
        - Audit Export (`admin/audit-export.php`) - export the audit log to csv file
        - Backup Now (`admin/backup-now.php`) - backup db and files
        - Schema Check (`admin/schema-check.php`) - verifies tables
        - Maintenance (`admin/maintenance.php`) - turns on admin maintenance mode
        - System Health (`tools/health.php`) -
*** Login/Logout ***
- `login.php`
    - `forgot-password.php`
        - `reset-password.php`
- `logout.php`

## League Bar (Pro/Farm) (`includes/leaguebar.php`)
- Home (`home.php`)
  - Rails: Scores, Transactions, Injuries, News, etc. (payload partials + JS renderers)
    - Leaders (`home-leaders.php`)
    - Scores (`home-scores.php`)
    - Standings (`home-standings.php`)
    - News (`home-news.php`)
        - News Articles (`news-article.php?id=…` or `?slug=…`)
    - Transactions (`home-transactions.php`)
    - Injuries (`home-injuries.php`)
- Teams (`team.php?id=…`)
- Standings (`standings.php`)
- Schedule (`schedule.php`)
- Statistics (`statistics.php`)
- Transactions (`transactions.php`)
- Injuries (`injuries.php`)
- Playoffs (`playoffs.php`)
- Entry Drafts (`entry-drafts.php`)


## BACK-END ##

## Admin Dashboard - Header (`admin/admin-header.php`)
- Admin Home (`admin/index.php`) - admin dashboard home
- Users (`admin/users.php`) - manage users in the league
    - Roles (`admin/roles.php`) - manage roles of users on the site (admin/commissioner/user)
    - Export Users (`admin/users-export.php`) - exports users to a csv file
    - Edit User (`admin/user-edit.php`) - edit users information
    - Delete User (`admin/user-delete.php`) - delete a user
    - User Teams (`admin/user-teams.php`) - add/view/change which team a user is connected to
    - Lock User (`admin/user-lock.php`) - locks a user's account
    - Unlock User (`admin/user-unlock.php`) - unlocks a user's account
    - Activate/Deactivate User (`admin/user-toggle-active.php`) -activate/deactivate a user's account 
- Licenses (`admin/licenses.php`) - for keeping track of licenses (becomes a page for your license key for other portals)
    - New/Edit License (`license-edit.php`) - for creating/editing license keys
    - License Actions (`license-action.php`) - Extend 6/12/24 Months or Block a license key
    - Export CSV (`admin/licenses.php?export=csv`) - export all licenses to csv file
    - Activate License (`admin/license-activate.php`) -
    - Extend License (`admin/license-extend.php` -) -
    - Issue License (`admin/license-issue.php`) -
    - Reactivate License (`admin/license-reactivate.php`) -
    - Revoke License (`admin/license-revoke.php`) -
- Audit Log (`admin/audit-log.php`) - keeps tracks of all events that happen (user login, password resets, devlog entries, etc.)
    - Export CSV (`admin/audit-log.php?export=csv`) - export events to a csv file
- Login Attempts (`admin/login-attempts.php`) - keeps track of all login attempts on the site
    - Export CSV (`admin/login-attempts.php?export=csv`) - export login attempts to a csv file
- Radar (`admin/alt-radar.php`) - tracks potential alternate accounts made by users
    - Watchlist (`admin/alt-radar.php#watchlist`) - a watch list of potential alternate accounts
    - Ignores (`admin/alt-radar.php#ignores`) - a list of IPs you have added as not being alternate accounts (i.e. same household)
    - Digest (`admin/alt-radar-digest.php`) - a quick daily overview of potential IPs/accounts that are alt accounts
- Temp Bans (`admin/temp-bans.php`) - for adding/viewing/removing temporary IP bans
- Account Locks (`admin/account-locks.php`) - for adding/viewing/removing account locks
    - Export CSV (`admin/account-locks.php?export=csv`) - export locked accounts to csv file
- Devlog (`admin/devlog.php`) - for adding/viewing/removing devlog entries
    - Export CSV (`admin/devlog?export=csv`) - export devlog to csv file
    - New/Edit Entry (`admin/devlog-edit.php`) - create/edit a devlog entry
- Admin 2FA (`admin/admin-2fa-setup.php`) - for setting up Two-Factor Authentication for admins
- 2FA Backup Codes (`admin/admin-2fa-codes.php`) - for (re-)generating ten one-time-use backup codes for 2FA
- Back to Site **(Splash Page)**
- Logout (`admin/logout.php`) - logout of admin dashboard takes you to login 
    - Login (`admin/login.php`) - login to admin dashboard (needs link to `home.php`)
- Help **(Keyboard Shortcuts)** - brings up a list of keyboard shortcuts for admin pages

## Admin
- `admin/_admin_bootstrap.php` - bootstrap for admin pages only
- `admin/_whats_new_badge.php` - what's new badge for admin dashboard
- `admin/admin-2fa-verify.php` - verification of 2fa codes
- `admin/audit-links-wrapper.php` -
- `admin/user-security.php` -

## API
- `api/news.php` — `{ ok, items: [{id,title,team,image,published_at,link}] }`
- (future) `api/scores.php`, `api/injuries.php`, etc.

## ACL
- `acl/set-active-team.php` -sets an user's active team

## Auth
- `auth/status.php` -

## Chat
- `chat/poll.php` -
- `chat/post.php` -

## DM
- `dm/poll.php` -
- `dm/post.php` -

## Assets
- `assets/avatar.php` - 
*** `assets/css/` ***
- `assets/css/game.css` -
- `assets/css/global.css` - Site wide CSS, loaded by '`includes/head-assets.php`'
- `assets/css/legacy-shim.css` - being used while site-wide changes are being made to avoid broken css
- `assets/css/nav.css` - navigation css for topbar/leaguebar
- `assets/css/tokens.css` - colours for different theme settings
- `assets/css/*.css` - rest of css are deprecated and set for deletion
*** `assets/img/` ***
- `assets/img/broadcasters/` - images for broadcasters on schedule page
- `assets/img/logos/` - images for team logos
- `assets/img/mugs/` - images for mugshots of players in the NHL (need images scraped from NHL.com)
*** `assets/js/` *** 
- `assets/js/auto-logos.js` - script that automatically selects dark/light images based on the current background they're on
- `assets/js/boxscore.js` - scripts for boxscores
- `assets/js/dark-swap.js` - theme related?
- `assets/js/feeds.js` - scripts for news feeds
- `assets/js/front-office.js` - scripts for front office page
- `assets/js/gamelog.js` - scripts for game logs
- `assets/js/goals.js` -
- `assets/js/home.js` - scripts for home page
- `assets/js/injuries.js` - scripts for injuries page / injuries home insert
- `assets/js/leagues.js` - scripts for leagues list and pages
- `assets/js/nav.js` - scripts for the topbar and leaguebar
- `assets/js/players.js` - scripts for players page and pages
- `assets/js/schedule.js` - scripts for the schedule page
- `assets/js/scores.js` - scripts for the scores page(s) and scores home insert
- `assets/js/standings-page.js` - scripts for the standings page
- `assets/js/standings.js` - scripts for the home standings insert
- `assets/js/statistics.js` - scripts for leaders/statistics page/statistics home insert
- `assets/js/tournaments.js` - scripts for the list of tournaments and pages
- `assets/js/transactions.js` - scripts for the transactions page and transactions home insert
- `assets/js/urls.js` - 
*** `assets/json/` ***
- `assets/json/affiliates.json` - information for AHL affiliates
- `assets/json/pbp-faceoffs-events.json` - tracks faceoffs from pbp
- `assets/json/pbp-faceoffs-players.json` - tracks players faceoff numbers from pbp
- `assets/json/pbp-faceoffs-teams.json` - tracks team faceoff numbers from pbp
- `assets/json/pbp-index.json` - takes all the boxscores and outputs them through the other pbp jsons
- `assets/json/pipeline-status.json` -
- `assets/json/move_dates.json` - contains dates for roster moves
- `assets/json/signing_dates.json` - contains dates for signings
- `assets/json/trade_dates.json` - contains dates for trades
- `assets/json//broadcasters-overrides.json` - MOVE TO `assets/json/` ASAP
- `assets/json//broadcasters-rules.json` - MOVE TO `assets/json` ASAP
- `assets/json//draft-picks-per-round.json`
- `assets/json//home-data.json`
- `assets/json//results-overrides.json`
- `assets/json/schedule-current.json`
- `assets/json/schedule-full.json`
- `assets/json/team-map.json`
- `assets/json/teams.json`
- `assets/json/ticker-current.json`
- `assets/json/time-map.json`
- `assets/json/team_colors.json` - team colours for NHL
*** `assets/splash/` ***
- `assets/splash/buttons/` - three svg images for splash page buttons
- `assets/splash/tiles/` - twelve webp images that make up the splash page background
- `assets/splash/bg-low.webp` - low res version of splash background that loads first
- `assets/splash/player-left/right.php` - two computer generated images of a skater and goalie of opposing teams for splash page

## Data 
- `data/derived-otg.csv` - 
- `data/enrich-boxjson-report.csv` - 
- `data/fix-from-html-report.csv` - 
- `data/fix-schedule-links-report.csv` - 
- `data/patch-boxscore-teams-report.csv` - 
- `data/rebuild-boxjson-report.csv` - 
- `data/reconcile-report.csv` - 
- `data/repair-boxlinks-report.csv` - 
*** `data/uploads/` *** - STHS output folder

## Docs
- `docs/DEVLOG.md` - document for devlog entries (can be moved to admin devlog after)
- `docs/PAGE-CHECKLIST.md` - document for auditing each page
- `docs/README.md` - document for site-wide rules and other important information
- `docs/SITE-MAP.md` - you are here
- `docs/SITE-STATUS.md` - tracks the status of every page in the site

## Includes
- `includes/account-locks.php` -
- `includes/acl.php` -
- `includes/admin-2fa-codes.php` -
- `includes/admin-helpers.php` - Helpers for admin pages
- `includes/admin-pin-throttle.php` -
- `includes/audit.php` -
- `includes/bootstrap.php` — functions + config + mysqli + get_db()
- `includes/config.php` - holds configuration settings for portal
- `includes/csrf.php` -
- `includes/db.php` - database connection
- `includes/error403.php` - Error page for ?
- `includes/functions.php` — `h, asset, url_root, u, uha_base_url, …` - Universal functions for the portal (i.e. auto logos)
- `includes/goals-extract.php` - for home-scores embedded page
- `includes/head-assets.php` - loads universal assets
- `includes/home-helpers.php` - for home page
- `includes/license.php` -
- `includes/log.php` -
- `includes/maintenance.php` - turns on maintenance mode
- `includes/password-reset.php` - password reset for users
- `includes/rate_limit.php` -
- `includes/recaptcha.php` -
- `includes/security-headers.php` -
- `includes/security-ip.php` -
- `includes/session.php` -
- `includes/sim_clock.php` - sets sim date based on game day #
- `includes/sqlite.php` -
- `includes/toast-center.php` -
- `includes/totp.php` -
- `includes/trust-device.php` -
- `includes/tx_helpers.php` - Helpers for transactions page
- `includes/util-mail.php` -

- Guards: `includes/license_guard.php`, `includes/session-guard.php`, `includes/guard.php` (admin only), `includes/user-auth.php`, `includes/auth.php`

## Tools
- `tools/box-index-dump.php` -
- `tools/derived-otg.php` -
- `tools/detect-html-pairs.php` -
- `tools/ls-boxscores.php` -
- `tools/peek-box.php` -
- `tools/peek-boxlinks` 
- `tools/peek-db.php` - check DB status
- `tools/repair-boxlinks.php` -

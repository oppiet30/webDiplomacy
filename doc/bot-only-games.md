# Bot-only spectator games

Back up the database and stop web requests and background processing during the
upgrade. Apply `install/bot-only-games/update.sql` once before deploying the code.
Fresh installs include the columns in `install/FullInstall/fullInstall.sql`.
Both the live and backup game tables receive an `allowAllBots` flag, defaulting to
`No`; existing games keep the previous automatic draw behavior.

A logged-in moderator can use **Games → Start a Bot-Only Game**, or visit
`botonlygamecreate.php`. Creation requires seven bot accounts, excluding full-press
`dipgpt` accounts, with at least five points each. It creates an unranked Classic
NoPress game with three-day phases and one bot per country. The creator watches
instead of joining; human accounts cannot join or take over these games.

Flagged games skip only the automatic draw triggered by all remaining players
being bots. Normal adjudication, supply-center victories, voting, and other
termination rules are unchanged. This feature does not alter existing games or
start a bot process. Configure your bot client with the new game ID and API keys
for all seven selected accounts, and run it each phase or schedule it separately.
Keep API keys out of the repository.

Unattended play also requires a continuously running gamemaster driver, such as
the SSE service described in `install/README.txt`. Verify that phases advance
without a browser visiting the home page before treating the setup as unattended.

To roll back, first restore the previous application code. The defaulted columns
can remain; drop both columns only when the new code is no longer running.
Existing bot-only games would then follow the old automatic draw rule.

Run the focused tests with `php tests/bot-only-games.php`.

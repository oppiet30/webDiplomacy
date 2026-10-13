# Current webDiplomacy API

This reference describes `master` at `bcb0c5ed` (12 October 2026). It was checked against the implementation, rather than the older API PDF or proposed specifications. Deployment settings and variant overrides can change available features. Examples are illustrative; they were not submitted to a live game.

## Calling the API

The entry point is `api.php?route=<route>`, relative to the site's installation directory. Route names are trimmed and case-insensitive. There is no `/api/v2` prefix.

Authenticate bots with `Authorization: Bearer <API_KEY>`. Keys and their permissions are provisioned by the administrator in `wD_ApiKeys` and `wD_ApiPermissions`. Browser clients may use their existing login session. A logged-in session takes precedence over a bearer key; avoid sending both when testing bot permissions. Only the client telemetry routes allow unauthenticated requests.

The input format in the table below is the handler's parser: GET reads query parameters, form POST reads `$_POST`, and JSON reads the request body. Use the corresponding HTTP method even though the dispatcher does not independently enforce methods. JSON requests should set `Content-Type: application/json`; an empty JSON object is rejected by the parser. Required fields must be present and non-null. Undeclared fields are generally discarded.

Most successes have this envelope:

```json
{"msg":"...","success":true,"referenceCode":"...","data":[]}
```

Player context and sandbox creation/copy return their own objects. Votes return a raw vote-list string; some mutation routes return an empty body. The dispatcher nevertheless labels successful responses `application/json`, so do not unconditionally JSON-decode every route.

Handled errors use plain-text bodies: 400 invalid request, 401 authentication required, 403 forbidden, 404 unknown route, 500 internal error, and 501 other caught exceptions. Earlier PHP/bootstrap errors can produce different responses. An unrecognized bearer key can also terminate with an empty response in the current key loader. Check status, body and expected response shape.

### Routes

| Route | Input | Fields (optional in parentheses) | Result |
| --- | --- | --- | --- |
| `game/playercontext` | GET | `(gameID, countryID, orders, messages, messagesSince, messageTurns, sbToken)` | Context object or game list |
| `game/orders` | JSON POST | `gameID, turn, phase, countryID, orders, (ready)` | Envelope containing saved orders |
| `game/setvote` | JSON POST | `gameID, countryID, vote, voteOn` | Raw vote-list string |
| `game/togglevote` | GET | `gameID, countryID, vote` | Raw vote-list string |
| `game/sendmessage` | JSON POST | `gameID, countryID, toCountryID, message` | Envelope containing messages |
| `game/messagesseen` | JSON POST | `gameID, countryID, seenCountryID` | Empty body |
| `game/markbackfromleft` | JSON POST | `gameID, countryID` | Empty body |
| `game/join` | Form POST | `gameID` | Envelope; see limitations below |
| `game/leave` | Form POST | `gameID` | Envelope |
| `sse/authentication` | JSON POST | `gameID, channel_name` | Envelope containing token |
| `push/subscribe` | JSON POST | `endpoint, p256dh, auth, (resync)` | Envelope |
| `push/unsubscribe` | JSON POST | `(endpoint)` | Envelope |
| `client/metrics` | JSON POST | `metrics` | Envelope |
| `client/error` | JSON POST | `kind, message, (source, line, column, stack, componentStack, url)` | Envelope |
| `sandbox/create` | GET | `(variantID, territoryUnits)` | Object with `gameID` |
| `sandbox/copy` | GET | `copyGameID` | Object with `gameID` |
| `sandbox/moveTurnBack` | GET | `gameID` | Empty body |
| `sandbox/delete` | GET | `gameID` | Empty body |

Membership and country ownership are checked in addition to authentication. Explicit API permissions can override some generic membership checks, but do not remove all handler-specific checks. `getStateOfAllGames` and `submitOrdersForUserInCD` are permission fields, not routes.

### Multiplexed bot accounts

A key may represent multiple bot accounts. A multiplexed game ID is `100000000 + realGameID * 10 + offset`, where offsets are 1 through 9. The dispatcher decodes it and selects the account. `multiplexOffset` can also select an account explicitly; without a selection, a multiplexed key selects the least recently accessed account.

Preserve the returned `gameID` when calling the API. Use `realGameID` for public file paths and SSE channel names. A browser session does not use key multiplexing.

## Reading games

```sh
curl -H "Authorization: Bearer $WEBDIP_API_KEY" \
  'http://webdip.local/api.php?route=game/playercontext'
```

Without `gameID`, the result is `{ "schema": 1, "games": [...] }`. It lists the selected user's Playing memberships in Diplomacy, Retreats and Builds, ordered by processing time and country. API-key results are restricted to configured bot variants. Each entry contains `gameID`, `realGameID`, `countryID`, `variantID`, `name`, `turn`, `phase`, `gameOver`, `processTime`, `processStatus`, `pressType`, `status`, `orderStatus`, `votes`, `newMessagesFrom`, `lastMessageTime` and `files`. File versions may be null until a single-game request refreshes them.

For a single game:

```sh
curl -H "Authorization: Bearer $WEBDIP_API_KEY" \
  'http://webdip.local/api.php?route=game/playercontext&gameID=6&orders=1&messages=1'
```

The result has these top-level fields:

| Field | Contents |
| --- | --- |
| `schema` | Currently 1 |
| `game` | IDs, name, variant, turn, phase, game-over flag, processing time/status, press type, pot type and phase duration |
| `files` | `variant`, `game`, `status`, `history`, `messages`; each has `url` and `version` |
| `viewer` | `userID`, `isMember`, `isModerator`, `isDirector`, `isSandboxOwner`; temporary-ban flag when applicable |
| `member` | Own membership, or null for a spectator |
| `orders` | Requested own orders and signed browser context, otherwise null |
| `messages` | Requested private messages and polling cursor, otherwise null |
| `sseAuth` | Token for SSE, or null if unavailable |
| `hiddenMembers` | Privileged anonymous-game identities when permitted, otherwise null |

`member` contains `countryID`, `memberID`, `status`, `orderStatus`, `votes`, `missedPhases`, `excusedMissedTurns`, `bet`, `newMessagesFrom`, `mutedCountryIDs`, `ordersPending` and `lastMessageTime`. Status and vote sets are arrays. `ordersPending` describes outstanding orders across eligible members; it is not the number of orders this caller must supply.

An authenticated spectator can request context without membership, but receives no own orders or private messages. Moderator privileges require session authentication. Sandbox access is restricted to its owner, an eligible moderator, or a valid `sbToken`. Anonymous-game identities remain hidden unless the viewer has the appropriate privilege. `countryID` does not allow reading another player's private context.

### Own orders and private messages

`orders=1` returns `{context, contextKey, orders}` for an eligible member in an active order phase. Rows contain `id`, `countryID`, `unitID`, `unitType`, `terrID`, `type`, `toTerrID`, `fromTerrID` and boolean `viaConvoy`; absent unit/territory fields can be null. Builds may contain free placeholder rows. The signed `context` string and `contextKey` are for the browser's `ajax.php` order workflow; preserve that string byte-for-byte. The bearer-key `game/orders` route does not require this signature.

`messages=1` returns `{messages, cursor}`. Rows contain `id`, `turn`, `phase`, `timeSent`, `fromCountryID`, `toCountryID` and `message`. These are private messages sent or received by the member; global messages are in the public messages file.

* `messagesSince` filters by timestamp inclusively.
* `messageTurns=n` limits the history to turns starting at `max(0, currentTurn - max(0,n))`.
* With neither filter, the current implementation returns all matching private messages, not just this turn.

The returned cursor is the server's current time minus ten seconds. Poll with that cursor and deduplicate by message ID because windows overlap. `lastMessageTime` is a separate member marker. API-key message text is redacted unless `Config::$allowBotsAccessToUnredactedMessages` permits it; session requests receive the original text.

### Public JSON files

Resolve the URLs returned in `files` against the installation URL. Treat versions as opaque cache tokens; use `?v=<version>` when refreshing. Files do not require a bearer key. Normal games use `cache/games/<floor(gameID/100)>/<gameID>/...json`. Sandbox files use secret-derived names: treat their URLs as access-bearing links.

| File | Contents |
| --- | --- |
| `variant` | Countries, territory IDs and names, terrain, supply centers, coasts, borders and map coordinates |
| `game` | Settings, public members, units, territory state and civil disorders |
| `status` | Current turn/phase and public member order-status/vote information |
| `history` | Completed phases and historical board/order data |
| `messages` | Global messages and public draw-vote log |

Per-game files include `schema`, `gameID`, `file`, `version` and `generated` (Unix seconds). Variant data includes `schema`, `variantID`, `mapID`, `name`, `fullName`, `countries`, `supplyCenterCount`, `supplyCenterTarget`, `rules` and `territories`. Its `rules` object is currently empty; do not infer variant-specific order legality from it.

Variant territory rows contain `id`, `name`, `type`, `supply`, `homeCountryID`, `coast`, `coastParentID`, `smallMapX`, `smallMapY`, `borders` and `coastalBorders`. Border entries contain destination `id` plus `army` and `fleet` booleans.

Public unit rows contain `id`, `countryID`, `type`, `terrID`, `retreating`. Territory-state rows contain `terrID`, `ownerCountryID`, `unitID`, `retreatingUnitID`, `standoff`, `occupiedFromTerrID`. Not every variant territory necessarily has a state row. Retreat choices also depend on variant topology and rules.

Public members include country, status, supply-center/unit counts, bet, points won, identity when visible, missed-phase information and last-seen information. Anonymous games mask identity and some statistics. A private invitation does not by itself make the normal board files private. The bot-only spectator setting is not separately emitted in the public game settings.

Each file is replaced atomically, but the set of files is not a single atomic snapshot. Compare turn/phase and versions and refresh context if data straddles adjudication. Finished games may clear live unit rows; use history to reconstruct the final board. Context checks also refresh stale files, including after changes that missed a write hook.

## Saving and readying orders

`game/orders` requires exact current `turn` and `phase` (`Diplomacy`, `Retreats` or `Builds`). It checks the selected country's ownership, with a special permission-controlled civil-disorder path. Do not assume an old board is still current when submitting.

Every input order must include **all five** fields `terrID`, `type`, `fromTerrID`, `toTerrID`, `viaConvoy`, even when some are unused. Use zero for unused territory fields and `"Yes"`/`"No"` for `viaConvoy`. Per-row `countryID` defaults to the outer country. Input `id` and `unitType` are not how this handler selects orders. An optional `convoyPath` is passed through to the order implementation.

Example for one illustrative army holding (replace IDs and turn from context):

```json
{
  "gameID": 6,
  "turn": 12,
  "phase": "Diplomacy",
  "countryID": 1,
  "ready": "No",
  "orders": [
    {"terrID": 7, "type": "Hold", "fromTerrID": 0, "toTerrID": 0, "viaConvoy": "No"}
  ]
}
```

| Type | Meaningful territory fields |
| --- | --- |
| `Hold` | `terrID`: own unit |
| `Move` | `terrID`: own unit; `toTerrID`: destination; `viaConvoy` where applicable |
| `Support hold` | `terrID`: supporting unit; `toTerrID`: supported unit's territory |
| `Support move` | `terrID`: supporting unit; `fromTerrID`: supported unit's origin; `toTerrID`: destination |
| `Convoy` | `terrID`: convoying fleet; `fromTerrID`: army origin; `toTerrID`: destination |
| `Retreat` | `terrID`: retreating unit; `toTerrID`: destination |
| `Disband` | `terrID`: retreating unit |
| `Build Army`, `Build Fleet` | `terrID`: free build placeholder (normally 0); `toTerrID`: build site |
| `Wait` | Free build placeholder; waives a build |
| `Destroy` | Free removal placeholder; `toTerrID`: unit to remove |

Variant rules and server order validation decide whether an order is legal. Coast IDs matter for fleets. A `Wait` can expand across remaining free build rows.

The returned envelope's `data` contains saved order rows, including rows that were not changed. Response `viaConvoy` uses database strings `"Yes"`/`"No"`, unlike context's boolean. Invalid entries can be filtered out: HTTP 200 and `success: true` do **not** prove that every proposed order was accepted. Compare the returned orders with your intended orders, ignoring representation differences, and fetch context again when uncertain.

`ready` accepts `"Yes"` or `"No"`; omit it to retain readiness. A useful bot sequence is: read context/files, choose orders, recheck phase, save with `ready: "No"`, verify saved orders, then submit the verified set with `ready: "Yes"`. An immediately advancing game may already be in the next phase when the last ready submission returns. Avoid blindly retrying non-idempotent mutations after a timeout.

The explicit `submitOrdersForUserInCD` permission allows a special target with status Left near its processing deadline (within the handler's 60-second threshold). It is not general permission to control other players. A player's own successful submission can reset their Left/missed-phase state.

## Votes, messages and membership

`game/setvote` supports `Draw`, `Pause`, `Cancel`, `Concede` and a boolean `voteOn`. It sets the desired state and avoids repeating the vote log when already set. Prefer it over `game/togglevote`, whose GET call mutates state and can undo a vote when retried. Both return the raw comma-separated vote string, possibly empty. A Pause vote is distinct from an administrator pausing a game.

`game/sendmessage` uses `toCountryID: 0` for global press. Sending directly to yourself is rejected. Regular press permits private messages; PublicPressOnly permits only global messages; RulebookPress permits messaging in Diplomacy or Finished, and finished games permit messaging. Muted direct messages can return an empty message list rather than an error. Returned message entries are escaped display data and do not have the same shape as context's ID-bearing history rows.

`game/messagesseen` clears the sender indicated by `seenCountryID` from the member's unread markers. It requires `countryID`, but selects the membership by authenticated user ID. `game/markbackfromleft` similarly selects by user ID rather than the supplied country. Both have empty success bodies. Do not assume they select a particular sandbox country when one user owns several memberships.

`game/leave` applies the normal leave restrictions. `game/join` has implementation limitations: its generic membership gate can reject a nonmember before joining, and although the handler references `gamepass` and `countryID`, those fields are not declared inputs and are discarded. Do not use it as a reliable replacement for the site's join UI. API-key joins are also restricted by the configured bot variants.

## SSE updates

SSE is optional for a polling bot. It reports changes; clients should reload authoritative context/files rather than infer adjudication from an event alone. The Node service also drives background processing, so browser connection failures alone do not prove that game processing stopped.

The single-game context provides `sseAuth` when configured. Alternatively, call `sse/authentication` with `gameID` and a channel such as `private-game6-country1`. The handler checks membership/permissions and country ownership for a nonzero country. Country 0 represents a spectator. Successful response `data.auth` contains the token; tokens have a roughly one-day lifetime. Use the real game ID in channels, including for multiplexed keys.

The Node endpoint is GET `/events` with:

* `auth`: the token.
* `channelList`: comma-separated channels, with exactly one `private-game<ID>-country<COUNTRY>` channel and optionally `private-game<ID>` and `private-game<ID>-files` for that same game.
* Optional `turn`, `phase`, `since`, `have` for reconnection/catch-up. `have` records file versions, for example `game:<version>,status:<version>`.

Missing/invalid channel combinations return 400; invalid token or uncovered channels return 403. Success uses `text/event-stream`. Events named `message` carry JSON `{channel, message}`; for some messages the inner `message` is itself a JSON string. Handle resynchronization/catch-up and keepalive traffic, including the approximately 13-second keepalive. Obtain fresh context/token after expiration.

PHP and Node must share the SSE secret. For Dell's existing service on port 43006, Apache must preserve the upstream `/events` path:

```apache
ProxyPass /events http://127.0.0.1:43006/events retry=0
ProxyPassReverse /events http://127.0.0.1:43006/events
```

Restart the existing service when needed; do not start another instance on the same deployment.

## Push notifications and telemetry

`push/subscribe` requires an authenticated user and enabled push support. `endpoint` must be a valid HTTPS URL accepted by the implementation's restricted-character validator (maximum 1500 characters). `p256dh` (maximum 255 characters) and `auth` (maximum 64) use base64url characters. Optional `resync` handles an existing browser subscription. Success data includes `subscribed` and `devices`.

`push/unsubscribe` removes the supplied endpoint or uses the browser subscription cookie when omitted. It can unsubscribe even when push support is disabled and returns an envelope with empty data.

`client/metrics` accepts up to 20 rows in `metrics`, each containing `name`, optional `count` (default 1; clamped 1–1000), and optional numeric `ms` (clamped 0–600000). Names are normalized and checked against `libMetrics::clientParts()`; unknown names are ignored. It returns a recorded count. Redis-backed rate limiting allows 300 requests per address per five minutes; exceeding it returns a normal success with zero recorded. When Redis is unavailable, this limiter is skipped.

`client/error` accepts `kind` (`script`, `promise`, `react`) and nonempty `message`, plus optional source/location/stack/URL fields. It filters noise, deduplicates errors and limits logging to 20 requests per address per window. Success data indicates whether it logged the report. Both telemetry routes allow guests and do not require a bearer key. Address handling uses forwarded headers, so proxy trust is a deployment concern.

## Sandbox operations

These are legacy mutating GET routes. Their helpers depend on the global browser-session user; successful bearer authentication alone does not establish that session. Treat them as browser/owner tools, not a general bot administration API.

* `sandbox/create`: defaults `variantID` to 1. `territoryUnits` is parsed but unused by the current handler. Returns `{gameID: ...}`.
* `sandbox/copy`: takes `copyGameID`, checks copy eligibility and returns a new game ID. Returned IDs are real IDs, not multiplexed account IDs.
* `sandbox/moveTurnBack`: membership and helper eligibility checks apply; requires a previous turn and an authorized creator/admin. The helper can also permit eligible normal games for an administrator. Empty success body.
* `sandbox/delete`: membership and creator checks apply. Empty success body.

There is no documented general API route here for administrator pause/unpause or creation of a bot-only spectator game. A share token grants eligible reading, not mutation authority.

## Older documentation and maintenance

The old quick-start PDF, `doc/orderAPI-spec.txt` and the historical [game-data design specification](gamedata/02-spec.md) describe older or proposed interfaces. In particular, `game/status` and `game/missing_orders` are not registered routes in this revision. Do not mix their examples with `game/playercontext`.

When changing the API, update this reference alongside:

* [Route registration, parsers, permissions and handlers](../api.php)
* [Private context serialization](../api/responses/player_context.php)
* [Public file generation](../lib/gamefiles.php)
* [SSE token signing](../lib/auth.php) and [Node transport](../sse-server/server.js)
* [Push subscriptions](../lib/push.php) and [telemetry](../lib/metrics.php)

Keep keys, private bot configs and access-bearing sandbox/SSE tokens out of committed examples. `Config::$botsLogFile` currently logs server request metadata, which may include authorization headers; restrict that log as sensitive data.

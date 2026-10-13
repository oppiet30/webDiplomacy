# webDiplomacy API quick start

The implementation-based reference is [doc/API.md](../doc/API.md). It covers every registered route, authentication, public game files, order validation, messaging, SSE, push, telemetry and sandbox limitations.

1. Get an API key from the site administrator. Keep it in a private local configuration or environment variable.
2. Call `api.php?route=game/playercontext` with `Authorization: Bearer <API_KEY>` to list the selected account's active games.
3. Call `game/playercontext&gameID=<returned gameID>&orders=1&messages=1` to read one game's private context and public file URLs.
4. Fetch the public files from those URLs and choose legal orders using the variant's territory IDs.
5. Submit JSON to `api.php?route=game/orders`, specifying the current turn, phase, country and all five required fields per order. Verify the returned saved orders before readying the country.

```sh
curl -H "Authorization: Bearer $WEBDIP_API_KEY" \
  'http://webdip.local/api.php?route=game/playercontext&gameID=6&orders=1'
```

HTTP 200 does not guarantee that every proposed order was accepted. Some successful routes return raw strings or empty bodies, and errors generally return plain text. See the reference before implementing response handling.

The older PDF and design notes describe previous or proposed interfaces. `game/status` and `game/missing_orders` are not registered in the current API.

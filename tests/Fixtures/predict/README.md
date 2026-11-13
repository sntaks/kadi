# Prediction market fixtures

Bodies the Kadi API sends on its prediction routes, used by `tests/Feature/Predict`.

- **Live answers** (the file is `{status, body}`), from the live API's read routes on 2026-11-13:
  `market-polymarket`, `markets-list-polymarket` (4 of 78 markets, two of them in one event), `history-polymarket`,
  `estimate-polymarket`, `updates-quiet` (prices fresh, nothing moved), `refusal-since-too-old`,
  `refusal-market-not-found`, `refusal-validation`.
- **Live answers, afternoon of 2026-11-13** (after KadiApi priced new imports with the pool): `market-polymarket-pool`,
  `history-polymarket-pool`, `estimate-polymarket-pool` (an imported pool market, no attribution), `updates-has-more`
  (trimmed to one pool and one reference market, `has_more` kept true) and `updates-has-more-page-2` (its `after_id` page).
- **Documentation samples** (the file is the whole body), from `documentation.html#predict` on 2026-11-13, for what
  the live API could not show yet (no custom market, and no call made as a player): `market-pool`, `market-lmsr`,
  `markets-list`, `history`, `estimate`, `updates`, `quote`, `bet-placed`, `bets-list`, `refusal-pool-side-full`,
  `refusal-price-changed`.

No player data: market text is public, and the ids, references and balances in the samples are the documentation's.

# Changelog

## 2.0.0 (2026-11-27)

The Poggit virion version for the original [pmmp/PocketMine-MP](https://github.com/pmmp/PocketMine-MP) 5.x is kept on the
[`legacy/pmmp-5.44`](https://github.com/presentkim-pm/selection-visualize-utils/tree/legacy/pmmp-5.44) branch.

### ⚠️ Breaking changes
- Targets [axolotl-pm/PocketMine-MP](https://github.com/axolotl-pm/PocketMine-MP) and **PHP 8.4+**.
- Distributed with Composer: the Poggit virion files (`.poggit.yml`, `virion.yml`) are removed, and the package is
  named `presentkim/selection-visualize-utils` (the old Composer name had no vendor).
- `Selection::sendTo()` throws `RuntimeException` when a column has no free Y slot (previously it silently fell back to
  `World::Y_MAX`, a slot that could collide with another selection).
- No `ext-morton` dependency anymore.

### ✨ Features
- `Selection::forget(Player)`: stop tracking a player and free its slot without sending packets.
- `Selection::releaseAll(Player)`: free every slot of a player across all selections. Call it on `PlayerQuitEvent`.
- `Selection` tracks viewers and Y slots per player and per world with `WeakMap`s, so no `Player` is kept alive.
- `BlockPreview::clear()` cancels a preview that was requested but not yet sent (the 1-tick delay).

### 🐛 Fixes
- `Selection::restoreFrom()` only sends restore packets if the player is still connected and in the world the block
  was sent in.
- `Selection` uses `floor()` for fractional coordinates.
- `BlockPreview` remembers the world and whether a liquid overlay was sent for each preview, so `clear()` restores
  the right world and only clears the liquid layer when one was sent.
- No liquid layer is sent when `PreviewEntry::$liquidBlock` is `null` (the documentation said otherwise).

### 📝 Documentation
- README rewritten. It notes that very large selections may exceed the Bedrock client's structure block bounding box
  limit and not be fully drawn.

### 🧪 Development
- PHPUnit tests and GitHub Actions CI on PHP 8.4 / 8.5.

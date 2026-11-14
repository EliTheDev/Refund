<div align="center">
	<h1>Refund</h1>
	<h3>Review player death inventories and safely return lost items and experience.</h3>
</div>

# Features
- Automatically saves a player's inventory, armor, off-hand items, and experience when they die with items or XP.
- Browse and search saved death records through in-game menus.
- Inspect death details, including the time, location, and cause.
- Preview a saved inventory without changing it, or teleport to the death location.
- Refund items through an interactive chest or deliver the remaining contents directly to an online player.
- Preserve unclaimed items and experience when a player's inventory is full or a chest claim is only partially completed.
- Track refund progress and who issued and received each refund.
- Inspect and prune old, completed refund records.
- Store refund records in SQLite.

# Requirements
- [InvMenu](https://github.com/Muqsit/InvMenu), included as a virion in the release (not a separate plugin)

# Installation
1. Place the Refund plugin `.phar` in the server's `plugins` folder.
2. Restart the server.

# How to issue a refund
1. Open a player's saved death history with `/refund <player>` or browse histories with `/refunds`.
2. Select a death record to view its details.
3. Optionally preview the items or teleport to the recorded death location.
4. Choose **Refund via Chest** to let the player claim items and XP individually, or **Give to Player** to deliver contents directly.
5. Confirm the refund. The recipient must be online; any contents that cannot be delivered remain saved for a later claim.

# Commands
| Command | Description | Permission |
|---------|-------------|------------|
| `/refund <player>` | View a player's saved death history. Online player names may use a unique prefix. | `refund.command` |
| `/refunds` | Browse the refund list. | `refunds.command` |
| `/refunds inspect <age>` | List the number of completed refunds older than the specified age and show up to 20 records. | `refunds.command` |
| `/refunds prune <age>` | Preview how many completed refunds older than the specified age would be deleted. | `refunds.command` |
| `/refunds prune <age> confirm` | Delete matching completed refunds and their history entries. | `refunds.command` |

Durations accept seconds, minutes, hours, days, weeks, months, or years. A unitless number is interpreted as days. Months are 30 days and years are 365 days.

Examples: `/refunds inspect 30d`, `/refunds prune 6mo`, and `/refunds prune 1y confirm`.

The `refund.command` and `refunds.command` permissions default to operators. Pruning only removes completed refunds; pending and partially claimed refunds are preserved.

# API
Other plugins can access Refund records through the public methods on `refund\Main`. For example, retrieve a player's first saved death record:

```php
use refund\Main;

$refundPlugin = Main::getInstance();
$refund = $refundPlugin->getRefundByPlayerDeath($player->getName(), 1);

if ($refund !== null) {
    $inventoryContent = $refund->getItem("inventory");
    $armorContent = $refund->getItem("armor");
    $offhandContent = $refund->getItem("offhand");
    $experience = $refund->getExperience();
    $isRefunded = $refund->isRefunded();
}
```

`getRefundByPlayerDeath()` returns `null` if there is no record for that player and death number. Other available methods include `getPlayerRefunds(string $name)` (death numbers mapped to refund IDs), `getRefundById(int $id)`, and `getUsersData()` (all player-to-death mappings).

### List a player's saved deaths
`getPlayerRefunds()` returns an array mapping each death number to its refund ID. Use `getRefundById()` to load the corresponding record:

```php
foreach ($refundPlugin->getPlayerRefunds($player->getName()) as $deathNumber => $refundId) {
    $refund = $refundPlugin->getRefundById($refundId);
    if ($refund === null) {
        continue;
    }

    $status = $refund->isRefunded() ? "claimed" : "pending";
    echo "Death #{$deathNumber}: Refund #{$refundId} ({$status})";
}
```

### Read a refund by ID
Use `getRefundById()` when you already have a refund ID. It returns `null` if the record does not exist:

```php
$refund = $refundPlugin->getRefundById($refundId);
if ($refund !== null) {
    $deathTime = $refund->getTime();
    $cause = $refund->getKilledBy();
    $position = $refund->getPosition();
    $armor = $refund->getItem("armor");
    $offHand = $refund->getItem("offHand");
}
```

# AI Use
The plugin's original code was written without AI, AI was later used to help clean up the code.

# License
This project is licensed under the [GNU General Public License, version 3](https://www.gnu.org/licenses/gpl-3.0.html).

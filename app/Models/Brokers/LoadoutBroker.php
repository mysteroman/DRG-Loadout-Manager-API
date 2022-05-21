<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Models\Brokers;


use Zephyrus\Exceptions\DatabaseException;

class LoadoutBroker extends Broker
{
    public function findAllFromRequest(\stdClass $request): array
    {
        $sql = 'select l.id "id", u.username "owner", l.id_dwarf "dwarf", l.id_version "version", l.name "name", l.description "description", l.creation_date "creation_date", l.edition_date "edition_date" from loadout l join "user" u on u.id = l.id_user';
        $params = [];
        // Joins

        // Wheres
        $sql .= ' where l.id_version = latest_version()';

        // Sort
        $sql .= ' order by ';
        if ($request->sort == 'asc_date') $sql .= 'l.edition_date asc';
        else if ($request->sort == 'name') $sql .= 'l.name desc';
        else if ($request->sort == 'asc_name') $sql .= 'l.name asc';
        else $sql .= 'l.edition_date desc';

        // Page
        $offset = $request->page;
        $offset *= $limit = $request->count;
        array_push($params, $limit, $offset);
        $sql .= ' limit ? offset ?';

        return $this->select($sql, $params, function($loadout) {
            $broker = new DataBroker($loadout->version);
            $loadout->version = $broker->findVersionById($loadout->version);
            $loadout->dwarf = $broker->findDwarfById($loadout->dwarf, false);
            return $loadout;
        });
    }

    public function findById(int $id): ?\stdClass
    {
        $sql = 'select l.id "id", u.username "owner", l.id_dwarf "dwarf", l.id_version "version", l.name "name", l.description "description", l.creation_date "creation_date", l.edition_date "edition_date" from loadout l join "user" u on u.id = l.id_user where l.id = ?';
        $loadout = $this->selectSingle($sql, [$id]);
        if (is_null($loadout)) return null;
        $broker = new DataBroker($loadout->version);
        $loadout->version = $broker->findVersionById($loadout->version);
        $loadout->dwarf = $broker->findDwarfById($loadout->dwarf);
        $loadout->perks = $this->findPerks($id, $broker);
        $loadout->grenade = $this->findAnyItemInSlot($id, $loadout->dwarf->grenades);
        $loadout->primary = $this->findAnyItemInSlot($id, $loadout->dwarf->primaries);
        $loadout->secondary = $this->findAnyItemInSlot($id, $loadout->dwarf->secondaries);
        $loadout->pickaxe = $this->findAnyItemInSlot($id, [$loadout->dwarf->pickaxe]);
        $loadout->armor = $this->findAnyItemInSlot($id, [$loadout->dwarf->armor]);
        $loadout->mobility_tool = $this->findAnyItemInSlot($id, [$loadout->dwarf->mobility_tool]);
        $loadout->support_tool = $this->findAnyItemInSlot($id, [$loadout->dwarf->support_tool]);
        return $loadout;
    }

    private function findPerks(int $id, Databroker $broker): \stdClass
    {
        $sql = 'select id_perk "perk", id_version "version", slot from loadout_perk where id_loadout = ?';
        $result = $this->select($sql, [$id], function($perk) use ($broker) {
            $perk->perk = $broker->findPerkById($perk->perk);
            return $perk;
        });
        $perks = new \stdClass();
        foreach ($result as $perk)
        {
            $slot = $perk->slot > 3 ? 'a' : 'p';
            $slot .= $perk->slot > 3 ? $perk->slot - 3 : $perk->slot;
            $perks->{$slot} = $perk->perk;
        }
        return $perks;
    }

    private function findAnyItemInSlot(int $id, array $items): ?\stdClass
    {
        $buffer = array_filter($items, function($item) {return !is_null($item);});
        if (count($buffer) == 0) return null;
        $items = [];
        foreach ($buffer as $item)
        {
            $items[$item->id] = $item;
        }
        $sql = 'select id_item "id" from loadout_item where ' .
            implode(' or ', array_map(function() {return 'id_item = ?';}, $items));
        $item = $this->selectSingle($sql, array_keys($items));
        if (is_null($item)) return null;
        if (isset($items[$item->id]->upgrades))
        {
            $item->upgrades = $this->findUpgrades($id, $items[$item->id]);
            if (isset($items[$item->id]->overclocks))
            {
                $item->overclock = $this->findOverclock($id, $items[$item->id]);
            }
        }
        return $item;
    }

    private function findUpgrades(int $id, \stdClass $item): array
    {
        $sql = 'select tier, slot from loadout_upgrade where id_loadout = ? and id_item = ?';
        $upgrades = [];
        $result = $this->select($sql, [$id, $item->id]);
        foreach ($result as $upgrade)
        {
            $upgrades[$upgrade->tier - 1] = $upgrade->slot - 1;
        }
        return $upgrades;
    }

    private function findOverclock(int $id, \stdClass $item): ?int
    {
        $sql = 'select id_overclock "id" from loadout_overclock where id_loadout = ? and id_item = ?';
        $overclock = $this->selectSingle($sql, [$id, $item->id]);
        if (is_null($overclock)) return null;
        return $overclock->id;
    }

    public function addLoadout(int $user, \stdClass $loadout): bool
    {
        $tr = function() use ($user, $loadout)
        {
            $sql = 'insert into loadout (id_user, id_dwarf, id_version, name, description) values (?, ?, latest_version(), ?, ?) returning id, id_version "version"';
            $insert = $this->selectSingle($sql, [
                $user,
                $loadout->dwarf,
                $loadout->name,
                $loadout->description
            ]);
            $id = $insert->id;
            $version = $insert->version;

            if (isset($loadout->perks))
            {
                $sql = 'insert into loadout_perks (id_loadout, id_perk, id_version, slot) values (?, ?, ?, ?)';
                foreach ($loadout->perks as $slot => $perk)
                {
                    if (strlen($slot) != 2) continue;

                    if ($slot[0] == 'P') $index = 0;
                    else if ($slot[1] == 'A') $index = 3;
                    else continue;

                    if ($slot[1] == '1') $index += 1;
                    else if ($slot[1] == '2') $index += 2;
                    else if ($slot[1] == '3' && $slot[0] == 'P') $index += 3;
                    else continue;

                    $this->query($sql, [$id, $perk->id, $version, $index]);
                }
            }

            $addItem = function(\stdClass $item) use ($loadout, $id, $version)
            {
                $sql = 'insert into loadout_item (id_loadout, id_item, id_dwarf) values (?, ?, ?)';
                $this->query($sql, [$id, $item->id, $loadout->dwarf]);

                if (isset($item->upgrades))
                {
                    $sql = 'insert into loadout_upgrade (id_loadout, id_item, tier, slot, id_version) values (?, ?, ?, ?, ?)';
                    foreach ($item->upgrades as $tier => $slot)
                    {
                        $this->query($sql, [$id, $item->id, $tier, $slot, $version]);
                    }

                    if (isset($item->overclock))
                    {
                        $sql = 'insert into loadout_overclock (id_loadout, id_item, id_overclock, id_version) values (?, ?, ?, ?)';
                        $this->query($sql, [$id, $item->id, $item->overclock, $version]);
                    }
                }
            };

            if (isset($loadout->grenade)) $addItem($loadout->grenade);
            if (isset($loadout->pickaxe)) $addItem($loadout->pickaxe);
            if (isset($loadout->armor)) $addItem($loadout->armor);
            if (isset($loadout->mobility_tool)) $addItem($loadout->mobility_tool);
            if (isset($loadout->support_tool)) $addItem($loadout->support_tool);
            if (isset($loadout->primary)) $addItem($loadout->mobility_tool);
            if (isset($loadout->secondary)) $addItem($loadout->support_tool);
        };
        try
        {
            $this->transaction($tr);
            return true;
        }
        catch (DatabaseException)
        {
            return false;
        }
    }
}
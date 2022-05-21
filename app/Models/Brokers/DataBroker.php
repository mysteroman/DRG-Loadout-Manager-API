<?php namespace Models\Brokers;


use Zephyrus\Database\Core\Database;

class DataBroker extends Broker
{
    private int $version;
    private array $modifierCallback;
    private array $weaponCallback;

    public function __construct(?int $version = null)
    {
        parent::__construct();
        $this->version = $version ?? $this->findCurrentVersion()->id;
        $this->modifierCallback = [$this, 'handleModifier'];
        $this->weaponCallback = [$this, 'handleWeapon'];
    }

    public function findAllVersions(): array
    {
        $sql = 'select id, version, patch, alias from game_version';
        return $this->select($sql);
    }

    public function findVersionById(int $id): ?\stdClass
    {
        $sql = 'select id, version, patch, alias from game_version where id = ?';
        return $this->selectSingle($sql, [$id]);
    }

    public function findCurrentVersion(): ?\stdClass
    {
        $sql = 'select id, version, patch, alias from game_version where id = latest_version()';
        return $this->selectSingle($sql);
    }

    public function findAllDwarves(): array
    {
        $sql = 'select id, name, icon from dwarf';
        return $this->select($sql);
    }

    public function findDwarfById(int $id, bool $withData = true): ?\stdClass
    {
        $sql = 'select id, name, icon from dwarf where id = ?';
        $dwarf = $this->selectSingle($sql, [$id]);
        if (is_null($dwarf) || !$withData) return $dwarf;
        $dwarf->grenades = $this->findGrenadesByDwarf($id);
        $dwarf->pickaxe = $this->findPickaxeByDwarf($id);
        $dwarf->armor = $this->findArmorByDwarf($id);
        $dwarf->mobilityTool = $this->findMobilityToolByDwarf($id);
        $dwarf->supportTool = $this->findSupportToolByDwarf($id);
        $dwarf->primaries = $this->findPrimaryWeaponsByDwarf($id);
        $dwarf->secondaries = $this->findSecondaryWeaponsByDwarf($id);
        return $dwarf;
    }

    private function findGrenadesByDwarf(int $id_dwarf): array
    {
        $sql = 'select i.id "id", i.name "name", i.icon "icon", g.id "order" from grenade g join item i on i.id = g.id_item where i.id_dwarf = ?';
        $callback = function($grenade) {
            $grenade->stats = $this->findStatsByItem($grenade->id);
            return $grenade;
        };
        return $this->select($sql, [$id_dwarf], $callback);
    }

    private function findPickaxeByDwarf(int $id_dwarf): ?\stdClass
    {
        $sql = 'select i.id "id", i.name "name", i.icon "icon", 0 "order" from pickaxe p join item i on i.id = p.id_item where i.id_dwarf = ?';
        $pickaxe = $this->selectSingle($sql, [$id_dwarf]);
        if (is_null($pickaxe)) return null;
        $pickaxe->stats = $this->findStatsByItem($pickaxe->id);
        $pickaxe->upgrades = $this->findUpgradesByItem($pickaxe->id);
        return $pickaxe;
    }

    private function findArmorByDwarf(int $id_dwarf): ?\stdClass
    {
        $sql = 'select i.id "id", i.name "name", i.icon "icon", 0 "order" from armor a join item i on i.id = a.id_item where i.id_dwarf = ?';
        $armor = $this->selectSingle($sql, [$id_dwarf]);
        if (is_null($armor)) return null;
        $armor->stats = $this->findStatsByItem($armor->id);
        $armor->upgrades = $this->findUpgradesByItem($armor->id);
        return $armor;
    }

    private function findMobilityToolByDwarf(int $id_dwarf): ?\stdClass
    {
        $sql = 'select i.id "id", i.name "name", i.icon "icon", 0 "order" from mobility_tool a join item i on i.id = a.id_item where i.id_dwarf = ?';
        $mobility_tool = $this->selectSingle($sql, [$id_dwarf]);
        if (is_null($mobility_tool)) return null;
        $mobility_tool->stats = $this->findStatsByItem($mobility_tool->id);
        $mobility_tool->upgrades = $this->findUpgradesByItem($mobility_tool->id);
        return $mobility_tool;
    }

    private function findSupportToolByDwarf(int $id_dwarf): ?\stdClass
    {
        $sql = 'select i.id "id", i.name "name", i.icon "icon", 0 "order" from support_tool a join item i on i.id = a.id_item where i.id_dwarf = ?';
        $support_tool = $this->selectSingle($sql, [$id_dwarf]);
        if (is_null($support_tool)) return null;
        $support_tool->stats = $this->findStatsByItem($support_tool->id);
        $support_tool->upgrades = $this->findUpgradesByItem($support_tool->id);
        return $support_tool;
    }

    private function findPrimaryWeaponsByDwarf(int $id_dwarf): array
    {
        $sql = 'select i.id "id", i.name "name", i.icon "icon", w.id "order" from primary_weapon w join item i on i.id = w.id_item where i.id_dwarf = ?';
        return $this->select($sql, [$id_dwarf], $this->weaponCallback);
    }

    private function findSecondaryWeaponsByDwarf(int $id_dwarf): array
    {
        $sql = 'select i.id "id", i.name "name", i.icon "icon", w.id "order" from secondary_weapon w join item i on i.id = w.id_item where i.id_dwarf = ?';
        return $this->select($sql, [$id_dwarf], $this->weaponCallback);
    }

    private function findStatsByItem(int $item): \stdClass
    {
        $sql = 'select s.id "id", s.name "name", s.value_format "value_format", i.base_value "base_value" from item_stat i join stat s on s.id = i.id_stat where i.id_item = ? and i.id_version = ?';
        $stats = new \stdClass();
        $result = $this->select($sql, [$item, $this->version]);
        foreach ($result as $itemStat)
        {
            $stats->{$itemStat->id} = $itemStat;
        }
        return $stats;
    }

    private function findUpgradesByItem(int $item): array
    {
        $tiers = [];
        $sql = 'select tier, slot, id_modifier "modifier" from upgrade where id_item = ? and id_version = ?';
        $upgrades = $this->select($sql, [$item, $this->version], $this->modifierCallback);
        foreach ($upgrades as $upgrade)
        {
            $id_tier = $upgrade->tier - 1;
            unset($upgrade->tier);
            $id_slot = $upgrade->slot - 1;
            unset($upgrade->slot);
            if (!isset($tiers[$id_tier]))
            {
                $tiers[$id_tier] = [];
            }
            $tiers[$id_tier][$id_slot] = $upgrade;
        }
        return $tiers;
    }

    private function handleWeapon(\stdClass $weapon): \stdClass
    {
        $weapon->stats = $this->findStatsByItem($weapon->id);
        $weapon->upgrades = $this->findUpgradesByItem($weapon->id);
        $weapon->overclocks = $this->findOverclocksByItem($weapon->id);
        return $weapon;
    }

    private function findOverclocksByItem(int $item): array
    {
        $sql = 'select id, id_modifier "modifier", type from overclock where id_item = ? and id_version = ?';
        return $this->select($sql, [$item, $this->version], $this->modifierCallback);
    }

    private function handleModifier(\stdClass $container): \stdClass
    {
        $this->resolveModifier($container->modifier);
        return $container;
    }

    private function resolveModifier(int|\stdClass &$modifier): void
    {
        $sql = 'select name, icon, text from modifier where id = ? and id_version = ?';
        $modifier = $this->selectSingle($sql, [$modifier, $this->version]);
        $sql = 'select id_stat "stat", mul "operation", operand from stat_modifier where id_modifier = ? and id_version = ?';
        $stats = $this->select($sql, [$modifier->id, $this->version]);
        $modifier->stats = new \stdClass();
        foreach ($stats as $stat)
        {
            $id = $stat->stat;
            unset($stat->stat);
            $modifier->stats->{$id} = $stat;
        }
    }

    public function findAllPrimaries(callable $callback = null): array
    {
        $sql = 'select i.id "id", i.name "name", i.icon "icon", pw.id "order" from primary_weapon pw join item i on i.id = pw.id_item';
        return $this->select($sql, [], $callback);
    }

    public function findAllSecondaries(callable $callback = null): array
    {
        $sql = 'select i.id "id", i.name "name", i.icon "icon", sw.id "order" from secondary_weapon sw join item i on i.id = sw.id_item';
        return $this->select($sql, [], $callback);
    }

    public function findAllPerks(): array
    {
        $sql = 'select id, name, icon, effect, active from perk where id_version = ?';
        return $this->select($sql, [$this->version]);
    }

    public function findPerkById(int $id): ?\stdClass
    {
        $sql = 'select id, name, icon, effect, active from perk where id = ? and id_version = ?';
        return $this->selectSingle($sql, [$id, $this->version]);
    }
}
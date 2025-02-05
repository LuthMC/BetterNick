<?php

namespace Luthfi\BetterNick;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\scheduler\ClosureTask;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\world\sound\PopSound;

class BetterNick extends PluginBase {

    private $nicknames = [];
    private $cooldowns = [];
    private $config;
    private $tempNicknames = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        $this->nicknames = $this->config->get("nicknames", []);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onDisable(): void {
        $this->config->set("nicknames", $this->nicknames);
        $this->config->save();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        switch ($command->getName()) {
            case "nick":
                return $this->handleNickCommand($sender, $args);
            case "unnick":
                return $this->onUnnickCommand($sender);
            case "randomnick":
                return $this->onRandomNickCommand($sender);
            default:
                return false;
        }
    }

    public function onPlayerJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        if ($this->config->get("auto_nick_enabled", false)) {
            $this->applyAutoNick($player);
        }
    }

    private function applyAutoNick(Player $player) {
        $format = $this->config->get("auto_nick_format", "User");
        $randomNumber = mt_rand(1000, 9999);
        $nickname = str_replace("{random}", $randomNumber, $format);
        
        $this->nicknames[$player->getName()] = $nickname;
        $player->setDisplayName($nickname);
        $this->playSound($player);
        $player->sendMessage(TextFormat::GREEN . "BetterNick | Auto-nickname set to: " . TextFormat::WHITE . $nickname);
    }
    
    private function handleNickCommand(CommandSender $sender, array $args): bool {
        if (count($args) === 0) {
            return $this->sendNickHelp($sender);
        }

        $subCommand = strtolower(array_shift($args));
        switch ($subCommand) {
            case "set":
                return $this->handleNickSet($sender, $args);
            case "reset":
                return $this->handleNickReset($sender, $args);
            case "list":
                return $this->handleNickList($sender);
            case "temp":
                return $this->handleNickTemp($sender, $args);
            case "resetall":
                return $this->handleResetAll($sender);
            default:
                return $this->onNickCommand($sender, array_merge([$subCommand], $args));
        }
    }

    private function onNickCommand(CommandSender $sender, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "BetterNick | This command can only be used in-game.");
            return true;
        }

        $cooldown = $this->config->get("cooldown", 30);
        if (isset($this->cooldowns[$sender->getName()]) && time() - $this->cooldowns[$sender->getName()] < $cooldown) {
            $remaining = $cooldown - (time() - $this->cooldowns[$sender->getName()]);
            $sender->sendMessage(TextFormat::RED . "BetterNick | Please wait $remaining seconds before changing your nickname again.");
            return true;
        }

        if (count($args) < 1) {
            $sender->sendMessage(TextFormat::RED . "BetterNick | Usage: /nick <nickname>");
            return true;
        }

        $nickname = implode(" ", $args);
        $maxLength = $this->config->get("max_length", 16);
        if (strlen($nickname) > $maxLength) {
            $sender->sendMessage(TextFormat::RED . "BetterNick | Nickname cannot be longer than $maxLength characters.");
            return true;
        }

        $blacklist = $this->config->get("blacklist", []);
        foreach ($blacklist as $word) {
            if (stripos($nickname, $word) !== false) {
                $sender->sendMessage(TextFormat::RED . "BetterNick | That nickname contains banned words.");
                return true;
            }
        }

        $this->nicknames[$sender->getName()] = $nickname;
        $sender->setDisplayName($nickname);
        $sender->sendMessage(TextFormat::GREEN . "BetterNick | Your nickname has been set to " . TextFormat::WHITE . $nickname);
        
        if ($this->isTooSimilar($nickname)) {
            $player->sendMessage(TextFormat::RED . "BetterNick | That nickname is too similar to existing player names.");
            return false;
        }
        
        $this->cooldowns[$sender->getName()] = time();
        return true;
    }

    private function handleNickSet(CommandSender $sender, array $args): bool {
        if (!$sender->hasPermission("betternick.admin")) {
            $sender->sendMessage(TextFormat::RED . "BetterNick | You don't have permission for this command.");
            return true;
        }

        if (count($args) < 2) {
            $sender->sendMessage(TextFormat::RED . "BetterNick | Usage: /nick set <player> <nickname>");
            return true;
        }

        $target = $this->getServer()->getPlayerByPrefix(array_shift($args));
        if (!$target instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "BetterNick | Player not found.");
            return true;
        }

        $nickname = implode(" ", $args);
        $this->nicknames[$target->getName()] = $nickname;
        $target->setDisplayName($nickname);
        $sender->sendMessage(TextFormat::GREEN . "BetterNick | Set " . $target->getName() . "'s nickname to " . $nickname);
        return true;
    }

    private function handleNickReset(CommandSender $sender, array $args): bool {
        if (!$sender->hasPermission("betternick.admin")) {
            $sender->sendMessage(TextFormat::RED . "BetterNick | You don't have permission for this command.");
            return true;
        }

        if (count($args) < 1) {
            $sender->sendMessage(TextFormat::RED . "BetterNick | Usage: /nick reset <player>");
            return true;
        }

        $target = $this->getServer()->getPlayerByPrefix($args[0]);
        if (!$target instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "BetterNick | Player not found.");
            return true;
        }

        $username = $target->getName();
        if (isset($this->nicknames[$username])) {
            unset($this->nicknames[$username]);
            $target->setDisplayName($username);
            $sender->sendMessage(TextFormat::GREEN . "BetterNick | Reset " . $username . "'s nickname.");
        } else {
            $sender->sendMessage(TextFormat::RED . "BetterNick | " . $username . " doesn't have a nickname.");
        }
        return true;
    }

    private function handleNickTemp(CommandSender $sender, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "BetterNick | This command can only be used in-game.");
            return true;
        }

        if (count($args) < 2) {
            $sender->sendMessage(TextFormat::RED . "BetterNick | Usage: /nick temp <nickname> <time>");
            return true;
        }

        $timeString = array_pop($args);
        $nickname = implode(" ", $args);
        $duration = $this->parseDuration($timeString);

        if ($duration <= 0) {
            $sender->sendMessage(TextFormat::RED . "BetterNick | Invalid duration format. Use s/m/h/d (e.g., 30s, 1h)");
            return true;
        }

        if ($this->setNickname($sender, $nickname)) {
            $this->tempNicknames[$sender->getName()] = time() + $duration;
            $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($sender): void {
                if (isset($this->tempNicknames[$sender->getName()])) {
                    $this->resetNickname($sender);
                    $sender->sendMessage(TextFormat::YELLOW . "BetterNick | Your temporary nickname has expired.");
                }
            }), $duration * 20);
            
            $sender->sendMessage(TextFormat::GREEN . "BetterNick | Temporary nickname set for " . $timeString);
            return true;
        }
        return false;
    }
    
    private function handleNickList(CommandSender $sender): bool {
        if (!$sender->hasPermission("betternick.admin")) {
            $sender->sendMessage(TextFormat::RED . "BetterNick | You don't have permission for this command.");
            return true;
        }

        if (empty($this->nicknames)) {
            $sender->sendMessage(TextFormat::YELLOW . "BetterNick | No active nicknames.");
            return true;
        }

        $list = TextFormat::GREEN . "Active Nicknames:\n";
        foreach ($this->nicknames as $original => $nick) {
            $list .= TextFormat::WHITE . "$original → " . TextFormat::AQUA . "$nick\n";
        }
        $sender->sendMessage($list);
        return true;
    }

    private function onUnnickCommand(CommandSender $sender): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "BetterNick | This command can only be used in-game.");
            return true;
        }

        $username = $sender->getName();
        if (isset($this->nicknames[$username])) {
            unset($this->nicknames[$username]);
            $sender->setDisplayName($username);
            $sender->sendMessage(TextFormat::GREEN . "BetterNick | Your nickname has been reset.");
        } else {
            $sender->sendMessage(TextFormat::RED . "BetterNick | You don't have a nickname set.");
        }
        return true;
    }

    private function onRandomNickCommand(CommandSender $sender): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "BetterNick | This command can only be used in-game.");
            return true;
        }

        $format = $this->config->get("random_format", "Player{random}");
        $randomNumber = mt_rand(1000, 9999);
        $randomNick = str_replace("{random}", $randomNumber, $format);

        $this->nicknames[$sender->getName()] = $randomNick;
        $sender->setDisplayName($randomNick);
        $sender->sendMessage(TextFormat::GREEN . "BetterNick | Your nickname has been set to " . TextFormat::WHITE . $randomNick);
        return true;
    }

    private function handleResetAll(CommandSender $sender): bool {
        if (!$sender->hasPermission("betternick.admin")) {
            $sender->sendMessage(TextFormat::RED . "BetterNick | You don't have permission for this command.");
            return true;
        }

        foreach ($this->nicknames as $playerName => $nick) {
            $player = $this->getServer()->getPlayerExact($playerName);
            if ($player instanceof Player) {
                $player->setDisplayName($playerName);
            }
        }

        $this->nicknames = [];
        $this->tempNicknames = [];
        $sender->sendMessage(TextFormat::GREEN . "BetterNick | All nicknames have been reset.");
        return true;
    }

    private function parseDuration(string $time): int {
        $unit = strtolower(substr($time, -1));
        $value = intval(substr($time, 0, -1));

        return match ($unit) {
            'd' => $value * 86400,
            'h' => $value * 3600,
            'm' => $value * 60,
            's' => $value,
            default => 0
        };
    }

    private function isTooSimilar(string $nickname): bool {
        $maxSimilarity = $this->config->get("max_similarity", 3);
        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            $name = strtolower($player->getName());
            $similarity = levenshtein(strtolower($nickname), $name);
            if ($similarity <= $maxSimilarity && $similarity !== -1) {
                return true;
            }
        }
        return false;
    }

    private function playSound(Player $player): void {
        $player->getWorld()->addSound($player->getPosition(), new PopSound(), [$player]);
    }

    private function resetNickname(Player $player): void {
        $player->setDisplayName($player->getName());
        unset($this->nicknames[$player->getName()]);
        unset($this->tempNicknames[$player->getName()]);
        $this->playSound($player);
    }
    
    private function sendNickHelp(CommandSender $sender): bool {
        if ($sender->hasPermission("betternick.admin")) {
            $sender->sendMessage(TextFormat::YELLOW . "BetterNick Commands:\n" .
                "/nick <name> - Set your nickname\n" .
                "/nick set <player> <name> - Set someone nickname\n" .
                "/nick reset <player> - Reset someone nickname\n" .
                "/nick list - List all nicknames\n" .
                "/unnick - Reset your nickname\n" .
                "/randomnick - Get a random nickname");
        } else {
            $sender->sendMessage(TextFormat::YELLOW . "BetterNick Commands:\n/nick <name>\n/unnick\n/randomnick");
        }
        return true;
    }
}

<?php

namespace Luthfi\BetterNick;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Random;

class BetterNick extends PluginBase {

    private $nicknames = [];

    public function onEnable(): void {
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        switch ($command->getName()) {
            case "nick":
                return $this->onNickCommand($sender, $args);
            case "unnick":
                return $this->onUnnickCommand($sender);
            case "randomnick":
                return $this->onRandomNickCommand($sender);
            default:
                return false;
        }
    }

    private function onNickCommand(CommandSender $sender, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "BetterNick | This command can only be used in-game.");
            return true;
        }

        if (count($args) < 1) {
            $sender->sendMessage(TextFormat::RED . "BetterNick | Usage: /nick <nickname>");
            return true;
        }

        $nickname = implode(" ", $args);
        $this->nicknames[$sender->getName()] = $nickname;
        $sender->setDisplayName($nickname);
        $sender->sendMessage(TextFormat::GREEN . "BetterNick | Your nickname has been set to " . TextFormat::WHITE . $nickname);
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
            $sender->sendMessage(TextFormat::GREEN . "BetterNick | Your nickname has been reset to your default name.");
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

        $randomNick = "Player" . mt_rand(1000, 9999);
        $this->nicknames[$sender->getName()] = $randomNick;
        $sender->setDisplayName($randomNick);
        $sender->sendMessage(TextFormat::GREEN . "BetterNick | Your nickname has been set to " . TextFormat::WHITE . $randomNick);
        return true;
    }
}

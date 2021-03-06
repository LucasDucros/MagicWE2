<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2;

use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\UnknownBlock;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;


class API{
	/**
	 * "  -p also kills pets.\n" +
	 * "  -n also kills NPCs.\n" +
	 * "  -g also kills Golems.\n" +
	 * "  -a also kills animals.\n" +
	 * "  -b also kills ambient mobs.\n" +
	 * "  -t also kills mobs with name tags.\n" +
	 * "  -f compounds all previous flags.\n" +
	 * "  -r also destroys armor stands.\n" */

	/**
	 * Default
	 * Replaces the air normally
	 */
	const FLAG_REPLACE_AIR = 0x00; // default. -r
	/**
	 * Only change non-air blocks
	 */
	const FLAG_KEEP_AIR = 0x01; // -k
	/**
	 * The -a flag makes it not paste air.
	 */
	const FLAG_PASTE_WITHOUT_AIR = 0x02; // -a
	/**
	 * Pastes or sets hollow
	 */
	const FLAG_HOLLOW = 0x03; // -h
	/**
	 * The -n flag makes it only consider naturally occurring blocks.
	 */
	const FLAG_NATURAL = 0x04; // -n
	/**
	 * Without the -p flag, the paste will appear centered at the target location.
	 * With the flag, then the paste will appear relative to where you had
	 * stood relative to the copied area when you copied it.
	 */
	const FLAG_UNCENTERED = 0x05; // -p

	public static function flagParser(array $flags){
		$flagmeta = 1;
		foreach ($flags as $flag){
			switch ($flag){
				case "-r":
					$flagmeta ^= 1 << self::FLAG_REPLACE_AIR;
					break;
				case  "-k":
					$flagmeta ^= 1 << self::FLAG_KEEP_AIR;
					break;
				case  "-a":
					$flagmeta ^= 1 << self::FLAG_PASTE_WITHOUT_AIR;
					break;
				case  "-h":
					$flagmeta ^= 1 << self::FLAG_HOLLOW;
					break;
				case  "-n":
					$flagmeta ^= 1 << self::FLAG_NATURAL;
					break;
				case  "-p":
					$flagmeta ^= 1 << self::FLAG_UNCENTERED;
					break;
				default:
					Server::getInstance()->getLogger()->warning("The flag $flag is unknown");
			}
		}
		return $flagmeta;
	}

	/**
	 * Checks if a flag is used
	 * @param int $flags The return value of flagParser
	 * @param int $check The flag to check
	 * @return bool
	 */
	private static function hasFlag(int $flags, int $check){
		return ($flags & (1 << $check)) > 0;
	}

	/**
	 * @param Selection $selection
	 * @param Level $level
	 * @param Block[] $blocks
	 * @param array ...$flagarray
	 * @return string
	 */
	public static function fill(Selection $selection, Level $level, $blocks = [], ...$flagarray){
		$changed = 0;
		$time = microtime(TRUE);
		try{
			foreach ($selection->getBlocksXYZ() as $x){
				foreach ($x as $y){
					foreach ($y as $block){
						if ($block->y >= Level::Y_MAX || $block->y < 0) continue;
						$newblock = $blocks[array_rand($blocks, 1)];
						if ($level->setBlock($block, $newblock, false, false)) $changed++;
					}
				}
			}
		} catch (WEException $exception){
			return Loader::$prefix . TextFormat::RED . $exception->getMessage();
		}
		return Loader::$prefix . TextFormat::GREEN . "Fill succeed, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " blocks out of " . $selection->getTotalCount() . " changed.";
	}

	/**
	 * @param Selection $selection
	 * @param Level $level
	 * @param Block[] $blocks1
	 * @param Block[] $blocks2
	 * @param array ...$flagarray
	 * @return string
	 */
	public static function replace(Selection $selection, Level $level, $blocks1 = [], $blocks2 = [], ...$flagarray){
		$changed = 0;
		$time = microtime(TRUE);
		try{
			foreach ($selection->getBlocksXYZ(...$blocks1) as $x){
				foreach ($x as $y){
					foreach ($y as $block){
						if ($block->y >= Level::Y_MAX || $block->y < 0) continue;
						$newblock = $blocks2[array_rand($blocks2, 1)];
						if ($level->setBlock($block, $newblock, false, false)) $changed++;
					}
				}
			}
		} catch (WEException $exception){
			return Loader::$prefix . TextFormat::RED . $exception->getMessage();
		}

		return Loader::$prefix . TextFormat::GREEN . "Replace succeed, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " blocks out of " . $selection->getTotalCount() . " changed.";
	}

	public static function copy(Selection $selection, Level $level, Player $player, ...$flagarray){
		$flags = self::flagParser($flagarray);
		try{
			$clipboard = new Clipboard();
			$clipboard->setData($selection->getBlocksRelativeXYZ());
			if (self::hasFlag($flags, self::FLAG_UNCENTERED))//TODO relative or not by flags
				$clipboard->setOffset(new Vector3());
			else
				$clipboard->setOffset($player->getPosition()->subtract($selection->/*getMinVec3()*/getMaxVec3()));//SUBTRACT THE LEAST X Y Z OF SELECTION //TODO check if player less than minvec
			Loader::$clipboards[$player->getLowerCaseName()] = $clipboard;
		} catch (WEException $exception){
			return Loader::$prefix . TextFormat::RED . $exception->getMessage();
		}
		return Loader::$prefix . TextFormat::GREEN . "Copied selection to clipboard";
	}

	public static function paste(Clipboard $clipboard, Level $level, Player $player, ...$flagarray){//TODO: maybe clone clipboard
		$flags = self::flagParser($flagarray);
		$changed = 0;
		$time = microtime(TRUE);
		$vec3 = $player->getPosition();//proper stating pos
		try{
			foreach ($clipboard->getData() as $x => $xaxis){
				foreach ($xaxis as $y => $yaxis){
					foreach ($yaxis as $z => $block){
						/** @var Block $block */
						//flag test
						$blockvec3 = $vec3->add($x, $y, $z);
						if (!self::hasFlag($flags, self::FLAG_UNCENTERED))//TODO relative or not by flags
							$blockvec3 = $blockvec3->subtract($clipboard->getOffset())->subtract(count($clipboard->getData()) - 1,count($xaxis) - 1,count($yaxis) - 1);//todo fix offset
						if ($level->setBlock($blockvec3, $block, false, false)) $changed++;
					}
				}
			}
		} catch (WEException $exception){
			return Loader::$prefix . TextFormat::RED . $exception->getMessage();
		}
		return Loader::$prefix . TextFormat::GREEN . "Pasted selection " . (self::hasFlag($flags, self::FLAG_UNCENTERED) ? "absolute" : "relative") . " to your position, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " blocks changed.";
	}

	public static function blockParser(string $fullstring){
		$blocks = [];
		foreach (explode(",", $fullstring) as $blockstring){
			$block = Item::fromString($blockstring)->getBlock();
			if ($block instanceof Block) $blocks[] = $block;
			else{
				$block = BlockFactory::get(...explode(':', $blockstring));
			}
			if ($block instanceof Block) $blocks[] = $block;
			else{
				throw new WEException(Loader::$prefix . TextFormat::RED . $block . " is not a block!");
			}
			if ($block instanceof UnknownBlock){
				Loader::getInstance()->getLogger()->notice(Loader::$prefix . TextFormat::RED . $block . " is unknown to " . Loader::getInstance()->getServer()->getName());
			}
		}
		return $blocks;
	}
}
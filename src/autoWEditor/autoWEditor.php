<?php

namespace autoWEditor;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\entity\Entity;
use pocketmine\Player;
use pocketmine\item\Item;
use pocketmine\scheduler\PluginTask;
use pocketmine\entity\Zombie;
use pocketmine\level\Position;
use pocketmine\level\Level;
use pocketmine\block\Block;
use pocketmine\math\Vector3;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;

use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\particle\DustParticle;

use pocketmine\Server;


class autoWEditor extends PluginBase implements Listener{

	public $id = 152;
	public $sessions = [];
	public $datas = [];

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		$name = $sender->getName();
		if($label == "t"){
			if($sender->isOP()){
				if(isset($args[0])){
					if(isset($this->datas[$args[0]])){
						//$this->datas[$args[0]];
						$item = item::get(339,0,64)->setCustomName("§eAuto_WEditor");
						$sender->getInventory()->addItem($item->setLore(["[autowe]",$args[0],"0"]));
					}else{
						$sender->sendMessage("§cそのidのデータは存在しません。");
					}
				}else{
					$sender->sendMessage("/t 登録したid");
				}
			}
		}else if($label == "///copy"){
			if(!$sender->isOP()) return true;
			if(isset($args[0])){
				$this->copy($sender,$args[0]);
			}else{
				$sender->sendMessage("////copy 保存する名前");
			}
		}else if($label == "///e"){
			if(!$sender->isOP()) return true;
			if(isset($args[0])){
				if($args[0] == "0"){
					unset($this->sessions[$name][0]);
					$sender->sendMessage("[WEdit_Auto] POS1は削除されました。");
					return true;
				}else if($args[0] == "1"){
					unset($this->sessions[$name][1]);
					$sender->sendMessage("[WEdit_Auto] POS2は削除されました。");
					return true;
				}
			}
			unset($this->sessions[$name]);
			$sender->sendMessage("[WEdit_Auto] 座標データは削除されました。");
		}else if($label == "///undo"){
			if(!$sender->isOP()) return true;
			$this->undo($player);
		}else if($label == "///d"){
			if(!$sender->isOP()) return true;
			if(isset($args[0])){
				if(isset($this->datas[$args[0]])){
					unset($this->datas[$args[0]]);
					$player->sendMessage($args[0]."のデータを削除しました。");
				}else{
					$sender->sendMessage("このidのデータは存在しません。");
				}
			}
		}else if($label == "///list"){
			if(!$sender->isOP()) return true;
			foreach($this->datas as $id => $data){
				$sender->sendMessage("".$id); 
			}
		}
		return true;
	}

	public function copy($player,$id){
		$name = $player->getName();
		if(isset($this->sessions[$name][0]) and isset($this->sessions[$name][1])){
			$pos = $this->sessions[$name];
			$sx = min($pos[0]->x, $pos[1]->x);
			$sy = min($pos[0]->y, $pos[1]->y);
			$sz = min($pos[0]->z, $pos[1]->z);
			$ex = max($pos[0]->x, $pos[1]->x);
			$ey = max($pos[0]->y, $pos[1]->y);
			$ez = max($pos[0]->z, $pos[1]->z);
			$num = ($ex - $sx + 1) * ($ey - $sy +1) * ($ez - $sz + 1);
			Server::getInstance()->broadcastMessage("[WEdit_auto] ".$name."がコピーを開始します…(copy : ".$num."ブロック)");

			$level = $player->getLevel();

			$data = array();
			$vector = new Vector3(0, 0, 0);
			$testx = 0;
			$testy = 0;
			$testz = 0;
			$count = 0;
			for($x = $sx; $x <= $ex; ++$x){
				for($y = $sy; $y <= $ey; ++$y){
					for($z = $sz; $z <= $ez; ++$z){
			/*for($x = $ex; $sx <= $x; --$x){
				for($y = $ex; $sy <= $y; --$y){
					for($z = $ez; $sz <= $z; --$z){*/
			/*for($x = $sx; $x <= $ex; ++$x){
				for($y = $sy; $y <= $ey; ++$y){
					for($z = $sz; $z <= $ez; ++$z){*/
						$vector->x = $x;
						$vector->y = $y;
						$vector->z = $z;
						
						$data[++$count] = $level->getBlock($vector);
						//var_dump($sx+$x);
						//$data[] = array($x, $y, $z, $level->getBlockIdAt($x, $y ,$z), $level->getBlockDataAt($x, $y ,$z));
						//$level->setBlock($vector,$block,false,false);
						//++$testz;
					}
					//++$testy;
				}
				//++$testx;
			}

			$this->datas[$id] = [$data,$this->sessions[$name]];
			
			unset($data);
			Server::getInstance()->broadcastMessage("[WEdit_Auto] コピーが終了しました。");
		}else{
			$player->sendMessage("[WEdit] ERROR: POS1とPOS2が指定されていません。\n[WEdit] //helpを打ち、使い方を読んでください。");
		}
	}

	public function BlockBreak(BlockBreakEvent $event){//1
		if(($id = $event->getItem()->getID()) == $this->id){
			$player = $event->getPlayer();
			$name = $player->getName();
			if(!$player->isOP()) return true;
			if(!isset($this->sessions[$name][0])){
				$pos = $event->getBlock()->asVector3();
				$this->sessions[$name][0] = $pos;
				$player->sendMessage("[WEdit_Auto] POS1が設定されました。: $pos->x, $pos->y, $pos->z");
				
				if(isset($this->sessions[$name][1])){
					$ms = $this->countBlocks($player);
					$player->sendMessage("(計".$ms."ブロック)");
				}
				$event->setCancelled();
			}
		}
		return true;
	}

	public function Place(BlockPlaceEvent $event){//2
		$id = $event->getItem()->getID();
		if($id == $this->id){
			$player = $event->getPlayer();
			$name = $player->getName();
			if(!$player->isOP()) return true;
			if(!isset($this->sessions[$name][1])){
				$pos = $event->getBlock()->asVector3();
				$this->sessions[$name][1] = $pos;
				$player->sendMessage("[WEdit_Auto] POS2が設定されました。: $pos->x, $pos->y, $pos->z");
				
				if(isset($this->sessions[$name][0])){
					$ms = $this->countBlocks($player);
					$player->sendMessage("(計".$ms."ブロック)");
				}
				$event->setCancelled();
 			}
		}
		return true;
	}
	public function tap(PlayerInteractEvent $event){
		if($event->getPlayer()->getInventory()->getItemInHand()->getCustomName() === "§eAuto_WEditor"){
			$player = $event->getPlayer();
			$name = $player->getName();
			$item = $player->getInventory()->getItemInHand();
			$Lore = $item->getLore();
			//var_dump($event->getBlock()->getSide($event->getFace(),1));
			if(!$player->isOP()) return true;
			if(isset($Lore[0])&&$Lore[0] === "[autowe]"){
				if($player->isSneaking()){
					$next = $Lore[2];
					++$next;
					if($next == 4){
						$next = 0;
					}
					$Lore[2] = "".$next;
					$item->setLore($Lore);
					$player->getInventory()->setItemInHand($item);
					$this->test($event->getBlock()->getLevel(),$event->getBlock()->getSide($event->getFace(),1),$next);
					$player->sendMessage("§6".$next);
				}else{
					$this->pastepp($player,$event->getBlock()->getSide($event->getFace(),1),$Lore[2],$Lore[1]);
				}
			}
		}
	}

	public function countBlocks($player){
		if($player == null){
			$name = CONSOLE;
		}else{
			$name = $player->getName();
		}
		if(isset($this->sessions[$name][0]) and isset($this->sessions[$name][1])){
			$pos = $this->sessions[$name];
			$sx = min($pos[0]->x, $pos[1]->x);
			$sy = min($pos[0]->y, $pos[1]->y);
			$sz = min($pos[0]->z, $pos[1]->z);
			$ex = max($pos[0]->x, $pos[1]->x);
			$ey = max($pos[0]->y, $pos[1]->y);
			$ez = max($pos[0]->z, $pos[1]->z);
			$num = ($ex - $sx + 1) * ($ey - $sy +1) * ($ez - $sz + 1);
			if($num < 0) $num * -1;
			return $num;
		}else{
			return false;
		}
	}
	/*
	┃
	┗━

	┏━
	┃

	━┓
	    ┃

	    ┃
	━┛

┗ 1
┏ 2
┓3

┛4
	
+
┃$x+1
-

$z-1 ━ $z+1
	*/
	public function test($level,$pos,$side){
		$tmppos = new Vector3($pos->x,$pos->y+2,$pos->z);
		switch((int) $side){
			case 0:
				//return new Vector3($this->x, $this->y, $this->z - $step);
				//$x+1 $z+1
				$ex = $pos->x+20;
				$ez = $pos->z+20;
				for($x = $pos->x; $x <= $ex; ++$x) {
					for($z = $pos->z; $z <= $ez; ++$z) {
						$tmppos->x = $x;
						$tmppos->z = $z;
						$particle = new DustParticle($tmppos, 255, 255, 0);
						$level->addParticle($particle);
					}
				}
				break;
			case 1:
				//return new Vector3($this->x, $this->y, $this->z + $step);
				//$x-1 $z+1
				$ex = $pos->x-20;
				$ez = $pos->z+20;
				for($x = $pos->x; $x >= $ex; --$x) {
					for($z = $pos->z; $z <= $ez; ++$z) {
						$tmppos->x =  $x;
						$tmppos->z =  $z;
						$particle = new DustParticle($tmppos, 255, 255, 0);
						$level->addParticle($particle);
					}
				}
				break;
			case 2:
				//return new Vector3($this->x - $step, $this->y, $this->z);
				//$x-1 $z-1
				$ex = $pos->x-20;
				$ez = $pos->z-20;
				for($x = $pos->x; $x >= $ex; --$x) {
					for($z = $pos->z; $z >= $ez; --$z) {
						$tmppos->x = $x;
						$tmppos->z = $z;
						$particle = new DustParticle($tmppos, 255, 255, 0);
						$level->addParticle($particle);
					}
				}
				break;
			case 3:
				//return new Vector3($this->x + $step, $this->y, $this->z);
				//$x+1 $z-1
				$ex = $pos->x+20;
				$ez = $pos->z-20;
				for($x = $pos->x; $x <= $ex; ++$x) {
					for($z = $pos->z; $z >= $ez; --$z) {
						$tmppos->x = $x;
						$tmppos->z = $z;
						$particle = new DustParticle($tmppos, 255, 255, 0);
						$level->addParticle($particle);
					}
				}
			break;
		}
	}
	public function pastepp($player,$pos,$side,$id){
		if(!isset($this->datas[$id])){
			return true;
			$player->sendMessage("§cそのidのデータは存在しません。");
		}
		$level = $player->getLevel();
		$name = $player->getName();
		$tmppos = $pos->asVector3();
			$pos1 = $this->datas[$id][1];
			$sx = min($pos1[0]->x, $pos1[1]->x);
			$sy = min($pos1[0]->y, $pos1[1]->y);
			$sz = min($pos1[0]->z, $pos1[1]->z);
			$ex = max($pos1[0]->x, $pos1[1]->x);
			$ey = max($pos1[0]->y, $pos1[1]->y);
			$ez = max($pos1[0]->z, $pos1[1]->z);
			$num = ($ex - $sx + 1) * ($ey - $sy +1) * ($ez - $sz + 1);

			Server::getInstance()->broadcastMessage("[WEdit_Auto] ".$name."が変更を開始します…(set_fast $id : ".$num."ブロック)");

			$level = $player->getLevel();

			$data = array();
			//$vector = new Vector3(0, 0, 0);
			$data = $this->datas[$id][0];
			$undo = [];
			$count = 0;
		switch((int) $side){
			case 0:
				//return new Vector3($this->x, $this->y, $this->z - $step);
				//$x+1 $z+1
				for($x = $sx; $x <= $ex; ++$x){
					for($y = $sy; $y <= $ey; ++$y){
						for($z = $sz; $z <= $ez; ++$z){
							$tmppos->x = $pos->x-($sx-$x);
							$tmppos->y = $pos->y-($sy-$y);
							$tmppos->z = $pos->z-($sz-$z);
							$level->setBlock($tmppos,$data[++$count],false,false);
							$undo[] = array($tmppos->x, $tmppos->y, $tmppos->z, $data[$count], $level->getBlockDataAt($x, $y ,$z));
						}
					}
				}
				break;
			case 1:
				//return new Vector3($this->x, $this->y, $this->z + $step);
				//$x-1 $z+1
				for($x = $sx; $x <= $ex; ++$x){
					for($y = $sy; $y <= $ey; ++$y){
						for($z = $sz; $z <= $ez; ++$z){
							$tmppos->x = $pos->x+($sx-$x);
							$tmppos->y = $pos->y-($sy-$y);
							$tmppos->z = $pos->z-($sz-$z);
							$level->setBlock($tmppos,$data[++$count],false,false);
							$undo[] = array($tmppos->x, $tmppos->y, $tmppos->z, $data[$count], $level->getBlockDataAt($x, $y ,$z));
						}
					}
				}
				break;
			case 2:
				//return new Vector3($this->x - $step, $this->y, $this->z);
				//$x-1 $z-1
				for($x = $sx; $x <= $ex; ++$x){
					for($y = $sy; $y <= $ey; ++$y){
						for($z = $sz; $z <= $ez; ++$z){
							$tmppos->x = $pos->x+($sx-$x);
							$tmppos->y = $pos->y-($sy-$y);
							$tmppos->z = $pos->z+($sz-$z);
							$level->setBlock($tmppos,$data[++$count],false,false);
							$undo[] = array($tmppos->x, $tmppos->y, $tmppos->z, $data[$count], $level->getBlockDataAt($x, $y ,$z));
						}
					}
				}
				break;
			case 3:
				//return new Vector3($this->x + $step, $this->y, $this->z);
				//$x+1 $z-1
				for($x = $sx; $x <= $ex; ++$x){
					for($y = $sy; $y <= $ey; ++$y){
						for($z = $sz; $z <= $ez; ++$z){
							$tmppos->x = $pos->x-($sx-$x);
							$tmppos->y = $pos->y-($sy-$y);
							$tmppos->z = $pos->z+($sz-$z);
							$level->setBlock($tmppos,$data[++$count],false,false);
							$undo[] = array($tmppos->x, $tmppos->y, $tmppos->z, $data[$count], $level->getBlockDataAt($x, $y ,$z));
						}
					}
				}
			break;
		}
		$this->sessions[$name][3] = $undo;
		Server::getInstance()->broadcastMessage("[WEdit_Auto] 変更が終了しました。");
	}
	public function undo($player){
		$name = $player->getName();
		if(isset($this->sessions[$name][3])){
			$data = $this->sessions[$name][3];
			$num = count($data);
			Server::getInstance()->broadcastMessage("[WEdit_Auto] ".$name."が変更を開始します…(undo : ".$num."ブロック)");

			$level = $player->getLevel();
			foreach($data as $b){
				$block = Block::get($b[3], $b[4]);
				$posi = new Vector3($b[0], $b[1], $b[2]);
				$level->setBlock($posi, $block);
			}
			unset($this->sessions[$name][3]);
			Server::getInstance()->broadcastMessage("[WEdit_Auto] 変更が終了しました。");
		}else{
			$player->sendMessage("[WEdit_Auto] ERROR: やり直し出来ません。");
		}
	}
}

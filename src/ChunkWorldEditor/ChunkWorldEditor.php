<?php

/*
License

The MIT License (MIT)

Copyright (c) 2017 Falkirks

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

namespace ChunkWorldEditor;

use pocketmine\Server;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\math\Vector3;
use pocketmine\entity\Entity;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\item\Item;

use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;

use pocketmine\scheduler\Task;

use pocketmine\level\format\Chunk;
use pocketmine\scheduler\AsyncTask;

class ChunkWorldEditor extends PluginBase implements Listener{
	public $id = 41;
	public $sessions = [];
	public $MaxThread = 7;
	public $pool;

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function BlockBreak(BlockBreakEvent $event){//1
		if(($id = $event->getItem()->getID()) == $this->id){
			$player = $event->getPlayer();
			$name = $player->getName();
			if(!$player->isOP()) return true;
			if(!isset($this->sessions[$name][0])){
				$pos = $event->getBlock()->asVector3();
				$this->sessions[$name][0] = $pos;
				$player->sendMessage("[WorldEditor_Plus] POS1が設定されました。: $pos->x, $pos->y, $pos->z");
				
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
		if($event->getItem()->getID() == $this->id){
			$player = $event->getPlayer();
			$name = $player->getName();
			if(!$player->isOP()) return true;
			if(!isset($this->sessions[$name][1])){
				$pos = $event->getBlock()->asVector3();
				$this->sessions[$name][1] = $pos;
				$player->sendMessage("[WorldEditor_Plus] POS2が設定されました。: $pos->x, $pos->y, $pos->z");
				
				if(isset($this->sessions[$name][0])){
					$ms = $this->countBlocks($player);
					$player->sendMessage("(計".$ms."ブロック)");
				}
				$event->setCancelled();
 			}
		}
		return true;
	}

	public function divide($sz,$ez,$thread,$c1 = 1){
		$array = [];
		$count = (int) (($ez - $sz) / $thread);
		$remainder = ($ez - $sz) % $thread;
		for($z = $sz;$z + $remainder <= $ez; $z = $z + $count){
			if($z !== $sz&&$remainder !== 0){
				$z++;
				$remainder--;
			}
			$array[] = $z;
		}
		$count2 = count($array)-1;
		$count3 = count($array)-2;
		$max = $array[$count2];
		for($i = 1; $i <= $count3; $i++){
			$return = $this->roundUpToAny($array[$i],16);
			if($return < $max){//
				$array[$i] = (int) $return - $c1;
			}else{
				$array[$i] = (int) $max;
			}
		}
		$oldarray = $array;
		for($i = 1; $i <= $count2; $i++){
			if($oldarray[$i-1] == $array[$i]){
				unset($array[$i]);
			}
		}
		$array = array_values($array);
		return $array;
	}

	public function roundUpToAny($n,$x=5) {
		return (ceil($n)%$x === 0) ? ceil($n) : round(($n+$x/2)/$x)*$x;
	}

	public function setppp($player,$id,$thread = 4){//int $thread
		$name = $player->getName();
		if(isset($this->sessions[$name][0]) and isset($this->sessions[$name][1])){
			$sx = min($this->sessions[$name][0]->x, $this->sessions[$name][1]->x);
			$sy = min($this->sessions[$name][0]->y, $this->sessions[$name][1]->y);
			$sz = min($this->sessions[$name][0]->z, $this->sessions[$name][1]->z);
			$ex = max($this->sessions[$name][0]->x, $this->sessions[$name][1]->x);
			$ey = max($this->sessions[$name][0]->y, $this->sessions[$name][1]->y);
			$ez = max($this->sessions[$name][0]->z, $this->sessions[$name][1]->z);
			
			if(abs($ex-$sx) >= $thread){
				if($ex-$sx > 0){
					$array = $this->divide($sx,$ex,$thread);
				}else{
					$array = $this->divide($ex,$sx,$thread);
				}
				$current = $array[0];
				$count1 = count($array)-1;
				for($i = 1; $i <= $count1; $i++){
					if($i === 1){
						$this->setppp_1($player,$id,$current,$sy,$sz,$array[$i],$ey,$ez,$i);
					}else{
						$this->setppp_1($player,$id,$current + 1,$sy,$sz,$array[$i],$ey,$ez,$i);
					}
					$current = $array[$i];
				}
			}else if(abs($ez-$sz) >= $thread){
				if($ez-$sz > 0){
					$array = $this->divide($sz,$ez,$thread);
				}else{
					$array = $this->divide($ez,$sz,$thread);
				}
				$current = $array[0];//
				$count1 = count($array)-1;
				for($i = 1; $i <= $count1; $i++){
					if($i === 1){
						$this->setppp_1($player,$id,$sx,$sy,$current,$ex,$ey,$array[$i] ,$i);//
					}else{
						$this->setppp_1($player,$id,$sx,$sy,$current + 1,$ex,$ey,$array[$i] ,$i);//
					}
					$current = $array[$i];
				}
			}else{
				$player->sendMessage("x軸の合計及び、y軸の合計は、スレッド数(現在: ".$thread."スレッドです...)よりも一致または、多くする必要があります。");
				$player->sendMessage("代わりと致しましては、「/////set」 又は「/////setpp」を利用して頂きたいです...");
			}
		}
	}

	public function setppp_1($player,$id,$sx1,$sy1,$sz1,$ex1,$ey1,$ez1,$Thread_id = -1){
		$name = $player->getName();
		if(isset($this->sessions[$name][0]) and isset($this->sessions[$name][1])){
			$sx = min($sx1, $ex1);
			$sy = min($sy1, $ey1);
			$sz = min($sz1, $ez1);
			$ex = max($sx1, $ex1);
			$ey = max($sy1, $ey1);
			$ez = max($sz1, $ez1);
			
			$did = explode(":", $id);
			$id = 0;
			$damage = 0;
			if(isset($did[0])){
				$id = (int) $did[0];
			}
			if(isset($did[1])){
				$damage = (int) $did[1];
			}
			$num = ($ex - $sx + 1) * ($ey - $sy +1) * ($ez - $sz + 1);
			if($Thread_id == -1){
				Server::getInstance()->broadcastMessage("[WorldEditor_Plus][1/2] ".$name."が変更を開始します…(Async_set) : ".$num."ブロック)");
			}else{
				Server::getInstance()->broadcastMessage("[WorldEditor_Plus][#".$Thread_id."][1/2] ".$name."が変更を開始します…(Async_set) : ".$num."ブロック)");
			}
			$level = $player->getLevel();
			$chunks = [];
			for($x = $sx; $x - 16 <= $ex; $x += 16){
				for($z = $sz; $z - 16 <= $ez; $z += 16){
					$chunk = $level->getChunk($x >> 4, $z >> 4, true);
					$chunks[Level::chunkHash($x >> 4, $z >> 4)] = $chunk->fastSerialize();
				}
			}
			$pos1 = [$sx,$sy,$sz];
			$pos2 = [$ex,$ey,$ez];
			
			$AsyncTask = new setAsyncTaskpp($chunks,$pos1,$pos2,$id,$damage,$player->getLevel()->getName(),$Thread_id);
			$this->getServer()->getAsyncPool()->submitTask($AsyncTask);
		}else{
			$player->sendMessage("[WEdit] ERROR: POS1とPOS2が指定されていません。\n[WEdit] //helpを打ち、使い方を読んでください。");
		}
	}

	public function setpp($player,$id){
		$name = $player->getName();
		if(isset($this->sessions[$name][0]) and isset($this->sessions[$name][1])){
			$did = explode(":", $id);
			$id = 0;
			$damage = 0;
			if(isset($did[0])){
				$id = (int) $did[0];
			}
			if(isset($did[1])){
				$damage = (int) $did[1];
			}
			$sx = min($this->sessions[$name][0]->x, $this->sessions[$name][1]->x);
			$sy = min($this->sessions[$name][0]->y, $this->sessions[$name][1]->y);
			$sz = min($this->sessions[$name][0]->z, $this->sessions[$name][1]->z);
			$ex = max($this->sessions[$name][0]->x, $this->sessions[$name][1]->x);
			$ey = max($this->sessions[$name][0]->y, $this->sessions[$name][1]->y);
			$ez = max($this->sessions[$name][0]->z, $this->sessions[$name][1]->z);
			$num = ($ex - $sx + 1) * ($ey - $sy +1) * ($ez - $sz + 1);
			Server::getInstance()->broadcastMessage("[WorldEditor_Plus][1/2] ".$name."が変更を開始します…(Async_set) : ".$num."ブロック)");
			$level = $player->getLevel();
			$chunks = [];
			for($x = $sx; $x - 16 <= $ex; $x += 16){
				for($z = $sz; $z - 16 <= $ez; $z += 16){
					$chunk = $level->getChunk($x >> 4, $z >> 4, true);
					$chunks[Level::chunkHash($x >> 4, $z >> 4)] = $chunk->fastSerialize();
				}
			}
			$pos1 = [$sx,$sy,$sz];
			$pos2 = [$ex,$ey,$ez];
			$AsyncTask = new setAsyncTask($chunks,$pos1,$pos2,$id,$damage,$player->getLevel()->getName());
			$this->getServer()->getAsyncPool()->submitTask($AsyncTask);
		}else{
			$player->sendMessage("[WEdit] ERROR: POS1とPOS2が指定されていません。\n[WEdit] //helpを打ち、使い方を読んでください。");
		}
	}

	public function set($player,$id){
		$name = $player->getName();
		if(isset($this->sessions[$name][0]) and isset($this->sessions[$name][1])){
			$did = explode(":", $id);
			$id = 0;
			$damage = 0;
			if(isset($did[0])){
				$id = (int) $did[0];
			}
			if(isset($did[1])){
				$damage = (int) $did[1];
			}
			$sx = min($this->sessions[$name][0]->x, $this->sessions[$name][1]->x);
			$sy = min($this->sessions[$name][0]->y, $this->sessions[$name][1]->y);
			$sz = min($this->sessions[$name][0]->z, $this->sessions[$name][1]->z);
			$ex = max($this->sessions[$name][0]->x, $this->sessions[$name][1]->x);
			$ey = max($this->sessions[$name][0]->y, $this->sessions[$name][1]->y);
			$ez = max($this->sessions[$name][0]->z, $this->sessions[$name][1]->z);
			$num = ($ex - $sx + 1) * ($ey - $sy +1) * ($ez - $sz + 1);
			Server::getInstance()->broadcastMessage("[WorldEditor_Plus] ".$name."が変更を開始します…(chunk_set) : ".$num."ブロック)");
			$level = $player->getLevel();
			$chunks = [];
			for($x = $sx; $x - 16 <= $ex; $x += 16){
				for($z = $sz; $z - 16 <= $ez; $z += 16){
					$chunk = $level->getChunk($x >> 4, $z >> 4, true);
					$chunks[Level::chunkHash($x >> 4, $z >> 4)] = $chunk;
				}
			}
			$currentProgress = null;

			$currentChunkX = $sx >> 4;
			$currentChunkZ = $sy >> 4;
			$currentChunkY = $sz >> 4;

			$currentChunk = null;
			$currentSubChunk = null;
			for($x = $sx; $x <= $ex; ++$x){
				$chunkX = $x >> 4;
				for($z = $sz; $z <= $ez; ++$z){
					$chunkZ = $z >> 4;
					if($currentChunk === null or $chunkX !== $currentChunkX or $chunkZ !== $currentChunkZ){
						$currentChunkX = $chunkX;
						$currentChunkZ = $chunkZ;
						$currentSubChunk = null;
						$hash = Level::chunkHash($chunkX, $chunkZ);
						$currentChunk = $chunks[$hash];
						if($currentChunk === null){
							continue;
						}
					}
					for($y = $sy; $y <= $ey; ++$y){
						$chunkY = $y >> 4;
              						if($currentSubChunk === null or $chunkY !== $currentChunkY){
							$currentChunkY = $chunkY;
							$currentSubChunk = $currentChunk->getSubChunk($chunkY, true);
							if($currentSubChunk === null){
								continue;
							}
						}
						$currentSubChunk->setBlock($x & 0x0f, $y & 0x0f, $z & 0x0f, $id & 0xff, $damage & 0xff);
					}
				}
			}
			foreach($chunks as $hash => $chunk){
				Level::getXZ($hash, $x, $z);
				$level->setChunk($x, $z, $chunk, false);
			}
			Server::getInstance()->broadcastMessage("[WorldEditor_Plus] 変更が終了しました。");
		}else{
			$player->sendMessage("[WEdit] ERROR: POS1とPOS2が指定されていません。\n[WEdit] //helpを打ち、使い方を読んでください。");
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if($label === "////set"){
			if(!$sender->isOP()) return true;
			if(isset($args[0])){
				$this->set($sender,$args[0]);
			}
			return true;
		}else if($label === "////setpp"){
			if(!$sender->isOP()) return true;
			if(isset($args[0])){
				$this->setpp($sender,$args[0]);
			}
			return true;
		}else if($label === "////setppp"){
			if(!$sender->isOP()) return true;
			if(isset($args[1])){
				if(!$this->is_natural($args[1])){
					$sender->sendMessage("スレッド数は正数ある必要があります。");
					return true;
				}
				if($this->MaxThread < $args[1]){
					$sender->sendMessage("最大スレッド数(現在: ".$this->MaxThread."スレッド)を越えているため、使用することは出来ません。");
					return true;
				}
				$this->setppp($sender,$args[0],(int) $args[1]);
			}else if(isset($args[0])){
				$this->setppp($sender,$args[0]);
			}
			return true;
		}else if($label == "////e"){
			if(!$sender->isOP()) return true;
			$name = $sender->getName();
			if(isset($args[0])){
				if($args[0] == "0"){
					unset($this->sessions[$name][0]);
					$sender->sendMessage("[WorldEditor_Plus] POS1は削除されました。");
					return true;
				}else if($args[0] == "1"){
					unset($this->sessions[$name][1]);
					$sender->sendMessage("[WorldEditor_Plus] POS2は削除されました。");
					return true;
				}
			}
			unset($this->sessions[$name]);
			$sender->sendMessage("[WorldEditor_Plus] 座標データは削除されました。");
			return true;
		}
		return true;
	}
	public function countBlocks($player){
		if($player == null){
			$name = CONSOLE;
		}else{
			$name = $player->getName();
		}
		if(isset($this->sessions[$name][0]) and isset($this->sessions[$name][1])){
			$pos = $this->sessions[$name];
			$sx = min($this->sessions[$name][0]->x, $this->sessions[$name][1]->x);
			$sy = min($this->sessions[$name][0]->y, $this->sessions[$name][1]->y);
			$sz = min($this->sessions[$name][0]->z, $this->sessions[$name][1]->z);
			$ex = max($this->sessions[$name][0]->x, $this->sessions[$name][1]->x);
			$ey = max($this->sessions[$name][0]->y, $this->sessions[$name][1]->y);
			$ez = max($this->sessions[$name][0]->z, $this->sessions[$name][1]->z);
			$num = ($ex - $sx + 1) * ($ey - $sy +1) * ($ez - $sz + 1);
			if($num < 0) $num * -1;
			return $num;
		}else{
			return false;
		}
	}

	public function is_natural($val){
		return (bool) preg_match('/\A[1-9][0-9]*\z/', $val);
	}
}

class setAsyncTask extends AsyncTask{
	public $chunks;
	public $pos1;
	public $pos2;
	public $id;
	public $damage;
	public $LevelName;
	public $Thread_id;

	public function __construct(array $chunks,array $pos1,array $pos2,int $id,int $damage,String $LevelName,int $Thread_id = -1){
		$this->chunks = serialize($chunks);
		$this->pos1 = serialize($pos1);
		$this->pos2 = serialize($pos2);
		$this->id = $id;
		$this->damage = $damage;
		$this->LevelName = $LevelName;
		$this->Thread_id = $Thread_id;
	}

	public function onRun(){
		$pos1 = unserialize($this->pos1);
		$pos2 = unserialize($this->pos2);
		$chunks = unserialize($this->chunks);
		foreach($chunks as $hash => $binary){
			$chunks[$hash] = \pocketmine\level\format\Chunk::fastDeserialize($binary);
		}
		$sx = $pos1[0];
		$sy = $pos1[1];
		$sz = $pos1[2];

		$ex = $pos2[0];
		$ey = $pos2[1];
		$ez = $pos2[2];

		$num = ($ex - $sx + 1) * ($ey - $sy +1) * ($ez - $sz + 1);
		$now = 0;

		$id = $this->id;
		$damage = $this->damage;

		$currentProgress = null;

		$currentChunkX = $sx >> 4;
		$currentChunkZ = $sy >> 4;
		$currentChunkY = $sz >> 4;

		$currentChunk = null;
		$currentSubChunk = null;
		for($x = $sx; $x <= $ex; ++$x){
			$chunkX = $x >> 4;
				for($z = $sz; $z <= $ez; ++$z){
				$chunkZ = $z >> 4;
				if($currentChunk === null or $chunkX !== $currentChunkX or $chunkZ !== $currentChunkZ){
					$currentChunkX = $chunkX;
					$currentChunkZ = $chunkZ;
					$currentSubChunk = null;
					$hash = Level::chunkHash($chunkX, $chunkZ);
					$currentChunk = $chunks[$hash];
					if($currentChunk === null){
						continue;
					}
				}
				for($y = $sy; $y <= $ey; ++$y){
					 $chunkY = $y >> 4;
              					if($currentSubChunk === null or $chunkY !== $currentChunkY){
						$currentChunkY = $chunkY;
						$currentSubChunk = $currentChunk->getSubChunk($chunkY, true);
						if($currentSubChunk === null){
							continue;
						}
					}
					$currentSubChunk->setBlock($x & 0x0f, $y & 0x0f, $z & 0x0f, $id & 0xff, $damage & 0xff);
				}
			}
		}
		$this->setResult($chunks);
	}

	public function onCompletion(Server $server){
		$Thread_id = $this->Thread_id;
		$label = "";
		if($Thread_id !== -1){
			$label .= "[#".$Thread_id."]";
		}
		Server::getInstance()->broadcastMessage("[WorldEditor_Plus]".$label."[1/2] 1つめの変更が終了しました。");
		Server::getInstance()->broadcastMessage("[WorldEditor_Plus]".$label."[2/2] 2つめの変更を開始します...");
		$chunks = $this->getResult();
		$level = $server->getLevelByName($this->LevelName);
		foreach($chunks as $hash => $chunk){
			Level::getXZ($hash, $x, $z);
			$level->setChunk($x, $z, $chunk, false);
		}
		Server::getInstance()->broadcastMessage("[WorldEditor_Plus]".$label."[2/2] 2つめの変更が終了しました。");
	}
}

class setAsyncTaskpp extends AsyncTask{
	public $chunks;
	public $chunks_result;
	public $pos1;
	public $pos2;
	public $id;
	public $damage;
	public $LevelName;
	public $Thread_id;
	public $changed;
	public function __construct(array $chunks,array $pos1,array $pos2,int $id,int $damage,String $LevelName,int $Thread_id = -1){
		$this->chunks = serialize($chunks);
		$this->pos1 = serialize($pos1);
		$this->pos2 = serialize($pos2);
		$this->id = $id;
		$this->damage = $damage;
		$this->LevelName = $LevelName;
		$this->Thread_id = $Thread_id;
	}

	public function onRun(){
		$pos1 = unserialize($this->pos1);
		$pos2 = unserialize($this->pos2);
		$chunks = unserialize($this->chunks);

		unset($this->chunks);
		$changed = [];
		foreach($chunks as $hash => $binary){
			$chunks[$hash] = \pocketmine\level\format\Chunk::fastDeserialize($binary);
		}
		$sx = $pos1[0];
		$sy = $pos1[1];
		$sz = $pos1[2];

		$ex = $pos2[0];
		$ey = $pos2[1];
		$ez = $pos2[2];

		$num = ($ex - $sx + 1) * ($ey - $sy +1) * ($ez - $sz + 1);
		$now = 0;

		$id = $this->id;
		$damage = $this->damage;

		$currentProgress = null;

		$currentChunkX = $sx >> 4;
		$currentChunkZ = $sy >> 4;
		$currentChunkY = $sz >> 4;

		$currentChunk = null;
		$currentSubChunk = null;
		for($x = $sx; $x <= $ex; ++$x){
			$chunkX = $x >> 4;
				for($z = $sz; $z <= $ez; ++$z){
				$chunkZ = $z >> 4;
				if($currentChunk === null or $chunkX !== $currentChunkX or $chunkZ !== $currentChunkZ){
					$currentChunkX = $chunkX;
					$currentChunkZ = $chunkZ;
					$currentSubChunk = null;
					$hash = Level::chunkHash($chunkX, $chunkZ);
					$currentChunk = $chunks[$hash];
					$changed[$hash] = true;
					if($currentChunk === null){
						continue;
					}
				}
				for($y = $sy; $y <= $ey; ++$y){
					 $chunkY = $y >> 4;
              					if($currentSubChunk === null or $chunkY !== $currentChunkY){
						$currentChunkY = $chunkY;
						$currentSubChunk = $currentChunk->getSubChunk($chunkY, true);
						if($currentSubChunk === null){
							continue;
						}
					}
					$currentSubChunk->setBlock($x & 0x0f, $y & 0x0f, $z & 0x0f, $id & 0xff, $damage & 0xff);
					//++$now;
					/*$Progress = round(($now / $num) * 100) . "%";
					if($currentProgress === null or $Progress !== $currentProgress){
						$currentProgress = $Progress;
						var_dump($Progress);
					}*/
				}
			}
		}
		//$this->setResult($chunks);
		$this->chunks_result = serialize($chunks);
		unset($chunks);
		$this->changed = serialize($changed);
		unset($changed);
	}

	public function onCompletion(Server $server){
		$Thread_id = $this->Thread_id;
		$label = "";
		if($Thread_id !== -1){
			$label .= "[#".$Thread_id."]";
		}
		Server::getInstance()->broadcastMessage("[WorldEditor_Plus]".$label."[1/2] 1つめの変更が終了しました。");
		Server::getInstance()->broadcastMessage("[WorldEditor_Plus]".$label."[2/2] 2つめの変更を開始します...");
		$chunks = unserialize($this->chunks_result);
		unset($this->chunks);
		$changed = unserialize($this->changed);
		unset($this->changed);
		$level = $server->getLevelByName($this->LevelName);
		foreach($chunks as $hash => $chunk){
			if(isset($changed[$hash])){
				Level::getXZ($hash, $x, $z);
				$level->setChunk($x, $z, $chunk, false);
			}
		}
		Server::getInstance()->broadcastMessage("[WorldEditor_Plus]".$label."[2/2] 2つめの変更が終了しました。");
		unset($chunks);
		unset($changed);

		
	}
}

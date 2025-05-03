<?php
namespace InputHook;

use RuntimeException;
use Throwable;

class KeyBindings {
	private Logger $logger;
	private string $filePath;

	private $keybinds = [];
	private ?int $lastMtime = null;

	public function __construct(
		Logger $logger,
		string $filePath
	){
		$this->logger = $logger;
		$this->filePath = $filePath;
	}

	private function readKeybinds(){
		$data = file_get_contents($this->filePath);
		if($data === false){
			$this->logger->err("Cannot read {$this->filePath}");
		}
		$items = json_decode($data, true);
		if(!is_array($items)){
			$json_err = json_last_error_msg();
			$this->logger->err("Failed to parse {$this->filePath}: {$json_err}");
			return null;
		}
		return $items;
	}

	public function reload(){
		$this->logger->info('Reloading keybinds...');
		try {
			$newBindings = $this->readKeybinds();
			if($newBindings !== null){
				$this->keybinds = $newBindings;
				$this->logger->info('Keybinds reloaded');
			}
		} catch(Throwable $ex){
			$this->logger->err("Failed to reload keybinds: " 
				. get_class($ex) . ' ' 
				. $ex->getMessage() . '(' . $ex->getCode() . ')'
			);
		}
	}

	public function get(int $keycode){
		return $this->keybinds[$keycode] ?? null;
	}

	public function probe(){
		clearstatcache();
		$newMtime = filemtime($this->filePath);
		if($newMtime !== $this->lastMtime){
			$this->logger->info("{$this->filePath} changed, reloading...");
			$this->reload();
		}
	}
}
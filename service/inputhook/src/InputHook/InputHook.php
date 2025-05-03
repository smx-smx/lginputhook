<?php
namespace InputHook;

use InputHook\HookFactory\HookFactoryInterface;

class InputHook {
	private Logger $logger;
	private KeyBindings $bindings;
	private HookFactoryInterface $hookFactory;

	public function __construct(
		Logger $logger,
		HookFactoryInterface $hookFactory,
		KeyBindings $bindings
	){
		$this->logger = $logger;
		$this->bindings = $bindings;
		$this->hookFactory = $hookFactory;
	}

	private function handleExec(array $para){
		$cmd = $para['command'] ?? null;
		if($cmd === null){
			$this->logger->err("missing erquired parameter 'command'");
			return true;
		}

		passthru("{$cmd}&");
		return false;
	}

	private function handleLaunch(array $para){
		$id = $para['id'] ?? null;
		if($id === null){
			$this->logger->err("missing required parameter 'id'");
			return true;
		}

		$payload = json_encode([
			'id' => $id
		]);

		$hProc = proc_open([
			'luna-send',
			'-n', '1',
			'luna://com.webos.applicationManager/launch',
			$payload
		], [
			2 => ['pipe', 'w']
		], $p);
		$exitCode = proc_close($hProc);
		$stderr = stream_get_contents($p[2]);
		if($exitCode !== 0){
			$this->logger->err("luna-send failed with exit-code {$exitCode}: {$stderr}");
		}
		return false;
	}

	private function handleKey(int $keycode, int $state) {
		if ($state != 1) {
			return;
		}
		
		$preventDefault = false;

		$this->logger->info("{$keycode} => {$state}");
		$binding = $this->bindings->get($keycode);
		if($binding !== null){
			return $preventDefault;
		}

		$action = $binding['action'] ?? '';
		switch($action){
			case 'launch':
				$preventDefault = $this->handleLaunch($action);
				break;
			case 'exec':
				$preventDefault = $this->handleExec($action);
				break;
		}

		return $preventDefault;
	}

	private function setup(){
		$hf = $this->hookFactory;
		if ($hf->hasSymbol("lginput_uinput_send_button")) {
			$hf->newHook("int (*)(uinput_info_t*, int, int)", "lginput_uinput_send_button",
				function($orig, $uinput_info, $keyid, $state) {
					$result = $this->handleKey($uinput_info->keybinds[$keyid]->uinput_code, $state);
		
					if ($result['action'] == 'ignore') {
						return 0;
					} else if ($result['action'] == 'replace') {
						$origKeycode = $uinput_info->keybinds[$keyid]->uinput_code;
		
						$uinput_info->keybinds[$keyid]->uinput_code = $result['keycode'];
						$callres = $orig($uinput_info, $keyid, $state);
						$uinput_info->keybinds[$keyid]->uinput_code = $origKeycode;
		
						return $callres;
					} else {
						return $orig($uinput_info, $keyid, $state);
					}
				});
		} else if ($hf->hasSymbol("MICOM_FuncWriteKeyEvent")) {
			// __SINT32 MICOM_FuncWriteKeyEvent(int fd,__UINT16 type,__UINT16 code,__SINT32 value)
			$hf->newHook("int (*)(int, uint16_t, uint16_t, int32_t)", "MICOM_FuncWriteKeyEvent",
				function($orig, $fd, $type, $code, $value) {
					if ($type != 0) {
						$result = $this->handleKey($code, $value);
		
						if ($result['action'] == 'ignore') {
							return 0;
						} else if ($result['action'] == 'replace') {
							return $orig($fd, $type, $result['keycode'], $value);
						} else {
							return $orig($fd, $type, $code, $value);
						}
					} else {
						return $orig($fd, $type, $code, $value);
					}
				});
		} else if($hf->hasSymbol("write")) {
			// Generic write(2) hook
			$hf->newHook("ssize_t (*)(int, input_event_t*, size_t)", "write", function($orig, $fd, $buf, $size) {
				$fdp = readlink("/proc/self/fd/$fd");
				if ($fdp == "/dev/uinput" && $size >= 16 && $buf[0]->type == 1) {
					var_dump($buf);
					$result = $this->handleKey($buf[0]->code, $buf[0]->value);
					if ($result['action'] == 'ignore') {
						return $size;
					} else if ($result['action'] == 'replace') {
						$buf[0]->code = $result['keycode'];
					}
				}
				return $orig($fd, $buf, $size);
			});
		}
	}

	public function run(){
		$this->setup();
		while (true) {
			$this->bindings->probe();
			sleep(1);
		}
	}
}
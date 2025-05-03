<?php
namespace InputHook\HookFactory;

use FFI;
use FFI\CData;

class HookFactoryEzinject extends HookFactoryBase implements HookFactoryInterface {
	private FFI $ffi;

	/**
	 * @var HookHandle[]
	 */
    private array $handles = array();


	public function __construct(){
		parent::__construct();
		$this->ffi = FFI::cdef("
			void *inj_backup_function(void *original_code, size_t *num_saved_bytes, int opcode_bytes_to_restore);
			int inj_replace_function(void *original_fn, void *replacement_fn);

			void *dlopen(const char *filename, int flag);
			void *dlsym(void *handle, const char *symbol);
		");
	}

	private function inj_backup_function(CData $original_code, ?CData $num_saved_bytes, int $opcode_bytes_to_restore): CData {
		/** @var mixed */
		$ffi = $this->ffi;
		return $ffi->inj_backup_function($original_code, $num_saved_bytes, $opcode_bytes_to_restore);
	}

	private function inj_replace_function(CData $original_fn, CData $replacement_fn): int {
		/** @var mixed */
		$ffi = $this->ffi;
		return $ffi->inj_replace_function($original_fn, $replacement_fn);
	}

	public function makePfn(string $type, $closure){
		$fnT = $this->ffi->type($type);
		$arrT = FFI::arrayType($fnT, [1]);
		$arr = FFI::new($arrT);
		$arr[0] = $closure;
		return $arr[0];
	}

	public function newHook(string $type, string $funcName, callable $hook): ?HookHandle {
		$pvCode = $this->getSymbol($funcName);
		if($pvCode == null){
			return null;
		}

		$pvOrig = $this->inj_backup_function($pvCode, NULL, -1);
		$pfnOrig = self::makePfn($type, $pvOrig);

		$handle = new HookHandle($this, $type, $pfnOrig, $hook);
		$this->inj_replace_function($pvCode, $handle->getNativeHandle());

		$this->handles[] = $handle;
		return $handle;
	}

	public function hasSymbol(string $funcName) : bool {
		return $this->getSymbol($funcName) != null;
	}
}

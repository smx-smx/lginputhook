<?php
namespace InputHook\HookFactory;

use Exception;
use FFI;
use FFI\CData;

abstract class HookFactoryBase {
	private FFI $dl;

	private CData $self;

	protected function dlopen(?string $filename, int $flags): CData {
		/** @var mixed */
		$ffi = $this->dl;
		return $ffi->dlopen($filename, $flags);
	}

	protected function dlsym(CData $handle, string $symbol): CData {
		/** @var mixed */
		$ffi = $this->dl;
		return $ffi->dlsym($handle, $symbol);
	}

	public function __construct(){
		$this->dl = FFI::cdef('
		void *dlopen(const char *filename, int flag);
		void *dlsym(void *handle, const char *symbol);
		');
		$this->self = $this->dlopen(NULL, 0x1);
		if($this->self == NULL){
			throw new Exception("dlopen self failed");
		}
	}

	public function getSymbol(string $symbol){
		return $this->dlsym($this->self, $symbol);
	}
}
<?php
namespace InputHook\HookFactory;

use Closure;
use FFI\CData;

class HookHandle {
	private Closure $wrapCb;
	private Closure $hookCb;
	private CData $nat;

	public function __construct(HookFactoryInterface $parent, string $type, CData $pfnOrig, callable $hookCb){
		$this->wrapCb = Closure::fromCallable(function(...$args) use($pfnOrig, $hookCb){
			return $hookCb($pfnOrig, ...$args);
		});
		$this->nat = $parent->makePfn($type, $this->wrapCb);
	}

	public function getNativeHandle(){
		return $this->nat;
	}
}

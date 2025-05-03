<?php
namespace InputHook\HookFactory;

interface HookFactoryInterface {
	function makePfn(string $type, $closure);
	function hasSymbol(string $funcName) : bool;
	function newHook(string $type, string $funcName, callable $hook) : ?HookHandle;
}
<?php
namespace InputHook\HookFactory;

use Exception;
use FFI;
use FFI\CData;
use InputHook\Logger;

class HookFactoryFrida extends HookFactoryBase implements HookFactoryInterface {
	private static bool $initialized = false;

	private FFI $ffi;
	private CData $gum;
	private Logger $logger;

	/**
	 * @var HookHandle[]
	 */
	private array $handles = array();

	private function gum_init(){
		/** @var mixed */
		$ffi = $this->ffi;
		return $ffi->gum_init();
	}

	private function gum_interceptor_obtain(): CData {
		/** @var mixed */
		$ffi = $this->ffi;
		return $ffi->gum_interceptor_obtain();
	}

	private function gum_interceptor_begin_transaction(CData $handle){
		/** @var mixed */
		$ffi = $this->ffi;
		return $ffi->gum_interceptor_begin_transaction($handle);
	}

	private function gum_interceptor_replace(
		CData $handle,
		CData $function_address,
		CData $replacement_function,
		?CData $replacement_data,
		?CData $original_function
	): int {
		/** @var mixed */
		$ffi = $this->ffi;
		return $ffi->gum_interceptor_replace(
			$handle,
			$function_address,
			$replacement_function,
			$replacement_data,
			$original_function);
	}

	private function gum_interceptor_end_transaction(CData $handle){
		/** @var mixed */
		$ffi = $this->ffi;
		return $ffi->gum_interceptor_end_transaction($handle);
	}

	public function __construct(Logger $logger, $structs="") {
		$this->logger = $logger;
		parent::__construct();

		$this->logger->info(get_class());
		$this->ffi = FFI::cdef("
			void gum_init ();
			void *gum_interceptor_obtain();
			void gum_interceptor_begin_transaction(void *handle);
			int gum_interceptor_replace (void *handle,
				void *function_address,
				void *replacement_function,
				void *replacement_data,
				void **original_function);
			void gum_interceptor_end_transaction(void *handle);

			void *dlopen(const char *filename, int flag);
			void *dlsym(void *handle, const char *symbol);
		" . $structs);

		if(!self::$initialized){
			$this->gum_init();
			self::$initialized = true;
		}
		$this->gum = $this->gum_interceptor_obtain();
	}

	/**
	 * converts a closure or an interger into a typed function pointer
	 */
	public function makePfn(string $type, $closure){
		$fnT = $this->ffi->type($type);
		$arrT = FFI::arrayType($fnT, [1]);
		$arr = FFI::new($arrT);
		$arr[0] = $closure;
		return $arr[0];
	}

	public function hasSymbol(string $funcName) : bool {
		return $this->getSymbol($funcName) != null;
	}

	public function newHook(string $type, string $funcName, callable $hook): ?HookHandle {
		logmsg2("newHook");
		$pvCode = $this->getSymbol($funcName);
		logmsg2("pvCode1");
		if($pvCode == null){
			throw new Exception("Symbol $funcName does not exist");
		}
		logmsg2("pvCode2");

		$pfnOrig = $this->makePfn($type, $pvCode);
		logmsg2("pfnOrig");
		$handle = new HookHandle($this, $type, $pfnOrig, $hook);
		logmsg2("handle");

		$pvHook = $handle->getNativeHandle();
		logmsg2("pvHook");
		$this->gum_interceptor_replace($this->gum,
			$pvCode, $pvHook, NULL, NULL);

		logmsg2("handle");
		$this->handles[] = $handle;
		logmsg2("done");
		return $handle;
	}
}
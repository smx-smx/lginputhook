<?php
namespace InputHook;

use ErrorException;
use Throwable;

class Logger {
    /** @var resource|bool */
    private $fh = false;

	/** log to stderr and file */
	private bool $tee = true;

    private function exceptionHandler(Throwable $exception){
		$msg = (''
			. "Unhandled Exception\n"
			. "    Class: " . get_class($exception) . "\n"
			. "     Code: " . $exception->getCode() . "\n"
			. "     File: " . $exception->getFile() . "\n"
			. "     Line: " . $exception->getLine() . "\n"
			. "  Message: " . $exception->getMessage() . "\n"
			. "---- Backtrace ----\n"
			. $exception->getTraceAsString() . "\n"
		);
		$this->err($msg);
    }

    private function errorHandler(
        int $errno, string $errstr,
        ?string $errfile = null, 
        ?int $errline = null
    ){
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting
            return;
        }
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    public function __construct(string $filePath){
        set_error_handler(function(
            int $errno, string $errstr,
            ?string $errfile = null, 
            ?int $errline = null
        ){
            return $this->errorHandler($errno, $errstr, $errfile, $errline);
        });

        set_exception_handler(function(Throwable $exception){
            return $this->exceptionHandler($exception);
        });

		if(file_exists('/etc/timezone')){
			$tz = rtrim(file_get_contents('/etc/timezone'));
			date_default_timezone_set($tz);
		}

        $this->fh = fopen($filePath, 'a');
        if($this->fh === false){
            fwrite(STDERR, "Cannot open logfile \"{$filePath}\" for writing\n");
        }
    }

	private static function formatInvoker(array $invoker){	
		$klass = $invoker['class'] ?? '';
		$func = $invoker['function'] ?? '';
		$file = $invoker['file'] ?? '';

		$parts = [];

		if($file !== ''){
			$line = $invoker['line'] ?? '';
			$parts[] = "{$file}:{$line}";
		}

		if($klass !== ''){
			$parts[] = $klass;
		}
		if($func !== ''){
			$parts[] = $func;
		}

		return implode(' ', $parts);
	}

    private function logMessage(string $tag, string $msg){
		$bt = debug_backtrace(2);
		$invoker = array_pop($bt);
		

		$now = date('d/m/Y:H:i:s');
        
		$s_invoker = ($invoker !== null)
			? self::formatInvoker($invoker)
			: '';

		$line = "{$now} - {$tag} - [{$s_invoker}] - {$msg}\n";
		fwrite($this->fh, $line);
		if($this->tee){
			fwrite(STDOUT, $line);
		}
    }

    public function info(string $msg){
        $this->logMessage('INFO', $msg);
    }

    public function warn(string $msg){
        $this->logMessage('WARN', $msg);
    }

    public function err(string $msg){
        $this->logMessage('ERR', $msg);
    }

    public function __destruct(){
        if($this->fh !== false){
            fclose($this->fh);
        }
    }
}
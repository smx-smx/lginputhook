<?php
namespace InputHook;

class Utils {
	public static function path_combine(string ...$parts){
		return implode(DIRECTORY_SEPARATOR, $parts);
	}
}
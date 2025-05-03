<?php
namespace InputHook;

use RuntimeException;

class ServiceContainer {
	private array $services;

	const SERVICE_LOGGER = 'logger';
	const SERVICE_HOOKFACTORY = 'hookfactory';
	const SERVICE_KEYBINDINGS = 'keybindings';

	public function __construct(){
		$this->services = [];
	}

	public function addService(string $key, $service){
		$this->services[$key] = $service;
	}

	public function getService(string $key){
		return $this->services[$key] ?? null;
	}

	public function requireService(string $key){
		$svc = $this->getService($key);
		if($svc === null){
			throw new RuntimeException("required service \"{$key}\" not found");
		}
	}
}
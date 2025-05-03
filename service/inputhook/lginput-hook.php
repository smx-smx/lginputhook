<?php

use InputHook\HookFactory\HookFactoryFrida;
use InputHook\HookFactory\HookFactoryInterface;
use InputHook\InputHook;
use InputHook\KeyBindings;
use InputHook\Logger;
use InputHook\ServiceContainer;
use InputHook\Utils;

require_once __DIR__ . '/vendor/autoload.php';

function getLogFilePath(){
    $pid = getmypid();
    $programName = rtrim(file_get_contents('/proc/self/comm'));    
    $tmpDir = sys_get_temp_dir();
    return Utils::path_combine($tmpDir, "{$pid}-{$programName}.log");
}

function buildServices(){
    $cfg = parse_ini_file(Utils::path_combine(__DIR__, 'inputhook.ini'), true, INI_SCANNER_TYPED);
    $sect = $cfg['inputhook'] ?? [];
    
    $logPath = getLogFilePath();
    $logger = new Logger($logPath);
    $hookFactory = new HookFactoryFrida($logger, '
    typedef struct {
        int unk1[4];
        int uinput_code;
        int unk2[9];
    } keybind_info_t;
    typedef struct {
        int fd;
        keybind_info_t* keybinds;
    } uinput_info_t;
    typedef struct {
        uint64_t time;
        uint16_t type;
        uint16_t code;
        int32_t value;
    } input_event_t;
    ');

    $services = new ServiceContainer;
    $services->addService(ServiceContainer::SERVICE_LOGGER, $logger);

    $keybinds_path = $sect['keybinds_path']
        ?? '/home/root/.config/lginputhook/keybinds.json';
    
    $services->addService(ServiceContainer::SERVICE_KEYBINDINGS, new KeyBindings($logger, $keybinds_path));
    $services->addService(ServiceContainer::SERVICE_HOOKFACTORY, $hookFactory);
    return $services;
}

$services = buildServices();

/** @var Logger */
$logger = $services->requireService(ServiceContainer::SERVICE_LOGGER);
/** @var KeyBindings */
$keybindings = $services->requireService(ServiceContainer::SERVICE_KEYBINDINGS);
/** @var HookFactoryInterface */
$hookFactory = $services->requireService(ServiceContainer::SERVICE_HOOKFACTORY);

$prog = new InputHook(
    $logger,
    $hookFactory,
    $keybindings
);
$prog->run();

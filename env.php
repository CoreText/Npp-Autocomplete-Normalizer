<?php

define('BASE_DIR', __DIR__);

$fileTemp = BASE_DIR . DIRECTORY_SEPARATOR . 'tmp'  . DIRECTORY_SEPARATOR . 'tmp.xml';
$fileSrc  = BASE_DIR . DIRECTORY_SEPARATOR . 'src'  . DIRECTORY_SEPARATOR . 'php.xml';
$fileDist = BASE_DIR . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'php.xml';

require_once('lib/Logger.php');

// Logger setup
Logger::$write_log = true;
Logger::$log_dir = BASE_DIR . DIRECTORY_SEPARATOR . 'logs';
Logger::$log_file_name = 'logging';
Logger::$log_file_extension = 'log';
Logger::$log_file_append = true;
Logger::$print_log = false;


$xmlTemplate = <<<XML_RENDER
<?xml version="1.0" encoding="UTF-8" ?>
<!--
Generated AutoComplete for Notepad++
Docs https://npp-user-manual.org/docs/auto-completion/
@author Texter CoreText - https://github.com/CoreText/Npp-Autocomplete-Normalizer
@version 1.1
-->
<NotepadPlus>
    <AutoComplete>
        <Environment ignoreCase="no" startFunc="(" stopFunc=")" paramSeparator="," terminal=";" additionalWordChar=""/>
    </AutoComplete>
</NotepadPlus>
XML_RENDER;


// defaults
$autoCompleteEnvironmentAttributes = [
    'ignoreCase'         => 'yes',
    'startFunc'          => '(',
    'stopFunc'           => ')',
    'paramSeparator'     => ',',
    'terminal'           => ';',
    'additionalWordChar' => '',
];

require_once('polyfills.php');

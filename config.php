<?php

$include_path = array();
$include_path[] = CWD;
$include_path[] = CWD . '/src';
$include_path[] = CWD . '/include';
$include_path[] = CWD . '/vendor';
set_include_path(join(PATH_SEPARATOR, $include_path).PATH_SEPARATOR.get_include_path());

?>

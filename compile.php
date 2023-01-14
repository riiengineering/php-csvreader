#!/usr/bin/env php
<?php

$php_header_ended = FALSE;
$php_declares = array();
$php_includes = array();

function fatal(string $s): void {
	fwrite(STDERR, $s);
	exit(1);
}

function php_to_path(string $code, string $file): string {
	# NOTE: this function only does some very very basic PHP parsing that should
	#       be good enough for the average include/require call.

	if (FALSE !== strpos($code, '__DIR__')) {
		$code = preg_replace(
			'/(^|[ \t]*\.|[a-z_]*\()[ \t]*__DIR__[ \t]*(\.[ \t]*|\)|$)/',
			'$1\''.dirname($file).'\'$2',
			$code);
	}

	if (FALSE !== strpos($code, '__FILE__')) {
		$code = preg_replace(
			'/(^|[ \t]*\.|[a-z_]*\()[ \t]*__FILE__[ \t]*(\.[ \t]*|\)|$)/',
			'$1\''.$file.'\'$2',
			$code);
	}

	return eval("return(${code});");
}

function php_declare(string $k, string $v): void {
	global $php_declares, $php_header_ended;

	if (array_key_exists($k, $php_declares)) {
		if ($v === $php_declares[$k]) {
			return;
		} else {
			fatal("${k} is already declared with a different value\n");
		}
	}
	if ($php_header_ended) {
		fatal("declare too late\n");
	}

	$php_declares[$k] = $v;
	printf("declare(%s=%s);\n", $k, $v);
}

function process_file(string $filename): void {
	global $php_header_ended, $php_includes;

	array_push($php_includes, realpath($filename));

	$fh = fopen($filename, 'r');
	if (!$fh) {
		fatal("failed to process ${filename}\n");
	}

	$php = FALSE;
	$ns = '';
	$nsopen = 0;

	// echo "// file:${filename}\n";

	while (FALSE !== ($line = fgets($fh))) {
		$line = rtrim($line, "\n");

		if (!$php) {
			if (preg_match('/<\?php/', $line)) {
				$php = TRUE;
				$line = preg_replace('/^.*?<\?php/', '', $line);
			} else {
				continue;
			}
		}

		if (preg_match('/\?>/', $line)) {
			fatal("mixed source detected\n");
		}

		if (preg_match('/^\s*declare\s*\(/', $line)) {
			while (preg_match('/^\s*declare\s*\((.*?)\)\s*;\s*/', $line, $matches)) {
				call_user_func_array(
					'php_declare',
					preg_split('/=/', $matches[1], 2));
				$line = substr($line, strlen($matches[0]));
			}
		}

		$php_header_ended = TRUE;

		if (preg_match('/namespace\s+([A-Za-z0-9\\\\]+)\s*([;{])\s*/', $line, $matches)) {
			for (; 0 < $nsopen; --$nsopen) echo "}\n";

			if (';' === $matches[2]) {
				$line = substr($line, strlen($matches[0]));
				$ns = $matches[1];
			} else {
				$ns = NULL;
			}
		}

		if (preg_match('/^\s*((?:include|require)(?:_once)?)\s*(\(.*\)|.*)\s*;\s*/', $line, $matches)) {
			$path = $matches[2];
			if ('(' === $path[0] && ')' === $path[-1]) $path = substr($path, 1, strlen($path)-2);

			$path = php_to_path($path, $filename);

			if ('_once' !== substr($matches[1], -4) || !in_array($path, $php_includes)) {
				if (file_exists($path)) {
					for (; 0 < $nsopen; --$nsopen) echo "}\n";
					process_file($path);
				} else {
					fwrite(STDERR, "${path}: file to include not found\n");
					if (0 === strpos($matches[1], 'require')) {
						exit(1);
					}
				}
			}

			$line = substr($line, strlen($matches[0]));
		}

		if (trim($line)) {
			if (!$nsopen && !is_null($ns)) {
				echo $ns ? "namespace ${ns} {\n" : "namespace {\n";
				++$nsopen;
			}
			echo $line."\n";
		}
	}

	for (; 0 < $nsopen; --$nsopen) echo "}\n";

	// echo "// endf:${filename}\n";

	fclose($fh);
}

function main(array $argv): int {
	echo "<?php\n";

	array_shift($argv);  // the compiler script itself
	foreach ($argv as $arg) {
		process_file($arg);
	}

	return 0;
}

exit(main($argv));

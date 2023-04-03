<?php declare(strict_types=1);

namespace riiengineering\csvreader\format;

function _read_file_head($fh, int $size): string {
	$prev_pos = ftell($fh);
	rewind($fh);
	$s = fread($fh, $size);
	if (FALSE !== $prev_pos) {
		fseek($fh, $prev_pos, SEEK_SET);
	}
	if (FALSE === $s) {
		throw new \RuntimeException('file read failed');
	}
	return $s;
}

function detect_column_separator(
		$fh, string $default_separator = ';', string $quote = '"'): string {
	// NOTE: run this function after converting line separators.
	$sep = $default_separator; $ncols = 1;

	$prev_pos = ftell($fh);
	foreach (array(',', ';', "\t") as $s) {
		rewind($fh);
		$line = fgets($fh);
		if (FALSE === $line) continue;

		// quoted columns indicate a correct separator
		if (1 === preg_match("/(^|[$s])[{$quote}][^{$quote}]*[{$quote}]([$s]|\$)/", $line)) {
			$sep = $s;
			break;
		}

		$cols = explode($s, $line);
		if (count($cols) > $ncols) {
			$sep = $s;
			$ncols = count($cols);
		}
	}
	if (FALSE !== $prev_pos) {
		fseek($fh, $prev_pos, SEEK_SET);
	}
	return $sep;
}

function detect_line_separator($fh): string {
	// NOTE: run this function after multi-byte encoding detection and conversion, since one byte of the multi-byte encoding could be an \r/\n leading to incorrect detection.
	// FIXME: what if 1024 is in between \r and \n?
	$s = _read_file_head($fh, 1000);

	$count_lf = substr_count($s, "\n");
	$count_cr = substr_count($s, "\r");

	if ($count_lf > $count_cr)
		return "\n";
	elseif ($count_lf < $count_cr)
		return "\r";
	else
		return "\r\n";
}

function detect_encoding($fh): ?string {
	$s = _read_file_head($fh, 4);

	// check UTF BOMs
	if (0 === substr_compare($s, "\xEF\xBB\xBF", 0, 3)) {
		//$this->data_start = 3;
		return 'UTF-8';
	}
	if (0 === substr_compare($s, "\xFF\xFE", 0, 2)) {
		if (0 === substr_compare($s, "\x00\x00", 2, 2)) {
			//$this->data_start = 4;
			return 'UTF-32LE';
		} else {
			//$this->data_start = 2;
			return 'UTF-16LE';
		}
	}
	if (0 === substr_compare($s, "\xFE\xFF", 0, 2)) {
		//$this->data_start = 2;
		return 'UTF-16BE';
	}
	if (0 === substr_compare($s, "\x00\x00\xFE\xFF", 0, 4)) {
		//$this->data_start = 4;
		return 'UTF-32BE';
	}

	// check UTF multi-byte line feeds/carriage returns
	$s = _read_file_head($fh, 1024);

	$c = "\x0A";  // LF
	$pos = strpos($s, $c);
	if (FALSE === $pos) {
		$c = "\x0D";  // CR
		$pos = strpos($s, $c);
	}

	if (FALSE !== $pos) {
		switch ($pos % 4) {
			// LE
			case 0:
				if (0 === substr_compare($s, "{$c}\x00\x00\x00", $pos, 4)) {
					return 'UTF-32LE';
				}
			case 2:
				if (0 === substr_compare($s, "{$c}\x00", $pos, 2)) {
					return 'UTF-16LE';
				}
				break;

				// BE
			case 3:
				if (0 === substr_compare($s, "\x00\x00\x00{$c}", $pos-3, 4)) {
					return 'UTF-32BE';
				}
			case 1:
				if (0 === substr_compare($s, "\x00{$c}", $pos-1, 2)) {
					return 'UTF-16BE';
				}
				break;
		}
	}

	// detect UTF-8

	// some characters in UTf-8 require more than one byte to be coded, check
	// for incomplete characters in the end of $s and if found, remove them.
	$c = function (string $n): int { $i=0;$m=1<<7;$n=ord($n);do{if($n&$m)++$i;else break;}while($m>>=1);return $i;};
	if (3 < $c($s[-3])) {
		$s = substr($s, 0, -3);
	} elseif (2 < $c($s[-2])) {
		$s = substr($s, 0, -2);
	} elseif (1 < $c($s[-1])) {
		$s = substr($s, 0, -1);
	}

	if (preg_match('//u', $s)) {
		return 'UTF-8';
	}

	return NULL;
}

function guess_encoding($fh, string $line_separator = NULL): ?string {
	switch ($line_separator) {
		case "\n":
			$candidates = array('UTF-8', 'Windows-1252', 'IBM850', 'Macintosh');
			break;
		case "\r\n":
			$candidates = array('Windows-1252', 'IBM850', 'Macintosh');
			break;
		case "\r":
			$candidates = array('Macintosh', 'Windows-1252', 'IBM850');
			break;
		default:
			$candidates = array('UTF-8', 'Windows-1252', 'Macintosh', 'IBM850');
	}

	$have_iconv = extension_loaded('iconv');
	$have_mbstring = extension_loaded('mbstring');

	$s = _read_file_head($fh, 1024);

	foreach ($candidates as $candidate) {
		if ($have_iconv) {
			if (FALSE !== @iconv($candidate, $candidate, $s)) {
				return $candidate;
			} else {
				continue;
			}
		}
		if ($have_mbstring) {
			try {
				if (TRUE === mb_check_encoding($s, $candidate)) {
					return $candidate;
				}
			} catch (\ValueError $e) {}

			continue;
		}
	}

	return NULL;
}

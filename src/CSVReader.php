<?php declare(strict_types=1);

namespace riiengineering\csvreader;

//include_once 	dirname(__FILE__).DIRECTORY_SEPARATOR	.'endings_filter.php';
//require_once  (dirname(__FILE__) . DIRECTORY_SEPARATOR  .	'fieldparser.php');
//require_once __DIR__ . '/format.php';
require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'endings_filter.php');
require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'fieldparser.php');
require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'format.php');


class CSVReader implements \Iterator {
	public const OPTIONS_DEFAULTS = array(
		'encoding' => 'AUTO',
		'line-separator' => 'AUTO',
		'separator' => 'AUTO',
		'fallback-separator' => ';',
		'enclosure' => '"',
		'escape' => '\\',
		'respect-sep-line' => TRUE,
		'require-header-line' => FALSE,
		'column-order-from-header-line' => TRUE,
	);

	private $fh;
	public array $columns;
	private array $options;
	private int $data_start = 0;
	private int $row_start = 0;

	private array $colmap;

	private int $it_row = 0;
	private ?array $it_curr = NULL;

	public function __construct(
			$file,
			?array $columns,
			array $options = array()) {

		$this->options = array_merge(self::OPTIONS_DEFAULTS, $options);

		if (is_string($file)) {
			if (!file_exists($file)) {
				throw new \RuntimeException("${file}: No such file");
			}
			if (!is_readable($file)) {
				throw new \RuntimeException("${file}: Permission denied");
			}

			$this->fh = fopen($file, 'r');
			if (FALSE === $this->fh) {
				throw new \RuntimeException("cannot open ${file}");
			}
		} elseif (is_resource($file)) {
			$this->fh = $file;
		} else {
			throw new \InvalidArgumentException('$file must be either a string or a stream');
		}

		if (is_null($columns) || array() === $columns) {
			// ignore
		} elseif (array_keys($columns) === range(0, count($columns)-1)) {
			$this->columns = array_fill_keys($columns, array());
			$required_columns = $this->columns;
		} else {
			$this->columns = $columns;
			$required_columns = array_filter(
				$this->columns,
				fn($props) => $props['required'] ?? TRUE);

			if (count($required_columns) < count($this->columns)
			    && !$this->options['require-header-line']) {
				// some columns are optional, ensure that the optional columns
				// are trailing, so that the columns can be mapped left-to-right
				// in case the header line is missing
				array_reduce(
					$this->columns,
					function (bool $acc, array $props): bool {
						$req = $props['required'] ?? TRUE;
						if (!$acc && $req) {
							throw new \InvalidArgumentException(
								'$columns contains optional columns mixed with required columns but require-header-line is FALSE. Either require-header-line or make optional columns follow required columns.');
						}
						return $req;
					},
					TRUE);
			}
		}
		$this->check_column_names();

		$this->data_start = ftell($this->fh);
		if (FALSE === $this->data_start) $this->data_start = 0;

		if ('AUTO' === $this->options['encoding']) {
			// detect encoding
			$this->options['encoding'] = format\detect_encoding($this->fh);

			if (!is_null($this->options['encoding'])) {
				$this->reencode($this->options['encoding']);
			}
		}

		if ('AUTO' === $this->options['line-separator']) {
			$this->options['line-separator'] = format\detect_line_separator($this->fh);
			if (is_null($this->options['line-separator'])) {
				throw new \RuntimeException("failed to detect line separator of file ${file}");
			}
		}

		switch ($this->options['line-separator']) {
			case "\n":
				break;
			case "\r\n":
				stream_filter_append($this->fh, 'endings.dos2unix');
				break;
			case "\r":
				stream_filter_append($this->fh, 'endings.mac2unix');
				break;
			default:
				throw new InvalidArgumentException('invalid line separator');
		}

		if (is_null($this->options['encoding'])) {
			$this->options['encoding'] = format\guess_encoding(
				$this->fh, $this->options['line-separator']);

			if (is_null($this->options['encoding'])
			    && !empty($this->options['fallback-encoding'])) {
				$this->options['encoding'] = $this->options['fallback-encoding'];
			}

			// if the encoding is known, reencode file to UTF-8, otherwise try
			// to work with what we have
			if (!is_null($this->options['encoding'])) {
				$this->reencode($this->options['encoding']);
			}
		}

		fseek($this->fh, $this->data_start, SEEK_SET);
		$first_line = fgets($this->fh);

		// process sep= line
		if (FALSE !== $first_line
			&& 0 === substr_compare($first_line, 'sep=', 0, 4)) {
			if ($this->options['respect-sep-line']) {
				$this->options['separator'] = $first_line[4];
			}
			$this->data_start += strlen($first_line);
			$this->row_start++;
		}

		if ('AUTO' === $this->options['separator']) {
			$this->options['separator'] = format\detect_column_separator(
				$this->fh,
				$this->options['fallback-separator'],
				$this->options['enclosure']);
		}

		fseek($this->fh, $this->data_start, SEEK_SET);
		$first_row = $this->getRow();
		$first_num_cols = count($first_row);

		// check if separator makes sense
		$min_columns = (isset($required_columns) ? count($required_columns) : 2);
		if (is_null($first_row) || $first_num_cols < $min_columns/2) {
			throw new \RuntimeException(
				"failed to parse this file; please ensure the columns are separated by '{$this->options['separator']}' characters");
		}

		$has_headerline = FALSE;
		if (!is_numeric($first_row[0][0]) && $this->options['column-order-from-header-line']) {
			// process header
			// assuming header rows are rows not starting with a digit, because
			// column names must not start with digit
			if (!isset($this->columns)) {
				// get $columns from header line
				$this->columns = array_fill_keys(
					array_map('self::colslug', $first_row),
					array());
				$required_columns = $this->columns;
				$this->check_column_names();
			}

			$this->colmap = $this->map_from_headerline($first_row);

			if (count(array_intersect_key($required_columns, $this->colmap)) < count($required_columns)) {
				// not all required columns could be mapped
				if (count($this->colmap) < count($required_columns)/2) {
					// probably not a header
					unset($this->colmap);
				} else {
					throw new \RuntimeException(
						'not all required columns could be mapped from header line');
				}
			} else {
				$this->data_start = ftell($this->fh);
				$this->row_start++;
				$has_headerline = TRUE;
			}
		}

		if (!$has_headerline) {
			if ($this->options['require-header-line']) {
				throw new \RuntimeException(
					'the given input file contains no header line');
			}

			// no header -> rewind
			fseek($this->fh, $this->data_start, SEEK_SET);
		}

		if (!isset($this->colmap) && isset($this->columns)) {
			// "default" colmap from $columns
			$this->colmap = array();
			foreach (array_keys($this->columns) as $i => $k) {
				$this->colmap[$k] = $i;
			}
		}

		if (!isset($this->columns)) {
			if ($this->options['column-order-from-header-line']) {
				throw new RuntimeException(
					'no $columns given and input file is lacking a header line');
			} else {
				throw new RuntimeException(
					'no $columns given and column-order-from-header-line is disabled');
			}
		}
	}

	public function __destruct() {
		fclose($this->fh);
	}

	private function check_column_names(): void {
		if (!isset($this->columns)) return;

		// column names must not start with numbers as this interferes with
		// header detection which assumes that headers don’t start with a
		// number and PHP converts numeric string array keys to integers
		// implicitly.

		$illegal_columns = array_filter(
			$this->columns,
			fn($k) => 1 !== preg_match('/^[a-z_-][a-z0-9_-]*/i', strval($k)),
			ARRAY_FILTER_USE_KEY);
		if (0 < count($illegal_columns)) {
			throw new \InvalidArgumentException(
				'columns specification contains invalid column names: '
				. implode(', ', array_keys($illegal_columns)));
		}
	}

	private function reencode(string $input_encoding): void {
		$output_encoding = 'UTF-8';

		if ($output_encoding === $input_encoding) return;

		// remove LE/BE suffix for iconv not to generate a BOM in the output
		switch (substr($this->options['encoding'], -2)) {
			case 'BE':
			case 'LE':
				$input_encoding = substr($this->options['encoding'], 0, -2);
				break;
			default:
				$input_encoding = $this->options['encoding'];
				break;
		}

		stream_filter_append(
			$this->fh, "convert.iconv.${input_encoding}.${output_encoding}");
	}

	private static function colslug(string $colname): string {
		return strtolower(preg_replace('/[^A-Za-z0-9]/', '', $colname));
	}

	private function map_from_headerline(array $header): array {
		$colunslug = array_combine(
			array_map('self::colslug', array_keys($this->columns)),
			array_keys($this->columns));
		$res = array();

		foreach ($header as $i => $k) {
			$colk = @$colunslug[self::colslug($k)];
			if (is_null($colk)) continue;
			$res[$colk] = $i;
		}

		return $res;
	}

	private function getRow(): ?array {
		$row = fgetcsv(
			$this->fh,
			NULL,
			$this->options['separator'],
			$this->options['enclosure'],
			$this->options['escape']);
		return (FALSE !== $row) ? $row : NULL;
	}


	// public methods

	public function options(): array {
		return $this->options;
	}

	public function currentRow(): ?array {
		return $this->it_curr;
	}
	public function currentRowNumber(): int {
		return $this->row_start + $this->it_row;
	}

	public function columnNameToNumber(string $colname): int {
		if (!array_key_exists($colname, $this->colmap)) {
			throw new \InvalidArgumentException("unknown column: ${colname}");
		}
		return $this->colmap[$colname]+1;
	}

	public function nextRow(): ?array {
		$row = $this->getRow();
		if (is_null($row)) {
			if (!is_null($this->it_curr)) $this->it_row++;
			return ($this->it_curr = NULL);
		}

		$res = array();
		foreach ($this->colmap as $k => $i) {
			if (!array_key_exists($i, $row)) {
				if ($this->columns[$k]['required'] ?? TRUE) {
					$res[$k] = NULL;
				}
				continue;
			}

			$value = $row[$i];

			$coltype = array_key_exists('type', $this->columns[$k])
				? $this->columns[$k]['type']
				 : 'string';
			switch ($coltype) {
				case 'bool':
				case 'boolean':
					$value = fieldparser\parse_boolean($value);
					break;
				case 'float':
				case 'double':
				case 'number':
					$value = fieldparser\parse_number($value);
					if (!is_null($value))
						$value = (float)$value;
					break;
				case 'int':
				case 'integer':
					$value = fieldparser\parse_number($value);
					if (!is_null($value))
						$value = (int)round($value, 0);
					break;
				case 'string':
					break;
				default:
					throw new \InvalidArgumentException(
						"{$this->columns[$k]['type']} is not a valid column type");
			}

			$res[$k] = $value;
		}

		$this->it_row++;
		$this->it_curr = $res;
		return $this->it_curr;
	}


	// Iterator methods
	public function current(): ?array {
		return $this->it_curr;
	}
	public function key(): int {
		return $this->it_row;
	}
	public function next(): void {
		$this->nextRow();
	}
	public function rewind(): void {
		$this->it_row = 0;
		$this->it_curr = NULL;

		fseek($this->fh, $this->data_start, SEEK_SET);

		// fetch first row
		$this->next();
	}
	public function valid(): bool {
		return !is_null($this->it_curr);
	}
}

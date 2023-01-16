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
		'separator' => ',',
		'enclosure' => '"',
		'escape' => '\\',
		'encoding' => 'AUTO',
		'line-separator' => 'AUTO',
		'respect-sep-line' => TRUE,
		'column-order-from-header-line' => TRUE,
	);

	private $fh;
	public array $columns;
	private array $options;
	private int $data_start;

	private array $colmap;

	private int $it_row = 0;
	private ?array $it_curr = NULL;

	public function __construct(
			$file,
			?array $columns,
			array $options = array()) {

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
		} else {
			$this->columns = $columns;
		}

		$this->options = array_merge(self::OPTIONS_DEFAULTS, $options);

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

			if (is_null($this->options['encoding']) && $this->options['fallback-encoding']) {
				$this->options['encoding'] = $this->options['fallback-encoding'];
			}

			$this->reencode($this->options['encoding']);
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
		}

		if ('AUTO' === $this->options['separator']) {
			throw new Exception('not implemented');
		}

		fseek($this->fh, $this->data_start, SEEK_SET);
		$first_row = $this->getRow();

		// check if separator makes sense
		if (is_null($first_row) || 2 > count($first_row)) {
			throw new \RuntimeException(
				"failed to parse this file; please ensure the columns are separated by '{$this->options['separator']}' characters");
		}

		if (!is_numeric($first_row[0][0])) {
			// process header
			// assuming header rows are rows not starting with a number
			if ($this->options['column-order-from-header-line']) {
				if (!isset($this->columns)) {
					// get columns from header line
					$this->columns = array_fill_keys(
						array_map('self::colslug', $first_row),
						array());
				}
				$this->colmap = $this->map_from_headerline($first_row);
				if (count($this->colmap) < count($this->columns)) {
					// not all columns could be mapped
					if (count($this->colmap) < count($this->columns)/2) {
						// probably not a header
						unset($this->colmap);

						// no header -> rewind
						fseek($this->fh, $this->data_start, SEEK_SET);
					} else {
						throw new \RuntimeException(
							'not all columns could be mapped from header');
					}
				} else {
					$this->data_start = ftell($this->fh);
				}
			}
		} else {
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
			throw new RuntimeException(
				'no $columns given and input file is lacking a header line');
		}
	}

	public function __destruct() {
		fclose($this->fh);
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
		return $this->it_row;
	}

	public function columnNameToNumber(string $colname): int {
		if (!array_key_exists($colname, $this->colmap)) {
			throw new \InvalidArgumentException("unknown column: ${colname}");
		}
		return $this->colmap[$colname];
	}

	public function nextRow(): ?array {
		$row = $this->getRow();
		if (is_null($row)) {
			if (!is_null($this->it_curr)) $this->it_row++;
			return ($this->it_curr = NULL);
		}

		$res = array();
		foreach ($this->colmap as $k => $i) {
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
					$value = (float)fieldparser\parse_number($value);
					break;
				case 'int':
				case 'integer':
					$value = (int)round(fieldparser\parse_number($value), 0);
					break;
				case 'string':
					break;
									default:
						throw new \InvalidArgumentException(
							"{$this->columns[$k]['type']} is not a valid column type");
						break;
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

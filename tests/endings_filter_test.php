<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

//require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'csvreader.php';
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'endings_filter.php';

final class endings_filter_test extends TestCase {
	public const FIXTURES_DIR = __DIR__ . '/fixtures/line-endings';

	private function _compare_against_file(
			string $fixture, string $filter, string $expected): void {
		$fh = fopen($fixture, 'r');
		stream_filter_append($fh, "endings.${filter}", STREAM_FILTER_READ);

		$this->assertEquals(
			file_get_contents($expected),
			stream_get_contents($fh));

		fclose($fh);
	}

	public function testCanProcessCRLineEndings(): void {
		foreach (array('mac2unix', 'cr2lf') as $filter) {
			$this->_compare_against_file(
				implode(DIRECTORY_SEPARATOR, array(self::FIXTURES_DIR, 'test1', 'cr.txt')),
				$filter,
				implode(DIRECTORY_SEPARATOR, array(self::FIXTURES_DIR, 'test1', 'lf.txt')));
		}
	}

	public function testCanProcessCRLFLineEndings(): void {
		foreach (array('dos2unix', 'crlf2lf') as $filter) {
			$this->_compare_against_file(
				implode(DIRECTORY_SEPARATOR, array(self::FIXTURES_DIR, 'test1', 'crlf.txt')),
				$filter,
				implode(DIRECTORY_SEPARATOR, array(self::FIXTURES_DIR, 'test1', 'lf.txt')));
		}
	}

	public function testCanProcessLFLineEndings(): void {
		foreach (array('unix2unix', 'lf2lf') as $filter) {
			$this->_compare_against_file(
				implode(DIRECTORY_SEPARATOR, array(self::FIXTURES_DIR, 'test1', 'lf.txt')),
				$filter,
				implode(DIRECTORY_SEPARATOR, array(self::FIXTURES_DIR, 'test1', 'lf.txt')));
		}
	}

}

<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'CSVReader.php';
//require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'csvreader.php';

use riiengineering\csvreader\CSVReader;

final class CSVReaderTest extends TestCase {
	public const DATA_DIR = __DIR__ . '/fixtures/data';

	public function testCSVReadNextRow(): void {
		$reader = new CSVReader(
			implode(DIRECTORY_SEPARATOR, array(self::DATA_DIR, 'csv', 'test1-simple-header.csv')),
			array('id', 'date', 'total'),
			array(
				'separator' => ';',
				'line-separator' => "\n",
				'encoding' => 'ASCII',
				'respect-sep-line' => FALSE,
				'column-order-from-header-line' => TRUE,
			));

		$this->assertSame(
			array('id' => '1', 'date' => '2017-05-23', 'total' => '13257.54'), $reader->nextRow());
		$this->assertSame(
			array('id' => '2', 'date' => '2018-07-14', 'total' =>  '5447.75'), $reader->nextRow());
		$this->assertSame(
			array('id' => '3', 'date' => '2019-11-23', 'total' =>  '4168.48'), $reader->nextRow());
		$this->assertSame(
			array('id' => '4', 'date' => '2020-02-01', 'total' => '41647.41'), $reader->nextRow());
		$this->assertSame(
			array('id' => '5', 'date' => '2022-12-20', 'total' =>  '2345.34'), $reader->nextRow());
		// EOF?
		$this->assertNull($reader->nextRow());
	}

	public function testCSVReadForeach(): void {
		$reader = new CSVReader(
			implode(DIRECTORY_SEPARATOR, array(self::DATA_DIR, 'csv', 'test1-simple-header.csv')),
			array('id', 'date', 'total'),
			array(
				'separator' => ';',
				'line-separator' => "\n",
				'encoding' => 'ASCII',
				'respect-sep-line' => FALSE,
				'column-order-from-header-line' => TRUE,
			));

		$expected = array(
			array('id' => '1', 'date' => '2017-05-23', 'total' => '13257.54'),
			array('id' => '2', 'date' => '2018-07-14', 'total' =>  '5447.75'),
			array('id' => '3', 'date' => '2019-11-23', 'total' =>  '4168.48'),
			array('id' => '4', 'date' => '2020-02-01', 'total' => '41647.41'),
			array('id' => '5', 'date' => '2022-12-20', 'total' =>  '2345.34'),
		);

		foreach ($reader as $i => $row) {
			$this->assertSame($expected[$i-1], $row);
		}
		// EOF?
		$this->assertFalse($reader->valid());
	}

	public function testColumnsFromHeaderDetection(): void {
		$reader = new CSVReader(
			implode(DIRECTORY_SEPARATOR, array(self::DATA_DIR, 'csv', 'test2-header.csv')),
			NULL,
			array(
				'separator' => ',',
				'line-separator' => "\n",
				'encoding' => 'ASCII',
				'respect-sep-line' => FALSE,
				'column-order-from-header-line' => TRUE,
			));

		$this->assertEquals(
			array('id', 'date', 'timestamp', 'code', 'description', 'active'),
			array_keys($reader->columns));

		// check that the reader reads 100 rows (the length of the file)
		$this->assertCount(100, $reader);
	}

	public function testHeaderDetection(): void {  // test if the code detects that the file has no header
		$columns = array(
			'id' => array(),
			'date' => array(),
			'timestamp' => array(),
			'code' => array(),
			'description' => array(),
			'active' => array(),
		);

		$reader = new CSVReader(
			implode(DIRECTORY_SEPARATOR, array(self::DATA_DIR, 'csv', 'test2-noheader.csv')),
			$columns,
			array(
				'separator' => ',',
				'line-separator' => "\n",
				'encoding' => 'ASCII',
				'respect-sep-line' => FALSE,
				'column-order-from-header-line' => TRUE,
			));

		// assert that CSVReader didn't change the columns.
		$this->assertEquals($columns, $reader->columns);

		// check that the reader reads 100 rows (the length of the file)
		$this->assertCount(100, $reader);
	}

	public function formatDetectionData(): array {
		return array(
			array('cars/Export/LibreOffice 4.0/UTF-8_comma.csv', array(
				'line-separator' => "\n",
				'encoding' => 'UTF-8',
				'separator' => ',',
			)),
			array('cars/Export/LibreOffice 4.0/UTF-8_semicolon.csv', array(
				'line-separator' => "\n",
				'encoding' => 'UTF-8',
				'separator' => ';',
			)),
			/* array('cars/Export/LibreOffice 4.0/Windows1252_semicolon.csv', array(
			   'line-separator' => "\n",
			   'encoding' => 'Windows-1252',
			   'separator' => ';',
			   )), */
			/* array('cars/Export/LibreOffice 4.0/MacRoman_comma.csv', array(
			   'line-separator' => "\n",
			   'encoding' => 'Macintosh',
			   'separator' => ',',
			   )), */

			array('cars/Export/Numbers 09/UTF-8.csv', array(
				'line-separator' => "\r\n",
				'encoding' => 'UTF-8',
				'separator' => ',',
			)),
			/* array('cars/Export/Numbers 09/Latin1.csv', array(
			   'line-separator' => "\r\n",
			   'encoding' => 'ISO-8859-1',
			   'separator' => ',',
			   )), */

			array('cars/Export/Office 2016/CSV.csv', array(
				'line-separator' => "\r\n",
				'encoding' => 'Windows-1252',
				'separator' => ';',
			)),
			array('cars/Export/Office 2016/Macintosh.csv', array(
				'line-separator' => "\r",
				'encoding' => 'Macintosh',
				'separator' => ';',
			)),
			/* array('cars/Export/Office 2016/MS-DOS.csv', array(
			   'line-separator' => "\r\n",
			   'encoding' => 'IBM850',
			   'separator' => ';',
			   )), */
			array('cars/Export/Office 2016/Unicode.txt', array(
				'line-separator' => "\r\n",
				'encoding' => 'UTF-16LE',
				'separator' => "\t",
			)),

			array('cars/Export/Office 2008/CSV.csv', array(
				'line-separator' => "\r",
				'encoding' => 'Macintosh',
				'separator' => ';',
			)),
			/* array('cars/Export/Office 2008/MS-DOS.csv', array(
			   'line-separator' => "\r",
			   'encoding' => 'IBM850',
			   'separator' => ';',
			   )), */
			array('cars/Export/Office 2008/Windows.csv', array(
				'line-separator' => "\r\n",
				'encoding' => 'Windows-1252',
				'separator' => ';',
			)),
			array('cars/Export/Office 2008/UTF-16.txt', array(
				'line-separator' => "\r\n",
				'encoding' => 'UTF-16LE',
				'separator' => "\t",
			)),

			array('cars/Export/Office 2003/CSV.csv', array(
				'line-separator' => "\r\n",
				'encoding' => 'Windows-1252',
				'separator' => ';',
			)),
			array('cars/Export/Office 2003/MS-DOS.csv', array(
				'line-separator' => "\r\n",
				'encoding' => 'IBM850',
				'separator' => ';',
			)),
			array('cars/Export/Office 2003/Macintosh.csv', array(
				'line-separator' => "\r",
				'encoding' => 'Macintosh',
				'separator' => ';',
			)),
			array('cars/Export/Office 2003/Unicode.txt', array(
				'line-separator' => "\r\n",
				'encoding' => 'UTF-16LE',
				'separator' => "\t",
			)),

			array('cars/Export/Office 97/CSV.csv', array(
				'line-separator' => "\r\n",
				'encoding' => 'Windows-1252',
				'separator' => ',',
			)),
			/* array('cars/Export/Office 97/MS-DOS.csv', array(
			   'line-separator' => "\r\n",
			   'encoding' => 'IBM850',
			   'separator' => ',',
			   )), */
			array('cars/Export/Office 97/Macintosh.csv', array(
				'line-separator' => "\r",
				'encoding' => 'Macintosh',
				'separator' => ',',
			)),
		);
	}

	/**
	 * @dataProvider formatDetectionData
	 */
	public function testFormatDetection(string $file, array $options): void {
		$reader = new \riiengineering\csvreader\CSVReader(
			self::DATA_DIR . DIRECTORY_SEPARATOR . $file,
			NULL,
			array(
				'separator' => $options['separator'],
				'line-separator' => 'AUTO',
				'encoding' => 'AUTO',
			)
		);
		$options_detected = $reader->options();

		$is = array();
		foreach ($options as $k => $_) {
			$is[$k] = $options_detected[$k];
		}

		$this->assertEquals($options, $is);

		$this->assertEquals(
			array(
				'vid',
				'make',
				'model',
				'currency',
				'price',
				'issold',
			),
			array_keys($reader->columns));
	}

	public function testUTF8MultibyteDetection(): void {
		// Explanation: this file contains a lot of 3-byte UTF-8 EURO
		// characters.  When the encoding detection code uses a window to
		// detect the encoding, there is a high likelihood that it will
		// receive a partial character at the end of the string, essentially
		// making the string non-valid UTF-8. This test is to check if the
		// code handles partial trailing characters correctly.

		$file = implode(DIRECTORY_SEPARATOR, array(self::DATA_DIR, 'csv', 'test-utf8euro.csv'));

		$reader = new CSVReader(
			$file,
			array('a', 'chars'),
			array(
				'separator' => ',',
				'encoding' => 'AUTO',
			));

		$this->assertSame('UTF-8', $reader->options()['encoding']);
	}

	public function testReadLargeFile(): void {
		$file = implode(DIRECTORY_SEPARATOR, array(self::DATA_DIR, 'csv', '1Mlines.tsv.gz'));
		$fh = gzopen($file, 'r');

		$reader = new CSVReader(
			$fh,
			array('a', 'b', 'c'),
			array(
				'separator' => "\t",
			));

		$i = 0;
		while (($row = $reader->nextRow())) {
			++$i;
			$this->assertSame(array('a' => 'hello', 'b' => 'world', 'c' => '123'), $row);
		}
		$this->assertSame(1000000, $i);
	}
}

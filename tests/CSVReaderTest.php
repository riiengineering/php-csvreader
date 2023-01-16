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
			array('id' => '1', 'date' => '2017-05-23', 'total' => '13\'257.54'), $reader->nextRow());
		$this->assertSame(
			array('id' => '2', 'date' => '2018-07-14', 'total' =>   '5,447.75'), $reader->nextRow());
		$this->assertSame(
			array('id' => '3', 'date' => '2019-11-23', 'total' =>   '4.168,48'), $reader->nextRow());
		$this->assertSame(
			array('id' => '4', 'date' => '2020-02-01', 'total' =>  '41 647.41'), $reader->nextRow());
		$this->assertSame(
			array('id' => '5', 'date' => '2022-12-20', 'total' =>   '2 345,34'), $reader->nextRow());
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
			array('id' => '1', 'date' => '2017-05-23', 'total' => '13\'257.54'),
			array('id' => '2', 'date' => '2018-07-14', 'total' =>   '5,447.75'),
			array('id' => '3', 'date' => '2019-11-23', 'total' =>   '4.168,48'),
			array('id' => '4', 'date' => '2020-02-01', 'total' =>  '41 647.41'),
			array('id' => '5', 'date' => '2022-12-20', 'total' =>   '2 345,34'),
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

	public function testTypeConversions(): void {
		// Test 1

		$reader = new CSVReader(
			implode(DIRECTORY_SEPARATOR, array(self::DATA_DIR, 'csv', 'test1-simple-header.csv')),
			array(
				'date' => array(),
				'id' => array('type' => 'int',),
				'total' => array('type' => 'number',),
			),
			array(
				'separator' => ';',
				'line-separator' => "\n",
				'encoding' => 'ASCII',
				'respect-sep-line' => FALSE,
				'column-order-from-header-line' => TRUE,
			));

		$expected = array(
			array('id' => 1, 'date' => '2017-05-23', 'total' => 13257.54),
			array('id' => 2, 'date' => '2018-07-14', 'total' =>  5447.75),
			array('id' => 3, 'date' => '2019-11-23', 'total' =>  4168.48),
			array('id' => 4, 'date' => '2020-02-01', 'total' => 41647.41),
			array('id' => 5, 'date' => '2022-12-20', 'total' =>  2345.34),
		);

		foreach ($reader as $i => $row) {
			$this->assertSame($expected[$i-1], $row);
		}
		// EOF?
		$this->assertFalse($reader->valid());

		unset($reader);


		// Test 2

		$reader = new CSVReader(
			implode(DIRECTORY_SEPARATOR, array(self::DATA_DIR, 'csv', 'test2-header.csv')),
			array(
				'code' => array('type' => 'string',),
				'date' => array(),
				'id' => array('type' => 'int',),
				'timestamp' => array('type' => 'int',),
				'description' => array('type' => 'string',),
				'active' => array('type' => 'boolean',),
			),
			array(
				'separator' => ',',
				'line-separator' => "\n",
				'encoding' => 'ASCII',
				'respect-sep-line' => FALSE,
				'column-order-from-header-line' => TRUE,
			));

		$expected = array(
array('id' => 2581, 'date' => '1976-06-09', 'timestamp' => 1313289800965, 'code' => 'VZFRG', 'description' => 'indonesia tunes zinc soma passwords',              'active' => TRUE),
array('id' => 2582, 'date' => '2003-12-26', 'timestamp' => 690797645152,  'code' => 'EPRIJ', 'description' => 'mh hear vacuum perform sample',                    'active' => FALSE),
array('id' => 2583, 'date' => '2003-11-03', 'timestamp' => 89510027403,   'code' => 'QEPBU', 'description' => 'healing earned borough casting mas',               'active' => FALSE),
array('id' => 2584, 'date' => '1985-02-27', 'timestamp' => 534860248553,  'code' => 'TTGDF', 'description' => 'tied kodak reg translation editing',               'active' => FALSE),
array('id' => 2585, 'date' => '1989-09-26', 'timestamp' => 723368268878,  'code' => 'EQWLW', 'description' => 'candidate retail meetup arab can',                 'active' => FALSE),
array('id' => 2586, 'date' => '2014-06-01', 'timestamp' => 672166143946,  'code' => 'UWQWI', 'description' => 'tigers volkswagen encountered involve j',          'active' => FALSE),
array('id' => 2587, 'date' => '2009-01-15', 'timestamp' => 427251486194,  'code' => 'SRYWB', 'description' => 'briefing bachelor conservative allen nutritional', 'active' => TRUE),
array('id' => 2588, 'date' => '1979-09-21', 'timestamp' => 711559059047,  'code' => 'SKSYN', 'description' => 'losing lies pictures role throughout',             'active' => FALSE),
array('id' => 2589, 'date' => '1979-03-18', 'timestamp' => 1123389328384, 'code' => 'QQNKN', 'description' => 'lambda poly meat gamespot wear',                   'active' => TRUE),
array('id' => 2590, 'date' => '1984-02-17', 'timestamp' => 730277074193,  'code' => 'KYZYX', 'description' => 'closely artwork trigger atmospheric retrieve',     'active' => FALSE),
array('id' => 2591, 'date' => '1972-04-13', 'timestamp' => 531766732563,  'code' => 'UJSQN', 'description' => 'liked rolling least extent norfolk',               'active' => FALSE),
array('id' => 2592, 'date' => '1993-08-30', 'timestamp' => 1138205375884, 'code' => 'AHXZX', 'description' => 'da solve tim retailer vendors',                    'active' => TRUE),
array('id' => 2593, 'date' => '1997-07-27', 'timestamp' => 1295665688872, 'code' => 'ZBAJZ', 'description' => 'horse circles inline by alt',                      'active' => FALSE),
array('id' => 2594, 'date' => '2003-09-17', 'timestamp' => 993751636118,  'code' => 'PBQLP', 'description' => 'that emission substances distance number',         'active' => TRUE),
array('id' => 2595, 'date' => '1990-12-21', 'timestamp' => 1229443276959, 'code' => 'OYBMN', 'description' => 'deborah jokes love healing marks',                 'active' => FALSE),
array('id' => 2596, 'date' => '1986-12-24', 'timestamp' => 582241070063,  'code' => 'OVESV', 'description' => 'geometry anna verse biography watching',           'active' => TRUE),
array('id' => 2597, 'date' => '2009-08-31', 'timestamp' => 1526658075554, 'code' => 'SJOLY', 'description' => 'monica rolled verified boulder prototype',         'active' => TRUE),
array('id' => 2598, 'date' => '1981-10-20', 'timestamp' => 484246584583,  'code' => 'OICCL', 'description' => 'bingo relative cheapest average scuba',            'active' => TRUE),
array('id' => 2599, 'date' => '1980-01-20', 'timestamp' => 697826670112,  'code' => 'AKKGL', 'description' => 'manchester changing unsubscribe soon gabriel',     'active' => TRUE),
array('id' => 2600, 'date' => '2015-05-30', 'timestamp' => 872096121073,  'code' => 'CFEZF', 'description' => 'arrived practice waters generally converter',      'active' => FALSE),
		);

		foreach ($reader as $i => $row) {
			if (!array_key_exists($i-1, $expected)) break;
			$this->assertSame($expected[$i-1], $row);
		}

		unset($reader);
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

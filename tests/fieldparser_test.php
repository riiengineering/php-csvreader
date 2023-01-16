<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

//require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'csvreader.php';
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'fieldparser.php';


final class fieldparser_test extends TestCase {
	public function assertAllTrue(array $conditions, string $message = '') {
		foreach ($conditions as $condition) {
			$this->assertTrue($condition, $message);
		}
	}

	public function assertAllNotTrue(array $conditions, string $message = '') {
		foreach ($conditions as $condition) {
			$this->assertNotTrue($condition, $message);
		}
	}

	public function testBooleanParser(): void {
		$trues = array('true', 'on', 'yes', '1');
		$falses = array('false', 'off', 'no', '0');

		$fn = '\riiengineering\csvreader\fieldparser\parse_boolean';

		$this->assertAllTrue(array_map($fn, $trues));
		$this->assertAllTrue(array_map($fn, array_map('strtoupper', $trues)));
		$this->assertAllTrue(array_map($fn, array_map('ucfirst', $trues)));

		$this->assertAllNotTrue(array_map($fn, $falses));
		$this->assertAllNotTrue(array_map($fn, array_map('strtoupper', $falses)));
		$this->assertAllNotTrue(array_map($fn, array_map('ucfirst', $falses)));

		$this->assertNull($fn('foo'));
	}

	public function testNumberParser(): void {
		$fn = '\riiengineering\csvreader\fieldparser\parse_number';

		$this->assertNull($fn(''));
		$this->assertNull($fn('notanumber'));

		$this->assertNull($fn('x'));
		$this->assertNull($fn('f'));
		$this->assertNull($fn('-f'));
	}

	public function testNumberParserHex(): void {
		$fn = '\riiengineering\csvreader\fieldparser\parse_number';

		$this->assertSame(0, $fn('0x'));
		$this->assertSame(0, $fn('0x0'));
		$this->assertSame(1, $fn('0x1'));
		$this->assertSame(32, $fn('0x20'));
	}

	public function testNumberParserExcelFormat(): void {
		$inputs = array(
			// ints
			'123',
			'-123',
			'123456',
			'-123456',
			"1'234'567",
			"-1'234'567",
			'1234567',
			'-1234567',
			'2.456',
			'-2.456',
			'1 234',
			'-1 234',
			'1_234',
			'-1_234',
			"1'234'567'890",
			"-1'234'567'890",
			'1.234.567.890',
			'-1.234.567.890',

			// small floats
			'3.1416',
			'-3.1416',
			'123.45',
			'-123.45',
			"1'084.24",
			"-1'084.24",
			'2,123.30',
			'-2,123.30',
			'1.234,50',
			'-1.234,50',
			'1 234,50',
			'-1 234,50',
			'1 234.50',
			'-1 234.50',

			// large floats
			'123456.78',
			'-123456.78',
			'123.456,789',
			'-123.456,789',
			'123_456.789',
			'-123_456.789',
			"1'234'567'890.12345",
			"-1'234'567'890.12345",

			// trailing decimal separator
			'123.',
			'-123.',
			'1,234.',
			'-1,234.',
			'1 234.',
			'-1 234.',
			'1?234.',
			'-1?234.',
			'123,',
			'-123,',
			'1.234,',
			'-1.234,',

			// floats < 1
			'.5',
			'-.5',
			'.01',
			'-.01',

			// invalid numbers
			'2.456.70',
			'-2.456.70',
			"1'234'567'890.123'45",
			"-1'234'567'890.123'45",

			// not number strings
			'not a number',
			'NaN',
			'three',

			// Australia, Cambodia, Canada (English-speaking; unofficial), China, Hong Kong, Iran, Ireland, Israel, Japan, Korea, Macau (in Chinese and English text), Malaysia, Malta, Mexico, Namibia, New Zealand, Pakistan, Peru (currency numbers), Philippines, Singapore, South Africa (English-speaking; unofficial), Taiwan, Thailand, United Kingdom and other Commonwealth states except Mozambique, United States.
			'1,234,567.89',
			'-1,234,567.89',
			// SI style (English version), Canada (English-speaking; official), China, Estonia (currency numbers), Hong Kong (in education), Namibia, South Africa (English-speaking; unofficial), Sri Lanka, Switzerland (officially encouraged for currency numbers only), United Kingdom (in education), United States (in education).
			// Bangladesh, India, Nepal, Pakistan (see Indian numbering system).
			'1234567.89',
			'-1234567.89',
			// SI style (French version), Albania, Belgium (French), Brazil, Bulgaria, Canada (French-speaking), Costa Rica, Croatia, Czechia, Estonia, Finland, France, Hungary, Italy (in education), Kosovo, Latin Europe, Latvia, Lithuania, Macau (in Portuguese text), Mozambique, Norway, Peru, Poland, Portugal, Russia, Serbia, Slovakia, South Africa (official), Spain (official use since 2010, according to the RAE and CSIC), Sweden, Switzerland (officially encouraged, except currency numbers), Ukraine, Vietnam (in education).
			'1234567,89',
			'-1234567,89',
			// Argentina, Austria, Belgium (Dutch), Bosnia and Herzegovina, Brazil, Chile, Colombia, Croatia (in bookkeeping), Denmark, Germany, Greece, Indonesia, Italy, Netherlands, Poland, Romania, Slovenia, Serbia (informal), Spain (used until 2010, inadvisable use according to the RAE and CSIC), Turkey, Uruguay, Vietnam.
			'1.234.567,89',
			'-1.234.567,89',
			// Malaysia, Philippines (uncommon today), Singapore, United Kingdom (older, typically handwritten; in education)
			'1,234,567·89',
			'-1,234,567·89',
			// Switzerland (computing), Liechtenstein.
			"1'234'567.89",
			"-1'234'567.89",
			// Switzerland (handwriting), Italy (handwriting).
			"1'234'567,89",
			"-1'234'567,89",
			// Spain (handwriting, used until 1980s, inadvisable use according to the RAE and CSIC).
			"1.234.567'89",
			"-1.234.567'89",
		);
		$should = array(
			// ints
			123,
			-123,
			123456,
			-123456,
			1234567,
			-1234567,
			1234567,
			-1234567,
			2456,
			-2456,
			1234,
			-1234,
			1234,
			-1234,
			1234567890,
			-1234567890,
			1234567890,
			-1234567890,

			// small floats
			3.1416,
			-3.1416,
			123.45,
			-123.45,
			1084.24,
			-1084.24,
			2123.3,
			-2123.3,
			1234.5,
			-1234.5,
			1234.5,
			-1234.5,
			1234.5,
			-1234.5,

			// large floats
			123456.78,
			-123456.78,
			123456.789,
			-123456.789,
			123456.789,
			-123456.789,
			1234567890.12345,
			-1234567890.12345,

			// trailing dots
			123.0,
			-123.0,
			1234.0,
			-1234.0,
			1234.0,
			-1234.0,
			1234.0,
			-1234.0,
			123.0,
			-123.0,
			1234.0,
			-1234.0,

			// floats < 1
			0.5,
			-0.5,
			0.01,
			-0.01,

			// invalid numbers
			NULL,
			NULL,
			NULL,
			NULL,

			// not number strings
			NULL,
			NULL,
			NULL,

			// Wikipedia
			1234567.89,
			-1234567.89,
			1234567.89,
			-1234567.89,
			1234567.89,
			-1234567.89,
			1234567.89,
			-1234567.89,
			1234567.89,
			-1234567.89,
			1234567.89,
			-1234567.89,
			1234567.89,
			-1234567.89,
			1234567.89,
			-1234567.89,
		);

		$fn = '\riiengineering\csvreader\fieldparser\parse_number';

		foreach (array_map(NULL, $inputs, $should) as list($input, $should)) {
			$num = $fn($input);
			$this->assertSame(
				$should,
				$num,
				"number ${input} was parsed to ${num} (expecting ${should})");
		}
	}
}

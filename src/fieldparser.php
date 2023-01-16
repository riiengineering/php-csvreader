<?php declare(strict_types=1);

namespace riiengineering\csvreader\fieldparser;

function parse_boolean(string $value): ?bool {
	return @array(
		'true' => TRUE,
		'on' => TRUE,
		'yes' => TRUE,
		'1' => TRUE,
		'false' => FALSE,
		'off' => FALSE,
		'no' => FALSE,
		'0' => FALSE
	)[strtolower($value)];
}

function parse_number(string $value) {
	if ('' === $value) {
		return NULL;
	}

	// hex numbers
	if ('0x' === substr($value, 0, 2)) {
		return intval($value, 16);
	}

	// excel number format
	if (1 === preg_match('/^-?(?:[0-9]{1,3}(?:([^0-9])?[0-9]{3}(?:(?(1)\1)[0-9]{3})*)?)?(?:(?(1)(?!\1))([^0-9])[0-9]*)?$/u', $value, $matches, PREG_UNMATCHED_AS_NULL)) {
		$ksep = @$matches[1]; $dsep = @$matches[2];

		$value = str_replace([$ksep, $dsep], ['', '.'], $value);

		// NOTE: the regex above accepts both the part before and after the
		//       decimal separator to be empty, so we check for it here and
		//       handle it as an invalid case.
		if ('.' === $value || '-.' === $value) return NULL;

		return $dsep ? floatval($value) : intval($value, 10);
	}
	return NULL;
}

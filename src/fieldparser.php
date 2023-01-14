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
	if (1 === preg_match('/^-?(?:[0-9]{1,3}(?:([^0-9])?[0-9]{3}(?:(?(1)\1)[0-9]{3})*)?)?(?:(?(1)(?!\1))([^0-9])[0-9]*)?$/u', $value, $matches, PREG_UNMATCHED_AS_NULL)) {
		$ksep = @$matches[1]; $dsep = @$matches[2];

		$value = str_replace([$ksep, $dsep], ['', '.'], $value);
		return $dsep ? floatval($value) : intval($value, 10);
	}
	return NULL;
}

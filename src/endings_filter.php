<?php declare(strict_types=1);

class endings_filter extends php_user_filter {
	public string $input_line_ending;
	public string $output_line_ending;

	public function filter(/* resource */ $in, /*resource */ $out, /* int */ &$consumed, /* bool */ $closing) /*: int */ {
		$resize = strlen($this->input_line_ending) != strlen($this->output_line_ending);

		while (($bucket = stream_bucket_make_writeable($in))) {
			// NOTE: increment $consumed prior to changing datalen
			$consumed += $bucket->datalen;

			$bucket->data = str_replace(
				$this->input_line_ending, $this->output_line_ending,
				$bucket->data);
			if ($resize)
				$bucket->datalen = strlen($bucket->data);
			stream_bucket_append($out, $bucket);
		}

		return PSFS_PASS_ON;
	}

	public function onCreate(): bool {
		switch ($this->filtername) {
			case 'endings.dos2unix':
			case 'endings.crlf2lf':
				$this->input_line_ending = "\r\n";
				$this->output_line_ending = "\n";
				break;
			case 'endings.mac2unix':
			case 'endings.cr2lf':
				$this->input_line_ending = "\r";
				$this->output_line_ending = "\n";
				break;
			case 'endings.unix2unix':
			case 'endings.lf2lf':
				$this->input_line_ending = "\n";
				$this->output_line_ending = "\n";
				break;
			default:
				return FALSE;
		}
		return TRUE;
	}

	public function onClose(): void {

	}
}
stream_filter_register('endings.*', 'endings_filter') or die("failed to register endings.* filter");

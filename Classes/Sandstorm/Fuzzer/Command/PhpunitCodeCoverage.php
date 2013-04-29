<?php
namespace Sandstorm\Fuzzer\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.Fuzzer".      *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

class PhpunitCodeCoverage extends \PHP_CodeCoverage {

	public function setData($data) {
		$this->data = $data;
	}

	public function setTests($tests) {
		$this->tests = $tests;
	}
}
?>
<?php
namespace Sandstorm\Fuzzer\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.Fuzzer".      *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * Code Coverage helpers for working with Functional Tests
 *
 * @Flow\Scope("singleton")
 */
class CodeCoverageCommandController extends \TYPO3\Flow\Cli\CommandController {

	/**
	 * As code coverage for functional tests is generated, the
	 *
	 * @param string $codeCoverageFile
	 */
	public function convertCommand($codeCoverageFile) {
		/* @var $coverage \Php_CodeCoverage */
		$coverage = unserialize(file_get_contents($codeCoverageFile));
		$coverageData = $coverage->getData();
		foreach ($coverageData as $key => $value) {
			var_dump($key);
		}

	}
}
?>
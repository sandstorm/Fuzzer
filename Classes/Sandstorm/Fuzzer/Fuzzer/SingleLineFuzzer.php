<?php
namespace Sandstorm\Fuzzer\Fuzzer;

/*                                                                        *
 * This script belongs to the FLOW3 package "Sandstorm.Fuzzer".      *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * asdf
 */
class SingleLineFuzzer extends AbstractFuzzer {

	protected $name = 'Single Line Fuzzer';

	/**
	 * The clover file structure containing the initial code coverage data,
	 * as simple XML element
	 *
	 * @var \SimpleXMLElement
	 */
	protected $initialCodeCoverageData;

	protected $lineNumbersEligibleForMutation = array();

	protected $currentlyMutatedLineNumberIndex = 0;

	protected $unmodifiedClassFileContents = '';

	public function initializeObject() {
		$initialCodeCoveragePathAndFilename = tempnam('/some/non/existing/path', 'fuzzer_clover');
		$output = array();
		$returnValue = NULL;
		exec(sprintf('phpunit -c Build/Common/PhpUnit/UnitTests.xml --coverage-clover %s %s%s', $initialCodeCoveragePathAndFilename, $this->package->getPackagePath(), $this->testPath), $output, $returnValue);

		if ($returnValue !== 0) {
			throw new \Exception('Initial clover code coverage could not be determined. are you sure your code runs through? The phpunit response was:' . chr(10) . chr(10) . implode('\n', $output));
		}

		$this->initialCodeCoverageData = new \SimpleXMLElement(file_get_contents($initialCodeCoveragePathAndFilename));
	}

	public function initializeMutationsForClassFile($absoluteClassPathAndFilename) {
		$this->unmodifiedClassFileContents = file_get_contents($absoluteClassPathAndFilename);

		$this->lineNumbersEligibleForMutation = array();
		$this->currentlyMutatedLineNumberIndex = 0;

		$elements = $this->initialCodeCoverageData->xpath('//file[@name="' . $absoluteClassPathAndFilename . '"]/line[@type = "stmt"][@count != "0"]');

		if (!is_array($elements)) {
			return;
		}
		foreach ($elements as $el) {
			$this->lineNumbersEligibleForMutation[] = (int)$el['num'];
		}
	}

	public function nextMutation() {
		if (!isset($this->lineNumbersEligibleForMutation[$this->currentlyMutatedLineNumberIndex])) {
			return FALSE;
		}

		$lineNumberWhichShouldBeMutated = $this->lineNumbersEligibleForMutation[$this->currentlyMutatedLineNumberIndex];
		$this->currentlyMutatedLineNumberIndex++;

		return $this->commentOutLineWithNumber($lineNumberWhichShouldBeMutated);
	}

	protected function commentOutLineWithNumber($lineNumberWhichShouldBeCommentedOut) {
		$modifiedClassFile = array();

			// The first line in the class has line number "1"
		$i=1;
		foreach (explode("\n", $this->unmodifiedClassFileContents) as $line) {
			if ($i === $lineNumberWhichShouldBeCommentedOut) {
				$modifiedClassFile[] = '# ' . $line;
			} else {
				$modifiedClassFile[] = $line;
			}

			$i++;
		}
		return implode("\n", $modifiedClassFile);
	}
}
?>
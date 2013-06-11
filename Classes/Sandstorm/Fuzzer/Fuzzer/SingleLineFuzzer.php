<?php
namespace Sandstorm\Fuzzer\Fuzzer;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.Fuzzer".      *
 *                                                                        *
 *                                                                        */

/**
 * asdf
 */
class SingleLineFuzzer extends AbstractFuzzer {

	protected $name = 'Single Line Fuzzer';

	protected $lineNumbersEligibleForMutation = array();

	protected $currentlyMutatedLineNumberIndex = 0;

	protected $unmodifiedClassFileContents = '';

	public function initializeMutationsForClassFile($absoluteClassPathAndFilename, \SimpleXMLElement $codeCoverageData) {
		$this->unmodifiedClassFileContents = file_get_contents($absoluteClassPathAndFilename);

		$this->lineNumbersEligibleForMutation = array();
		$this->currentlyMutatedLineNumberIndex = 0;

		$elements = $codeCoverageData->xpath('//file[@name="' . $absoluteClassPathAndFilename . '"]/line[@type = "stmt"][@count != "0"]');

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
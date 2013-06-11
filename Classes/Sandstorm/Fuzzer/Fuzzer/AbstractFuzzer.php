<?php
namespace Sandstorm\Fuzzer\Fuzzer;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.Fuzzer".      *
 *                                                                        *
 *                                                                        */

/**
 * Base class for all fuzzers
 */
abstract class AbstractFuzzer {

	/**
	 * Human readable name of this fuzzer; to be used in output
	 * @var string
	 */
	protected $name = '';

	/**
	 * @return string the human readable name of this fuzzer, to be used in output
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @param string $absoluteClassPathAndFilename
	 * @param \SimpleXMLElement $codeCoverageData
	 */
	abstract public function initializeMutationsForClassFile($absoluteClassPathAndFilename, \SimpleXMLElement $codeCoverageData);

	/**
	 * @return string or FALSE
	 */
	abstract public function nextMutation();
}
?>
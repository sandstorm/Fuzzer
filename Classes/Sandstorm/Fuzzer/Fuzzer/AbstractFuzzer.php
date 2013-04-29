<?php
namespace Sandstorm\Fuzzer\Fuzzer;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.Fuzzer".      *
 *                                                                        *
 *                                                                        */

/**
 * asdf
 */
abstract class AbstractFuzzer {

	/**
	 * @var \TYPO3\Flow\Package\PackageInterface
	 */
	protected $package;

	protected $testPath;

	protected $name = '';

	public function __construct(\TYPO3\Flow\Package\PackageInterface $package, $testPath) {
		$this->package = $package;
		$this->testPath = $testPath;
	}

	public function getName() {
		return $this->name;
	}

	abstract public function initializeMutationsForClassFile($absoluteClassPathAndFilename);

}
?>
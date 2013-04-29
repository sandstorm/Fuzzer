<?php
namespace SandstormMedia\Fuzzer\Fuzzer;

/*                                                                        *
 * This script belongs to the FLOW3 package "SandstormMedia.Fuzzer".      *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

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
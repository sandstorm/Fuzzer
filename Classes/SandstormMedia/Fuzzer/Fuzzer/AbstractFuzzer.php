<?php
namespace SandstormMedia\Fuzzer\Fuzzer;

/*                                                                        *
 * This script belongs to the FLOW3 package "SandstormMedia.Fuzzer".      *
 *                                                                        *
 *                                                                        */

use TYPO3\FLOW3\Annotations as FLOW3;

/**
 * asdf
 */
abstract class AbstractFuzzer {

	/**
	 * @var \TYPO3\FLOW3\Package\PackageInterface
	 */
	protected $package;

	protected $testPath;

	protected $name = '';

	public function __construct(\TYPO3\FLOW3\Package\PackageInterface $package, $testPath) {
		$this->package = $package;
		$this->testPath = $testPath;
	}

	public function getName() {
		return $this->name;
	}

	abstract public function initializeMutationsForClassFile($absoluteClassPathAndFilename);

}
?>
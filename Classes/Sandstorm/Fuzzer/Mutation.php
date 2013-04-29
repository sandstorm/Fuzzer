<?php
namespace Sandstorm\Fuzzer;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.Fuzzer".      *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * Undetected Mutation class
 */
class Mutation {
	protected $packageKey;

	protected $relativePathAndFileName;

	protected $descriptionOfModification;

	protected $diff;

	public function getPackageKey() {
		return $this->packageKey;
	}

	public function setPackageKey($packageKey) {
		$this->packageKey = $packageKey;
	}

	public function getRelativePathAndFileName() {
		return $this->relativePathAndFileName;
	}

	public function setRelativePathAndFileName($relativePathAndFileName) {
		$this->relativePathAndFileName = $relativePathAndFileName;
	}

	public function getDescriptionOfModification() {
		return $this->descriptionOfModification;
	}

	public function setDescriptionOfModification($descriptionOfModification) {
		$this->descriptionOfModification = $descriptionOfModification;
	}

	public function getDiff() {
		return $this->diff;
	}

	public function setDiff($diff) {
		$this->diff = $diff;
	}
}
?>
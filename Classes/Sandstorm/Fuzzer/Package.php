<?php
namespace Sandstorm\Fuzzer;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.Fuzzer".      *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Package\Package as BasePackage;

/**
 * Package class for Fuzzer
 */
class Package extends BasePackage {


	/**
	 * Invokes custom PHP code directly after the package manager has been initialized.
	 *
	 * @param \TYPO3\Flow\Core\Bootstrap $bootstrap The current bootstrap
	 * @return void
	 */
	public function boot(\TYPO3\Flow\Core\Bootstrap $bootstrap) {
		include(__DIR__ . '/../../../../../Libraries/phpunit/php-code-coverage/PHP/CodeCoverage/Autoload.php');
	}
}

?>
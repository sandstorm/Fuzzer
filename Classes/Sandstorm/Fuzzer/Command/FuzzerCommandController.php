<?php
namespace Sandstorm\Fuzzer\Command;

/*                                                                        *
 * This script belongs to the FLOW3 package "Sandstorm.Fuzzer".      *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * Fuzzer command controller for the Sandstorm.Fuzzer package
 *
 * @Flow\Scope("singleton")
 */
class FuzzerCommandController extends \TYPO3\Flow\Cli\CommandController {

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Package\PackageManagerInterface
	 */
	protected $packageManager;

	/**
	 * @var array
	 */
	protected $settings;


	protected $numberOfTotalMutations = 0;
	protected $numberOfMutationsWithBrokenSyntax = 0;
	protected $numberOfDetectedMutationsWithCorrectSyntax = 0;
	protected $undetectedMutations = array();

	protected $unitTestTimeout = 0;

	public function injectSettings(array $settings) {
		$this->settings = $settings;
	}

	/**
	 * An example command
	 *
	 * The comment of this command method is also used for FLOW3's help screens. The first line should give a very short
	 * summary about what the command does. Then, after an empty line, you should explain in more detail what the command
	 * does. You might also give some usage example.
	 *
	 * It is important to document the parameters with param tags, because that information will also appear in the help
	 * screen.
	 *
	 * @param string $packageKey The package key
	 * @param string $classFileRegex
	 * @param string $testRegex
	 * @return void
	 */
	public function fuzzCommand($packageKey, $classFileRegex = NULL, $testPath = NULL) {
		$startTime = time();

		$package = $this->packageManager->getPackage($packageKey);
		$packagePath = $package->getPackagePath();

		$this->checkPrerequisites($packagePath);
		$this->determineUnitTestTimeout($package, $testPath);

		$fuzzers = $this->loadAndInitializeFuzzers($package, $testPath);

		$this->resetStatistics();

		$this->outputLine('<b>Generating and Testing Mutations:</b>');
		$this->flush();

		foreach ($package->getClassFiles() as $relativeClassPathAndFilename) {
			$absoluteClassPathAndFilename = $packagePath . $relativeClassPathAndFilename;
			if ($relativeClassPathAndFilename !== NULL && !preg_match('#'. $classFileRegex . '#', $relativeClassPathAndFilename)) {
				continue;
			}
			foreach ($fuzzers as $fuzzer) {
				$fuzzer->initializeMutationsForClassFile($absoluteClassPathAndFilename);
				while ($classFileContents = $fuzzer->nextMutation()) {
					file_put_contents($absoluteClassPathAndFilename, $classFileContents);

					$this->runTestsForPackage($package, $relativeClassPathAndFilename, $testPath);
				}
				// Reset fuzzed file
				exec(sprintf('cd %s; git checkout -- %s', $packagePath, $relativeClassPathAndFilename));
			}
		}

		$this->outputStatistics();

		$this->outputLine('Total Runtime: %u s', array(time() - $startTime));
	}

	protected function checkPrerequisites($packagePath) {
		if (!is_dir($packagePath . '.git')) {
			throw new \Exception('Package is not a git repository; aborting.');
		}
		$output = array();

		exec('cd ' . $packagePath . '; git status -z', $output);
		$output = implode('\n', $output);
		if (strlen($output) > 0) {
			throw new \Exception('Not all files in the package are committed to Git; aborting.');
		}
	}

	protected function determineUnitTestTimeout(\TYPO3\Flow\Package\PackageInterface $package, $testPath) {
		$this->unitTestTimeout = 10000;

		$startTime = time();
		$this->runPhpUnitForPackage($package, $testPath);
		$testDuration = time() - $startTime;

		if ($testDuration === 0) {
			$testDuration = 1;
		}

		$this->unitTestTimeout = $testDuration * 3;

		$this->outputLine('Unmodified unit tests took %u seconds, setting timeout to %u seconds (to be safe).', array($testDuration, $this->unitTestTimeout));
		$this->flush();
	}

	protected function loadAndInitializeFuzzers(\TYPO3\Flow\Package\PackageInterface $package, $testPath = 'Tests/Unit') {
		$fuzzers = array();
		foreach ($this->settings['fuzzers'] as $fuzzerClassName) {
			$fuzzers[] = new $fuzzerClassName($package, $testPath);
		}
		return $fuzzers;
	}

	protected function runTestsForPackage(\TYPO3\Flow\Package\PackageInterface $package, $relativeClassPathAndFilename, $testPath) {
		$this->numberOfTotalMutations++;

		$output = array();
		$returnValue = NULL;
		exec(sprintf('php -l %s 2>/dev/null', $package->getPackagePath() . $relativeClassPathAndFilename), $output, $returnValue);
		if ($returnValue !== 0) {
			$this->numberOfMutationsWithBrokenSyntax++;
			$this->output('_');
			$this->flush();
			return;
		}

		$returnValue = $this->runPhpUnitForPackage($package, $testPath);

		if ($returnValue === 0) {
				// PHPUnit did NOT report an error, i.e. all tests ran through. That's a severe problem, as we modified the
				// source code and our unit tests did not find that... we're after such stuff modifications :)
			$mutation = new \Sandstorm\Fuzzer\Mutation();
			$mutation->setPackageKey($package->getPackageKey());
			$mutation->setRelativePathAndFileName($relativeClassPathAndFilename);
			$mutation->setDescriptionOfModification('TODO: Describe modification');

			$output = array();
			$returnValue = NULL;
			exec(sprintf('cd %s; git diff', $package->getPackagePath()), $output, $returnValue);

			$mutation->setDiff(implode("\n", $output));

			$this->undetectedMutations[] = $mutation;
			$this->output('E');
			$this->flush();
		} elseif ($returnValue === 137) {
				// We handle timeouts like successful detections
			$this->numberOfDetectedMutationsWithCorrectSyntax++;
			$this->output('T');
			$this->flush();
		} else {
				// PHPUnit reported an error, thus we detected the modification to the source code
			$this->numberOfDetectedMutationsWithCorrectSyntax++;
			$this->output('.');
			$this->flush();
		}
	}

	/**
	 * Return value is:
	 * - 0 if unit tests ran through successfully,
	 * - 1 or 2 if the tests failed or an error occured,
	 * - 255 if a fatal error occured
	 * - 137 ON TIMEOUT
	 *
	 *
	 * @param \TYPO3\Flow\Package\PackageInterface $package
	 * @return null
	 */
	protected function runPhpUnitForPackage(\TYPO3\Flow\Package\PackageInterface $package, $testPath = 'Tests/Unit/') {
		$output = array();
		$returnValue = NULL;

		exec(sprintf('timeout %s phpunit -c Build/Common/PhpUnit/UnitTests.xml %s%s 2>/dev/null', $this->unitTestTimeout, $package->getPackagePath(), $testPath), $output, $returnValue);
		return $returnValue;
	}

	protected function resetStatistics() {
		$this->numberOfTotalMutations = 0;
		$this->numberOfMutationsWithBrokenSyntax = 0;
		$this->numberOfDetectedMutationsWithCorrectSyntax = 0;
		$this->undetectedMutations = array();
	}

	protected function outputStatistics() {
		$this->outputLine();
		$this->outputLine();
		$this->outputLine('<em>Undetected Mutations</em>');
		$this->outputLine('<em>--------------------</em>');

		foreach ($this->undetectedMutations as $mutation) {
			$this->outputLine('<b>%s</b>', array($mutation->getRelativePathAndFileName()));
			$this->outputLine('  Package: %s', array($mutation->getPackageKey()));
			$this->outputLine('  Mutation: %s', array($mutation->getDescriptionOfModification()));
			$this->outputLine('  <u>Diff follows below</u>');
			$this->outputLine($mutation->getDiff());
			$this->outputLine();
		}

		$this->outputLine();
		$this->outputLine();
		$this->outputLine('<em>Fuzzing Statistics</em>');
		$this->outputLine('<em>------------------</em>');
		$this->outputLine('Total Mutations: %u', array($this->numberOfTotalMutations));
		$this->outputLine('Mutations with Broken Syntax: %u', array($this->numberOfMutationsWithBrokenSyntax));
		$this->outputLine('Detected Mutations: %u', array($this->numberOfDetectedMutationsWithCorrectSyntax));
		$this->outputLine('<b>Undetected mutations: %u</b> (see above for details)', array(count($this->undetectedMutations)));
	}

	protected function flush() {
		$this->response->send();
		$this->response->setContent('');
	}
}
?>
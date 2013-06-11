<?php
namespace Sandstorm\Fuzzer\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.Fuzzer".      *
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
	 * @Flow\Inject
	 * @var \Sandstorm\Fuzzer\Command\CodeCoverageCommandController
	 */
	protected $codeCoverageCommandController;

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * Options
	 */
	protected $runUnitTests = FALSE;
	protected $runFunctionalTests = FALSE;
	protected $unitTestTimeout = 0;
	protected $functionalTestTimeout = 0;
	protected $phpunitBinary = NULL;

	/**
	 * @var \SimpleXMLElement
	 */
	protected $codeCoverageData;

	/**
	 * Results
	 */
	protected $numberOfTotalMutations = 0;
	protected $numberOfMutationsWithBrokenSyntax = 0;
	protected $numberOfDetectedMutationsWithCorrectSyntax = 0;
	protected $undetectedMutations = array();

	/**
	 * @param array $settings
	 */
	public function injectSettings(array $settings) {
		$this->settings = $settings;
	}

	/**
	 * Test the quality of your tests by injecting random errors into your source code
	 *
	 * You at least need to specify the $packageKey which should be fuzzed.
	 *
	 * Optionally, specify <b>classFileRegex</b> to only mutate parts of all class files.
	 *
	 * Optionally, if only unit or functional tests should be ran, this can be specified using <b>testType</b>
	 *
	 * @param string $packageKey The package key
	 * @param string $classFileRegex
	 * @param string $testType either <b>Unit</b> or <b>Functional</b>. If not specified, executed both.
	 * @param string $testPath
	 * @return void
	 */
	public function fuzzCommand($packageKey, $classFileRegex = NULL, $testType = 'BOTH', $testPath = NULL) {
		switch ($testType) {
			case 'Unit':
				$this->runUnitTests = TRUE;
				break;
			case 'Functional':
				$this->runFunctionalTests = TRUE;
				break;
			case 'BOTH':
				$this->runUnitTests = TRUE;
				$this->runFunctionalTests = TRUE;
				break;
			default:
				$this->outputFormatted('You specified an <b>invalid testType</b> ("%s"), only <b>Unit</b> or <b>Functional</b> allowed.', array($testType));
				$this->quit(1);
		}

		$package = $this->packageManager->getPackage($packageKey);
		$this->checkPrerequisites($package);

		$this->fuzz($package, $classFileRegex, $testPath);
	}

	/**
	 * Check the prerequisites and exit if they are not met.
	 *
	 * @param \TYPO3\Flow\Package\PackageInterface $package
	 */
	protected function checkPrerequisites(\TYPO3\Flow\Package\PackageInterface $package) {
		if (!$this->existsCommandOnPath('git')) {
			$this->outputLine('FATAL ERROR: <b>git</b> command not found; aborting.');
			$this->quit(1);
		}

		if (!$this->existsCommandOnPath('timeout')) {
			$this->outputLine('FATAL ERROR: <b>timeout</b> command not found; aborting.');
			$this->quit(1);
		}

		if (file_exists(FLOW_PATH_ROOT . 'bin/phpunit')) {
			$this->phpunitBinary = FLOW_PATH_ROOT . 'bin/phpunit';
			$this->outputLine('phpunit(composer) found.');
		} elseif ($this->existsCommandOnPath('phpunit')) {
			$this->phpunitBinary = 'phpunit';
			$this->outputLine('phpunit(PEAR) found.');
		} else {
			$this->outputLine('FATAL ERROR: <b>phpunit</b> command not found (tried inside composer and globally installed; aborting.');
			$this->quit(1);
		}

		if (!is_dir($package->getPackagePath() . '.git')) {
			$this->outputFormatted('FATAL ERROR: Package is not a git repository; aborting.');
			$this->quit(1);
		}
		$output = array();

		exec('cd ' . $package->getPackagePath() . '; git status -z', $output);
		$output = implode('\n', $output);
		if (strlen($output) > 0) {
			$this->outputFormatted('FATAL ERROR: Not all files in the package are committed to Git; aborting.');
			$this->quit(1);
		}
	}

	/**
	 * Run the actual fuzzing process
	 *
	 * @param \TYPO3\Flow\Package\PackageInterface $package
	 * @param string $classFileRegex
	 * @param string $testPath
	 */
	protected function fuzz(\TYPO3\Flow\Package\PackageInterface $package, $classFileRegex, $testPath) {
		$this->resetStatistics();
		$startTime = time();

		if ($this->runUnitTests) {
			$this->unitTestTimeout = $this->determineTestTimeout($package, 'Unit', $testPath);
		}
		if ($this->runFunctionalTests) {
			$this->functionalTestTimeout = $this->determineTestTimeout($package, 'Functional', $testPath);
		}

		$this->loadCodeCoverageData($package, $testPath);

		$fuzzers = $this->loadAndInitializeFuzzers($package, $testPath);

		$this->outputLine();
		$this->outputLine('<b>Legend</b>');
		$this->outputLine('_ invalid PHP code (OK)');
		$this->outputLine('. PHPunit test failure / error (OK)');
		$this->outputLine('T Timeout (OK)');
		$this->outputLine('E PHPunit successful tests (!!!)');
		$this->outputLine();


		$this->outputLine('<b>Generating and Testing Mutations:</b>');
		$this->flush();

		$packagePath = $package->getPackagePath();

		foreach ($package->getClassFiles() as $relativeClassPathAndFilename) {
			$absoluteClassPathAndFilename = $packagePath . $relativeClassPathAndFilename;
			if ($relativeClassPathAndFilename !== NULL && !preg_match('#'. $classFileRegex . '#', $relativeClassPathAndFilename)) {
				continue;
			}
			foreach ($fuzzers as $fuzzer) {
				$fuzzer->initializeMutationsForClassFile($absoluteClassPathAndFilename, $this->codeCoverageData);
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


	/**
	 * @param \TYPO3\Flow\Package\PackageInterface $package
	 * @param string $type either "Unit" or "Functional"
	 * @param string $testPath
	 * @return integer the test timeout
	 */
	protected function determineTestTimeout(\TYPO3\Flow\Package\PackageInterface $package, $type, $testPath) {
		$startTime = time();
		if ($this->runPhpUnitForPackage($package, 10000, $type, $testPath) === FALSE) {
			$this->outputFormatted('NOTICE: No Tests/%s folder found for package %s', array($type, $package->getPackageKey()));
			return;
		}
		$testDuration = time() - $startTime;

		if ($testDuration === 0) {
			$testDuration = 1;
		}

		$timeout = $testDuration * 3;

		$this->outputLine('Unmodified %s tests took %u seconds, setting timeout to %u seconds (to be safe).', array($type, $testDuration, $timeout));
		$this->flush();

		return $timeout;
	}

	/**
	 * Load the code coverage data and fill $this->codeCoverageData
	 * @param \TYPO3\Flow\Package\PackageInterface $package
	 * @param string $testPath
	 */
	protected function loadCodeCoverageData(\TYPO3\Flow\Package\PackageInterface $package, $testPath) {
		$this->output('Collecting code coverage data (might take a few seconds) ...');
		$this->flush();
		if ($this->runUnitTests) {
			// reminder: if first directory of tempnam does not exist, you get a new file
			$unitTestCoveragePathAndFilename = tempnam('/some/non/existing/path', 'fuzzer_unittest_coverage') . '.php';
			$this->runPhpUnitForPackage($package, 1000000, 'Unit', $testPath, '--coverage-php ' . $unitTestCoveragePathAndFilename);
		}

		if ($this->runFunctionalTests) {
			// reminder: if first directory of tempnam does not exist, you get a new file
			$rawFunctionalTestCoveragePathAndFilename = tempnam('/some/non/existing/path', 'fuzzer_functionaltest_coverage_raw') . '.php';
			$this->runPhpUnitForPackage($package, 1000000, 'Functional', $testPath, '--coverage-php ' . $rawFunctionalTestCoveragePathAndFilename);

			$functionalTestCoveragePathAndFilename = tempnam('/some/non/existing/path', 'fuzzer_functionaltest_coverage') . '.php';

			$this->codeCoverageCommandController->convertCommand($rawFunctionalTestCoveragePathAndFilename, $functionalTestCoveragePathAndFilename, $package->getPackageKey());
		}

		if ($this->runUnitTests && $this->runFunctionalTests) {
			$mergedTestCoveragePathAndFilename = tempnam('/some/non/existing/path', 'fuzzer_test_coverage') . '.php';
			$this->codeCoverageCommandController->mergeCommand($unitTestCoveragePathAndFilename, $functionalTestCoveragePathAndFilename, $mergedTestCoveragePathAndFilename);
		} elseif ($this->runUnitTests) {
			$mergedTestCoveragePathAndFilename = $unitTestCoveragePathAndFilename;
		} else { // only functional tests run
			$mergedTestCoveragePathAndFilename = $functionalTestCoveragePathAndFilename;
		}

		$mergedCloverTestCoveragePathAndFilename = tempnam('/some/non/existing/path', 'fuzzer_test_coverage') . '.xml';
		$this->codeCoverageCommandController->renderCommand($mergedTestCoveragePathAndFilename, $mergedCloverTestCoveragePathAndFilename, 'clover');

		$this->codeCoverageData = new \SimpleXMLElement(file_get_contents($mergedCloverTestCoveragePathAndFilename));
		$this->outputLine(' Done.');
		$this->flush();
	}

	/**
	 * @return array<\Sandstorm\Fuzzer\Fuzzer\AbstractFuzzer>
	 */
	protected function loadAndInitializeFuzzers() {
		$fuzzers = array();
		foreach ($this->settings['fuzzers'] as $fuzzerClassName) {
			$fuzzers[] = new $fuzzerClassName();
		}
		return $fuzzers;
	}

	/**
	 * @param \TYPO3\Flow\Package\PackageInterface $package
	 * @param string $relativeClassPathAndFilename
	 * @param string $testPath
	 * @return void
	 */
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

		if ($this->runUnitTests) {
			$returnValue = $this->runPhpUnitForPackage($package, $this->unitTestTimeout, 'Unit', $testPath);
		} else {
				// if unit tests did not run, we emulate a "successful" run.
			$returnValue = 0;
		}

		if ($returnValue === 0 && $this->runFunctionalTests) {
				// only if Unit Tests were "successful" we need to run the functional tests to detect the modifications.
				// if unit tests failed already or timed out we do not need to continue with the functional ones.
			$returnValue = $this->runPhpUnitForPackage($package, $this->functionalTestTimeout, 'Functional', $testPath);
		}

		if ($returnValue === 0) {
				// PHPUnit did NOT report an error, i.e. all tests ran through. That's a severe problem, as we modified the
				// source code and our unit tests did not find that... we're after such modifications :)
			$mutation = new \Sandstorm\Fuzzer\UndetectedMutation();
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
	 * - FALSE if the unit / functional test directory does not exist
	 *
	 *
	 * @param \TYPO3\Flow\Package\PackageInterface $package
	 * @param integer $timeout the timeout
	 * @param string $testType either 'Unit' or 'Functional'
	 * @param string $testPath path inside Tests/$testType
	 * @param string $additionalArguments additional arguments to be passed to PHPUnit
	 * @return null
	 */
	protected function runPhpUnitForPackage(\TYPO3\Flow\Package\PackageInterface $package, $timeout, $testType, $testPath = '', $additionalArguments = '') {
		$output = array();
		$returnValue = NULL;
		if (!is_dir($package->getPackagePath() . 'Tests/' . $testType)) {
			return FALSE;
		}

		exec(sprintf('timeout %s %s -c %sBuild/BuildEssentials/PhpUnit/%sTests.xml %s %sTests/%s/%s 2>/dev/null', $timeout, $this->phpunitBinary, FLOW_PATH_ROOT, $testType, $additionalArguments, $package->getPackagePath(), $testType, $testPath), $output, $returnValue);
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

	/**
	 * Check if certain commands exist on the PATH, taken from
	 * http://stackoverflow.com/questions/12424787/how-to-check-if-a-shell-command-exists-from-php/15475417#15475417
	 *
	 * @param string $command
	 * @return boolean TRUE if the command was found, FALSE otherwise
	 */
	protected function existsCommandOnPath($command) {
		if (strtolower(substr(PHP_OS, 0, 3)) === 'win') {
			$fp = popen("where $command", "r");
			$result = fgets($fp, 255);
			$exists = !preg_match('#Could not find files#', $result);
			pclose($fp);
		} else { # non-Windows
			$fp = popen("which $command", "r");
			$result = fgets($fp, 255);
			$exists = !empty($result);
			\pclose($fp);
		}

		return $exists;
	}
}
?>
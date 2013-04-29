Fuzzer -- Validating Unit Test Coverage
=======================================

-- Testing your Unit Tests --

(c) Sebastian Kurfürst, Sandstorm Media UG (haftungsbeschränkt)

Do you use automated unit tests to check your software?
Are you monitoring the Code Coverage of your Unit Tests?
Yeah -- then this software is for you! It helps to find missing edge cases in tests.

It implements a technique called *Fuzzing*.

Our Notion Of Coverage
----------------------

Normal "code coverage" metrics (as measured by PHPUnit for example) only counts
how often a code line is executed during the test runs.

So, while PHPUnit Code Coverage checks that you *execute* all covered lines,
it does not prove that you *valdiate* the functionality executed on the particular
line.

An example shall illustrate this. Imagine you have the following class:

``` php
class Foo {
	protected $someInternalState = 0;
	public function someMethod() {
		$this->someInternalState++;

		return 42;
	}

	public function getInternalState() {
		return $this->someInternalState;
	}
}
```

... and the following testcases:

``` php
/**
 * @test
 */
public function someTest() {
	$myObject = new Foo();
	$this->assertSame(42, $myObject->someMethod());
}

/**
 * @test
 */
public function someOtherTest() {
	$myObject = new Foo();
	$this->assertSame(0, $myObject->someInternalState());
}
```

This example has a *Code Coverage* of **100 %**, so you might say "yeah, great,
nothing to improve here". However, if you look closely into the code, you will
see that commenting out the line `$this->someInternalState++` will **not break
the unit tests**, despite of broken functionality.

Here Comes The Fuzzer
---------------------

The fuzzer automatically **modifies your source code**, checks that the resulting
file has a valid syntax, and then runs the unit tests. In a perfect world, the
unit tests would fail after every modification, as we modified the source code,
and deliberately broke some functionality.

The fuzzer detects cases where **the source has been modified, but the unit tests
still run through successfully**, giving indication which other test cases you
need to write.

Installation
------------

**The system has been tested on Mac OS, and should also run on Linux. It will
probably not run on Windows.**

```
cd <YourFlow3Root>/Packages/Application
git clone --recursive git://github.com/sandstorm/Sandstorm.Fuzzer.git
cd ../../
./flow3 package:activate Sandstorm.Fuzzer
```

You additionally need the following command-line tools installed:

- git
- php (should not be problematic, as you have a working FLOW3 installation)
- xdebug (as we must be able to run phpunit with code coverage reports)
- phpunit
- timeout (on Mac OS, you can install it using MacPorts with "port install timeout"; installed by default in many linux distributions)

Usage
-----

First, make sure you have a reasonably **high code coverage**, as the fuzzer
will only work on code which is covered by Unit Tests (to reduce the number of false
positives).

```
./flow3 fuzzer:fuzz <ThePackageKeyYouWantToTest>
```

The package must have **its own Git Repository** and must **not have any uncommitted
changes**, else the tool will not run.

Example Output
--------------

```
Unmodified unit tests took 1 seconds, setting timeout to 3 seconds (to be safe).
Generating and Testing Mutations:
_.__.._..._.___....__.E._._..._..._.__.E._...._..E__.__.._.._.._E_.___._.___...E_.._._._..._.T._..._



Undetected Mutations
--------------------

Classes/Domain/Model/FooBar.php
  Package: TYPO3.FooBar
  Diff follows below
diff --git a/Classes/Domain/Model/FooBar.php b/Classes/Domain/Model/FooBar.php
index 8fde46c..bee58d7 100644
--- a/Classes/Domain/Model/FooBar.php
+++ b/Classes/Domain/Model/FooBar.php
@@ -107,7 +107,7

 	public function registerIfPossible() {
-		parent::registerIfPossible();
+# 		parent::registerIfPossible();
 		foreach ($this->elements as $element) {
 			$element->doStuff();
 		}

... output for all other undetected mutations ...


Fuzzing Statistics
------------------
Total Mutations: 253
Mutations with Broken Syntax: 120
Detected Mutations: 127
Undetected mutations: 6 (see above for details)
Total Runtime: 61 s

```

In the above example, you see that commenting out the "parent::..." call
did not make the unit tests fail -- so you can now write this additional
unit test.

For every run mutation, a progress indicator is shown like it is done by
PHPUnit. The characters mean the following:

- `_` the mutation contains PHP syntax errors
- `.` the unit tests could be ran, and they failed correctly
- `T` the unit tests ran into a timeout (which is also expected behavior)
- `E` the unit tests ran through SUCCESSFULLY; meaning a mutation was not detected.
  **These are the cases we are looking for.**

Timeouts
--------

As we modify source code, it could easily be that the modified source code does
not terminate anymore. Thus, we first check how long the unit tests take, add
some offset to it and abort the tests if they run longer than expected. If this
happens, we handle it the same way like a test failure.

Internals
---------

There are different fuzzers available in the system which generate source code
mutations:

- *SingleLineFuzzer*: Comments out a single source code line at a time. Only
  works on lines which are executed at least once during the unit tests
- (more to come here later)

License
-------

All the code is licensed under the GPL license.
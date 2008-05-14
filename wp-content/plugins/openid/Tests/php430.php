------------

<?php

class example1 {
	var $enabled;
	var $text;
	function example1() {
		$this->enabled = false;
		$this->text = "Set by Example1->Constructor";
		echo "1: Var is $this->enabled\n";
	}
	function startup() {
		$this->enabled = true;
		$this->text = "Set by Example1->Startup";
		echo "2: Var is $this->enabled\n";
	}
}

class example2 {
	var $ex1;
	function example2() {
		
	}
	
	function startup() {
		$this->ex1 = new example1();
		$this->ex1->startup();
		echo "3: Var is " . $this->ex1->enabled . " , " . $this->ex1->text . "\n";
	}
	
	function printout() {
		echo "5: Var is " . $this->ex1->enabled . " , " . $this->ex1->text . "\n";
		echo "5.1: Var is " . ( $this->ex1->enabled ? "TRUE" : "FALSE" ) . " , " . $this->ex1->text . "\n";
		echo "5.2: Var is " . ( ($this->ex1->enabled === true) ? "TRUE" : "FALSE" ) . " , " . $this->ex1->text . "\n";
	}
	
}

echo "Starting\n";

$ex0 = new example2();
$ex0->startup();

$ex0->printout();

echo "6: Var is " . $ex0->ex1->enabled . " , " . $ex0->ex1->text . "\n";

echo "Finished\n-----------\n\n";

?>
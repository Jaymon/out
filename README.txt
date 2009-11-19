out is an easy to use php class to get variable information, it's as simple as:

$var1 = 'foo';
out::e($var1);

to print out info on the variable like:

$var = "foo" (/test.php:2)

have more than one variable you want to check? You can pass them all in the same call:

$var2 = 'bar';
out::e($var1,$var2);

there are also some other functions to help with debugging:

out::h(1); // prints 'here 1'

out::b('title',5); // prints 5 lines of = with title in the middle, just try it and you'll understand 

out::i($var); // prints more information about the $var than just value, really handy for objects. NOTE: some of the stuff in out:i() currently requires php 5.3 to be useful, function still works, just not as useful

out::c($var); // prints out an OCTAL dump of the characters in $var, handy for seeing what whitespace $var has, etc.

out::m($obj_list,'func_name'); // iterate through a list of objects and print out the output of $obj->func_name()

out::p(); /* do something long and complex here */ out::p('how long this code took'); // prints out how long the code between the two p() calls took to run

now, if you want to print to a file instead, just add an f in front of all the functions and they will print to out.txt instead:

out::fe();
out::fi();
out::fh();
...

that's pretty much it.
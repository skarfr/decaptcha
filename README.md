# decaptcha
This is a simple abstract PHP class containing useful functions which could be used to "read"/"guess" the text written in an image.
Case scenario: You want to automate the submission of a particular web form which contains a "simple" captcha.
This class won't break any captchas by itself, but by using your logic and some of the functions available in decaptchaMaster, you might be able to code a working solveCaptcha() function.

### How does it works ?
Following the Object Inheritance principle, you can create a PHP class and extend/inherit from decaptchaMaster.php. Then, you must implement the function solveCaptcha() matching the captcha specific features that you are aiming to solve programmatically.
decaptchaMaster doesn't know the alphabet and can not guess/read/solve without a proper "learning".
But don't worry, there is no AI involved in here at all. The learning methode is much more "visual".

### What kind of captchas can it breaks ?
Those functions can help in the breaking of "simple" captchas. By "simple" captcha, i mean an image with a simple text, without any crazy random distorsion nor random angle (from a captcha to an other).

### Examples
* Freeglobes (URL directory system): http://demo.skar.fr/decaptcha/freeglobes.php
* Categorizator / Rewrite YourPHPAnnuaire (URL directory system): http://demo.skar.fr/decaptcha/categorizator.php

### Requirements
You will need to install few components on your server:
* PHP Hypertext Preprocessor: https://secure.php.net
* PHP GD: https://secure.php.net/manual/en/book.image.php
* cURL Client URL Library: https://secure.php.net/manual/en/book.curl.php

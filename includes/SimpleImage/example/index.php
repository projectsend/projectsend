<?php
require '../src/claviska/SimpleImage.php';

// Ignore notices
error_reporting(E_ALL & ~E_NOTICE);

try {
  // Create a new SimpleImage object
  $image = new \claviska\SimpleImage();

  // Manipulate it
  $image
    ->fromFile('parrot.jpg')              // load parrot.jpg
    ->autoOrient()                        // adjust orientation based on exif data
    ->bestFit(300, 600)                   // proportinoally resize to fit inside a 250x400 box
    ->flip('x')                           // flip horizontally
    ->colorize('DarkGreen')               // tint dark green
    ->border('black', 5)                  // add a 5 pixel black border
    ->overlay('flag.png', 'bottom right') // add a watermark image
    ->toScreen();                         // output to the screen

} catch(Exception $err) {
  // Handle errors
  echo $err->getMessage();
}

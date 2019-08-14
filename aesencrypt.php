<?php
class AESENCRYPT {


  function encryptFile($source)
  {
      $dest = 'upload/files/en_'.$source;
      $source = 'upload/files/'.$source;
      $key = "8765432198765432187654321987654321";
      $fileEncryptionblocks = 10000;
      $key = substr(sha1($key, true), 0, 32);
      $iv = openssl_random_pseudo_bytes(32);

      $error = false;
      if ($fpOut = fopen($dest, 'w')) {
          // Put the initialzation vector to the beginning of the file
          fwrite($fpOut, $iv);
          if ($fpIn = fopen($source, 'rb')) {
              while (!feof($fpIn)) {
                  $plaintext = fread($fpIn, 32 * $fileEncryptionblocks);
                  $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                  // Use the first 32 bytes of the ciphertext as the next initialization vector
                  $iv = substr($ciphertext, 0, 32);
                  fwrite($fpOut, $ciphertext);
              }
              fclose($fpIn);
          } else {
              $error = true;
          }
          fclose($fpOut);
          unlink($source);
          // copy($dest, $source);
          // unlink($dest);
      } else {
          $error = true;
      }

      return $error ? false : $dest;
  }

  function decryptFile($source)
  {
      $key = "8765432198765432187654321987654321";
      $key = substr(sha1($key, true), 0, 32);
      $dest = 'upload/files/temp/'.$source;
      $source = 'upload/files/'.$source;
      $fileEncryptionblocks = 10000;
      $error = false;
      if ($fpOut = fopen($dest, 'w')) {
          if ($fpIn = fopen($source, 'rb')) {
              // Get the initialzation vector from the beginning of the file
              $iv = fread($fpIn, 32);
              while (!feof($fpIn)) {
                  $ciphertext = fread($fpIn, 32 * ($fileEncryptionblocks + 1)); // we have to read one block more for decrypting than for encrypting
                  $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                  // Use the first 32 bytes of the ciphertext as the next initialization vector
                  $iv = substr($ciphertext, 0, 32);
                  fwrite($fpOut, $plaintext);
              }
              fclose($fpIn);
          } else {
              $error = true;
          }
          fclose($fpOut);
      } else {
          $error = true;
      }

      return $error ? false : $dest;
  }
}
 ?>

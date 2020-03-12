<?php

class AESENCRYPT {


  function encryptFile($source,$section='',$userid='')
  {
    if($section==''){
        rename('upload/files/'.$source,'upload/files/en_'.$source);
        $dest = 'upload/files/'.$source;
        $source = 'upload/files/en_'.$source;
        $key = "8765416198765416187654161987654161";
        $fileEncryptionblocks = 10000000;
        $key = substr(sha1($key, true), 0, 16);
        $iv = openssl_random_pseudo_bytes(16);

        $error = false;
        if ($fpOut = fopen($dest, 'w')) {
            // Put the initialzation vector to the beginning of the file
            fwrite($fpOut, $iv);
            if ($fpIn = fopen($source, 'rb')) {
                while (!feof($fpIn)) {
                    $plaintext = fread($fpIn, 16 * $fileEncryptionblocks);
                    $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                    // Use the first 16 bytes of the ciphertext as the next initialization vector
                    $iv = substr($ciphertext, 0, 16);
                    fwrite($fpOut, $ciphertext);
                }
                fclose($fpIn);
            } else {
                $error = true;
            }
            unlink($source); 
        } else {
            $error = true;
        }

        return $error ? false : $dest;
    }else{
      if($section=='upload'){
        rename(UPLOADED_FILES_FOLDER.'../../img/avatars/signature/'.$userid.'/'.$source,UPLOADED_FILES_FOLDER.'../../img/avatars/signature/'.$userid.'/'.'en_'.$source);
        $dest = UPLOADED_FILES_FOLDER.'../../img/avatars/signature/'.$userid.'/'.$source;
        $source = UPLOADED_FILES_FOLDER.'../../img/avatars/signature/'.$userid.'/'.'en_'.$source;
        $key = "8765416198765416187654161987654161";
        $fileEncryptionblocks = 10000;
      }else{
        rename(UPLOADED_FILES_FOLDER.'../../img/avatars/tempsignature/'.$userid.'/'.$source,UPLOADED_FILES_FOLDER.'../../img/avatars/tempsignature/'.$userid.'/'.'en_'.$source);
        $dest = UPLOADED_FILES_FOLDER.'../../img/avatars/tempsignature/'.$userid.'/'.$source;
        $source = UPLOADED_FILES_FOLDER.'../../img/avatars/tempsignature/'.$userid.'/'.'en_'.$source;
        $key = "8765416198765416187654161987654161";
        $fileEncryptionblocks = 1000000;
      }
        $key = substr(sha1($key, true), 0, 16);
        $iv = openssl_random_pseudo_bytes(16);
        $error = false;
        if ($fpOut = fopen($dest, 'w')) {
          // Put the initialzation vector to the beginning of the file
          fwrite($fpOut, $iv);
          if ($fpIn = fopen($source, 'rb')) {
            while (!feof($fpIn)) {
              $plaintext = fread($fpIn, 16 * $fileEncryptionblocks);
              $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
              // Use the first 16 bytes of the ciphertext as the next initialization vector
              $iv = substr($ciphertext, 0, 16);
              fwrite($fpOut, $ciphertext);
            }
            fclose($fpIn);
          } else {
            $error = true;
          }
            unlink($source); 
        } else {
          $error = true;
        }
        return $error ? false : $dest;
    }
  }

  function decryptFile($source,$section='',$userid='')
  {
     if($section==''){
      // echo '12112';exit();
        $key = "8765416198765416187654161987654161";
        $key = substr(sha1($key, true), 0, 16);
        $dest = 'upload/files/temp/'.$source;
        $source = 'upload/files/'.$source;
        $fileEncryptionblocks = 10000000;
        $error = false;
        if ($fpOut = fopen($dest, 'w')) {

            if ($fpIn = fopen($source, 'rb')) {
                // Get the initialzation vector from the beginning of the file
                $iv = fread($fpIn, 16);
                while (!feof($fpIn)) {
                    $ciphertext = fread($fpIn, 16 * ($fileEncryptionblocks + 1)); // we have to read one block more for decrypting than for encrypting
                    $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                    // Use the first 16 bytes of the ciphertext as the next initialization vector
                    $iv = substr($ciphertext, 0, 16);
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
      }else{
        $key = "8765416198765416187654161987654161";
        $key = substr(sha1($key, true), 0, 16);
        if($section=='upload'){
          $dest = UPLOADED_FILES_FOLDER.'../../img/avatars/signature/'.$userid.'/temp/'.$source;
          $source = UPLOADED_FILES_FOLDER.'../../img/avatars/signature/'.$userid.'/'.$source;
        }else{
          $dest = UPLOADED_FILES_FOLDER.'../../img/avatars/tempsignature/'.$userid.'/temp/'.$source;
          $source = UPLOADED_FILES_FOLDER.'../../img/avatars/tempsignature/'.$userid.'/'.$source;
        }

        $fileEncryptionblocks = 1000000;
        $error = false;
        if ($fpOut = fopen($dest, 'w')) {

            if ($fpIn = fopen($source, 'rb')) {
                // Get the initialzation vector from the beginning of the file
                $iv = fread($fpIn, 16);
                while (!feof($fpIn)) {
                    $ciphertext = fread($fpIn, 16 * ($fileEncryptionblocks + 1)); // we have to read one block more for decrypting than for encrypting
                    $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                    // Use the first 16 bytes of the ciphertext as the next initialization vector
                    $iv = substr($ciphertext, 0, 16);
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
  function encryptZipFile($source)
  {
      rename('upload/files/temp/'.$source,'upload/files/temp/en_'.$source);
      $dest = 'upload/files/temp/'.$source;
      $source = 'upload/files/temp/en_'.$source;
      $key = "8765416198765416187654161987654161";
      $fileEncryptionblocks = 10000000;
      $key = substr(sha1($key, true), 0, 16);
      $iv = openssl_random_pseudo_bytes(16);

      $error = false;
      if ($fpOut = fopen($dest, 'w')) {
          // Put the initialzation vector to the beginning of the file
          fwrite($fpOut, $iv);
          if ($fpIn = fopen($source, 'rb')) {
              while (!feof($fpIn)) {
                  $plaintext = fread($fpIn, 16 * $fileEncryptionblocks);
                  $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                  // Use the first 16 bytes of the ciphertext as the next initialization vector
                  $iv = substr($ciphertext, 0, 16);
                  fwrite($fpOut, $ciphertext);
              }
              fclose($fpIn);
          } else {
              $error = true;
          }
         unlink($source);
      } else {
          $error = true;
      }

      return $error ? false : $dest;
  }
  function decryptZipFile($source)
  {
      $key = "8765416198765416187654161987654161";
      $key = substr(sha1($key, true), 0, 16);
      $dest = 'upload/files/temp/zip/'.$source;
      $source = 'upload/files/temp/'.$source;
      $fileEncryptionblocks = 10000000;
      $error = false;
      if ($fpOut = fopen($dest, 'w')) {
          if ($fpIn = fopen($source, 'rb')) {
              // Get the initialzation vector from the beginning of the file
              $iv = fread($fpIn, 16);
              while (!feof($fpIn)) {
                  $ciphertext = fread($fpIn, 16 * ($fileEncryptionblocks + 1)); // we have to read one block more for decrypting than for encrypting
                  $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                  // Use the first 16 bytes of the ciphertext as the next initialization vector
                  $iv = substr($ciphertext, 0, 16);
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

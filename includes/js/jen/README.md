# Jen is a portable and safe Javascript password/number generator

[![Build Status](https://travis-ci.org/mykiimike/jen.svg)](https://travis-ci.org/mykiimike/jen)
[![][gt-issues]][gt-issues]
[![][gt-licence]][gt-licence]

[![NPM](https://nodei.co/npm/node-jen.png?downloads)](https://nodei.co/npm/node-jen/)

Jen allows to generate passwords, random bytes and random numbers securely using cryptographic approach.

Jen supports 4 engines to generate random bytes :
* NodeJS Crypto API
* W3C Crypto API http://www.w3.org/TR/WebCryptoAPI/
* Microsoft Crypto API https://msdn.microsoft.com/en-us/library/windows/desktop/aa380256(v=vs.85).aspx
* Failsafe

Failsafe uses Math.random() which is not safe because the random number generator doesn't use a 
cryptographic approach.   

* You can see a demo at http://mykiimike.github.io/jen/
* Explaination [Cross Domain Math.random() prediction](http://ifsec.blogspot.fr/2012/05/cross-domain-mathrandom-prediction.html)

## Hardened passwords
Jen has a hardened passwords generator activated by default which adds specials chars into the password.
For those who have SQL injection in the password field they must set to **false** the **hardened** 
argument at the constructor. 

## Install

### On NodeJS
```bash
npm install node-jen
```

### On browser
```html
<script type="text/javascript" src="path/to/jen.js"></script>
```

## API

### Jen(hardened)
```js
var hdl = new Jen(true);
```
* hardened: Use hardened version includes specials chars into password generator: (default true)

## Jen.password(min, max, regex)
## Jen.password(min, max)
## Jen.password(min)
This function returns a random String.

* min: Minimum String length (must be upper to 4)
* max: Maximum String length
* regex: Regular expression to filter selected chars

```js
console.log("10 Passwords from 10 to 30 w/o hardening");
for(var a=0; a<10; a++)
	console.log(hdl.password(10, 30));

console.log("10 Passwords fixed 5 w/o hardening");
for(var a=0; a<10; a++)
	console.log(hdl.password(5));

console.log("10 Passwords from 10 to 30 w/ hardening");
hdl.hardening(true);
for(var a=0; a<10; a++)
	console.log(hdl.password(10, 30));

console.log("10 Passwords fixed 10 w/ hardening");
for(var a=0; a<10; a++)
	console.log(hdl.password(10, 10));

console.log("10 Passwords fixed 10 w/o hardening with regex [A-F0-9]");
hdl.hardening(false);
for(var a=0; a<10; a++)
	console.log(hdl.password(10, 10, /[A-F0-9]/));
```

## Jen.random(size)
Generate random numbers (integers) into a String.

* size: Size of bytes read from randomBytes

```js
console.log("10 Random string (based on 4 bytes)");
for(var a=0; a<10; a++)
	console.log(hdl.random(4));
```

## Jen.randomBytes(size)
Generate random bytes into an Uint8Array.

* size: Size of bytes read from randomBytes

```js
console.log("10 Random 4 bytes");
for(var a=0; a<10; a++)
	console.log(hdl.randomBytes(4));
```

## Jen.randomBetween(max, min)
Generate random number between the given **min** and **max** arguments

* max: Maximum value
* min: Minimum value

```js
console.log("10 Random number between 10 and 3000");
for(var a=0; a<10; a++)
	console.log(hdl.randomBetween(3000, 10));
```

### Jen.hardening(bool)
Set on/off hardening string generator
 
* bool: boolean to activate hardened password generator (default true)
  
### Jen.engine() 
Returns the current engine in a String
```js
console.log("Engine: "+hdl.engine());
```

### Jen.stats() 
Get password generation statistics 
```js
console.log("Last pass stats: "+hdl.stats());
```

### Jen.fill()
This function fill the random buffer line. You don't need to use it.


[gt-issues]: https://img.shields.io/github/issues/mykiimike/jen.svg
[gt-licence]: https://img.shields.io/badge/license-GPLv3-blue.svg


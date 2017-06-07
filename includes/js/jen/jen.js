/*
 * Jen is a portable password generator using cryptographic approach
 * Copyright (C) 2015  Michael VERGOZ @mykiimike
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 */

"use strict";

var _serverSide = false;

function JenFailsafe() { }

/* not a cryptographic approach */
JenFailsafe.getRandomValues = function(buffer) {
	if (!(buffer instanceof Uint8Array))
		buffer = new Uint8Array(256);
	
	var rd = 0;
	for(var a=0; a<buffer.length; a++) {
		while(1) {
			rd = Math.round(Math.random()*256);
			if(rd >= 0 && rd <= 255)
				break;
		}
		buffer[a] = rd;
	}
	return(buffer);
};

function Jen(hardened) {
	if(!(this instanceof Jen))
		return new Jen(hardened);
	this.hardened = hardened && hardened == true ? hardened : false;
	this.dump = new Uint8Array(256);
	this.mode = '';
	this.version = '1.0.6-dev';
	if(_serverSide == true) {
		this.crypto = require("crypto");
		this.mode = "NodeJS CryptoAPI";
	}
	else {
		this.crypto = window.crypto || window.msCrypto;
		if(window.crypto) {
			this.mode = "W3C CryptoAPI";
			this.crypto = window.crypto;
		}
		else if(window.msCrypto) {
			this.mode = "Microsoft CryptoAPI";
			this.crypto = window.msCrypto;
		}
		if(!this.crypto) {
			this.mode = "Failsafe";
			this.crypto = JenFailsafe;
		}
	}
}

Jen.prototype.engine = function() {
	return(this.mode);
};

Jen.prototype.fill = function() {
	if(_serverSide == true)
		this.dump = this.crypto.randomBytes(256);
	else
		this.crypto.getRandomValues(this.dump);
};

Jen.prototype.randomBytes = function(size) {
	if(size <= 0)
		size = 1;
	
	if(_serverSide == true)
		return(this.crypto.randomBytes(size));
	
	var r = new Uint8Array(size);
	this.crypto.getRandomValues(r);
	return(r);
};

Jen.prototype.random = function(size) {
	if(size <= 0)
		size = 4;
	else if(size > 2)
		size = 4;
	
	var d = this.randomBytes(size);
	
	if(_serverSide == true) {
		if(size == 1)
			return(d.readUInt8(0));
		else if(size == 2)
			return(d.readUInt16LE(0));
		else
			return(d.readUInt32LE(0));
	}

	var dv = new DataView(d.buffer), r;
	if(size == 1)
		r = dv.getUint8(0);
	else if(size == 2)
		r = dv.getUint16(0);
	else
		r = dv.getUint32(0);
	
	return(r);
};

Jen.prototype.randomBetween = function(max, min) {
	if(max <= 0)
		max = Math.pow(2, 32);
	if(!min)
		min = 0;
	if(min >= max)
		return(NaN);
	var size = 1;
	var ml2 = Math.log(max)/Math.log(2);
	if(ml2 > 16)
		size = 4;
	else if(ml2 > 8)
		size = 2;
	var num;
	do {
		num = this.random(size);
	} while(num > max || num < min);
	return(num);
};

Jen.prototype.hardening = function(bool) {
	this.hardened = !!bool;
};

Jen.prototype.password = function(min, max, regex) {
	var start = new Date().getTime();
	if(!(regex instanceof RegExp))
		regex = null;

	min = min < 1 ? 1 : min;
	max = max > min ? max : min;

	var b = 0, ret = '';
	var cur = max;

	if(min != max) {
		cur = 0;
		
		var nBi = Math.ceil(Math.log(max)/Math.log(2)),
		nBy = Math.ceil(nBi/8), nByBi = nBy*8; 
		while(cur == 0) {
			var r = this.random(nBy)>>(nByBi-nBi);
			if(r >= min && r <= max) {
				cur = r;
				break;
			}
		}
	}

	b = 0;
	while(b < cur) {
		
		this.fill();
		var array = this.dump;
		for (var a=0; a < array.length && b < cur; a++) {
			if(
				(array[a] >= 0x30 && array[a] <= 0x39) ||
				(array[a] >= 0x41 && array[a] <= 0x5a) ||
				(array[a] >= 0x61 && array[a] <= 0x7a)) {
				if(regex) {
					if(regex.test(String.fromCharCode(array[a]))) {
						ret += String.fromCharCode(array[a]);
						b++;
					}
				}
				else {
					ret += String.fromCharCode(array[a]);
					b++;
				}
			}
			else if(this.hardened == true && (
					array[a] == 0x21 ||
					array[a] == 0x23 ||
					array[a] == 0x25 ||
					(array[a] == 0x28 && array[a] <= 0x2f) ||
					(array[a] == 0x3a && array[a] <= 0x40)
				)) {
				if(regex) {
					if(regex.test(String.fromCharCode(array[a]))) {
						ret += String.fromCharCode(array[a]);
						b++;
					}
				}
				else {
					ret += String.fromCharCode(array[a]);
					b++;
				}
			}
		}
	}
	this.fill();
	this._time = new Date().getTime()-start;
	return(ret);

};

Jen.prototype.stats = function(min, max, regex) {
	return(this._time);
};

if(typeof module !== 'undefined' && module.exports) {
	_serverSide = true;
	module.exports = Jen;
}




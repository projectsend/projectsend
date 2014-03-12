/*
Script: GrowingInput.js
	Alters the size of an input depending on its content

	License:
		MIT-style license.

	Authors:
		Guillermo Rauch
*/

(function($){

$.GrowingInput = function(element, options){
	
	var value, lastValue, calc;
	
	options = $.extend({
		min: 0,
		max: null,
		startWidth: 15,
		correction: 15
	}, options);
	
	element = $(element).data('growing', this);
	
	var self = this;
	var init = function(){
		calc = $('<span></span>').css({
			'float': 'left',
			'display': 'inline-block',
			'position': 'absolute',
			'left': -1000
		}).insertAfter(element);
		$.each(['font-size', 'font-family', 'padding-left', 'padding-top', 'padding-bottom', 
		 'padding-right', 'border-left', 'border-right', 'border-top', 'border-bottom', 
		 'word-spacing', 'letter-spacing', 'text-indent', 'text-transform'], function(i, p){				
				calc.css(p, element.css(p));
		});
		element.blur(resize).keyup(resize).keydown(resize).keypress(resize);
		resize();
	};
	
	var calculate = function(chars){
		calc.text(chars);
		var width = calc.width();
		return (width ? width : options.startWidth) + options.correction;
	};
	
	var resize = function(){
		lastValue = value;
		value = element.val();
		var retValue = value;		
		if(chk(options.min) && value.length < options.min){
			if(chk(lastValue) && (lastValue.length <= options.min)) return;
			retValue = str_pad(value, options.min, '-');
		} else if(chk(options.max) && value.length > options.max){
			if(chk(lastValue) && (lastValue.length >= options.max)) return;
			retValue = value.substr(0, options.max);
		}
		element.width(calculate(retValue));
		return self;
	};
	
	this.resize = resize;
	init();
};

var chk = function(v){ return !!(v || v === 0); };
var str_repeat = function(str, times){ return new Array(times + 1).join(str); };
var str_pad = function(self, length, str, dir){
	if (self.length >= length) return this;
	str = str || ' ';
	var pad = str_repeat(str, length - self.length).substr(0, length - self.length);
	if (!dir || dir == 'right') return self + pad;
	if (dir == 'left') return pad + self;
	return pad.substr(0, (pad.length / 2).floor()) + self + pad.substr(0, (pad.length / 2).ceil());
};

})(jQuery);